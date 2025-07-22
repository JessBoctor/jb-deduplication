<?php
/**
 * PDF Media Deduplication WP-CLI Command
 *
 * Usage:
 *   wp pdf-media deduplicate [--dry-run] [--start-post-id=<id>]
 *
 * Examples:
 *   wp pdf-media deduplicate --dry-run
 *   wp pdf-media deduplicate --start-post-id=500
 *   wp pdf-media deduplicate --dry-run --start-post-id=1000
 *
 * Place this file in your WordPress environment and run the above commands from the terminal.
 */
if ( ! defined( 'WP_CLI' ) && WP_CLI ) {
    return;
}

if ( ! class_exists( 'PDF_Media_Deduplication_Command' ) ) {
    class PDF_Media_Deduplication_Command {

        /**
         * Number of posts to process per batch.
         *
         * @var int
         */
        private $batch_size = 100;

        /**
         * Whether to run in test mode (dry run).
         *
         * @var bool
         */
        private $dry_run = false;

        /**
         * Minimum post ID to start processing from.
         *
         * @var int
         */
        private $start_post_id = 1;

        /**
         * Holds the last post ID returned in the batch.
         *
         * @var int|null
         */
        private $last_post_id = null;

        /**
         * Holds unique post titles to check for duplicates.
         *
         * @var array
         */
        private $unique_post_titles = array();

        /**
         * Total number of PDF posts detected in the media library.
         *
         * @var int
         */
        private $total_duplicate_posts = 0;

        /**
         * Search for duplicate PDF media files.
         */
        public function __invoke( $args, $assoc_args ) {
            // Determine if we are running in dry run mode
            $this->dry_run = isset( $assoc_args['dry-run'] );
            if ( $this->dry_run ) {
                WP_CLI::log( 'Running in dry run mode. No changes will be made.' );
            } else {
                WP_CLI::log( 'Running in live mode. Changes will be applied.' );
            }

            // Determine the starting post ID from CLI args or saved option
            $this->determine_start_post_id( $assoc_args );

            // Set the batch size if provided
            if ( isset( $assoc_args['batch-size'] ) && is_numeric( $assoc_args['batch-size'] ) ) {
                $this->batch_size = intval( $assoc_args['batch-size'] );
            }
            WP_CLI::log( "Batch size set to: {$this->batch_size}" );

            // Fetch the past unique post titles from options
            $saved_unique_post_titles = get_option( 'one-time-script-pdf-deduplication-unique-post-titles', array() );
            if ( is_array( $saved_unique_post_titles ) ) {
                $this->unique_post_titles = $saved_unique_post_titles;
                WP_CLI::log( 'Loaded unique post records from options.' );
            } else {
                WP_CLI::log( 'No unique post records found in options.' );
            }

            // Being the deduplication process
            $this->deduplicate_pdfs();
        }

        /**
         * Deduplicate PDF media files in the WordPress media library.
         *
         * @when after_wp_load
         */
        public function deduplicate_pdfs() {
            WP_CLI::log( 'Starting PDF media deduplication...' );

            // Fetch PDF posts for this batch
            $pdf_posts = $this->get_pdf_posts();
            if ( empty( $pdf_posts ) ) {
                WP_CLI::log( 'No PDF posts found to deduplicate.' );
                return;
            }
            // Log the number of PDF posts found
            $pdf_posts_count = count( $pdf_posts );
            WP_CLI::log( "Found {$pdf_posts_count} PDF posts to process." );
            $this->save_last_post_id_to_options();
            WP_CLI::log( "Last post ID in batch: {$this->last_post_id}" );

            // Loop through the PDF posts and check for duplicates
            foreach ( $pdf_posts as $post ) {
                $post_title = $post->post_title;
                $matching_post_title_id = null;

                if ( $this->dry_run ) {
                    WP_CLI::log( "Checking post ID {$post->ID} with title '{$post_title}' for duplicates." );
                }

                // Check if the post title is already in the unique titles array
                $matching_post_title_id = array_search( $post_title, $this->unique_post_titles, true );
                if ( ! empty( $matching_post_title_id ) ) {
                    $this->handle_duplicate_post( $post, $matching_post_title_id );
                    continue;
                } 

                // Check if the post is a fuzzy duplicate
                // These are post titles that may have a common slug
                // but a unique post title because of -x suffixes which get added upon upload

                // "-1" is a common suffix for duplicates, so we check for it
                if ( str_contains( $post_title, '-1' ) ) {
                    str_replace( '-1', '', $post_title );
                    $matching_post_title_id = array_search( $post_title, $this->unique_post_titles, true );
                    if ( ! empty( $matching_post_title_id ) ) {
                        $this->handle_duplicate_post( $post, $matching_post_title_id );
                        continue;
                    }
                }

                // "-2" is a common suffix for duplicates, so we check for it
                if ( str_contains( $post_title, '-2' ) ) {
                    str_replace( '-2', '', $post_title );
                    $matching_post_title_id = array_search( $post_title, $this->unique_post_titles, true );
                    if ( ! empty( $matching_post_title_id ) ) {
                        $this->handle_duplicate_post( $post, $matching_post_title_id );
                        continue;
                    }
                }

                // "-pdf" is a common suffix for duplicates, so we check for it
                if ( str_contains( $post_title, '-pdf' ) ) {
                    str_replace( '-pdf', '', $post_title );
                    $matching_post_title_id = array_search( $post_title, $this->unique_post_titles, true );
                    if ( ! empty( $matching_post_title_id ) ) {
                        $this->handle_duplicate_post( $post, $matching_post_title_id );
                        continue;
                    }
                }

                // Add the unmodified post title to the unique titles array
                $this->unique_post_titles[$post->ID] = $post->post_title;
            }

            // Save the unique post titles to options
            $this->save_unique_post_titles_to_options();

            // Log the number of duplicate posts found
            WP_CLI::log( "Total duplicate posts found: {$this->total_duplicate_posts}" );

            // Log the number of unique post titles found
            WP_CLI::log( 'Unique PDF posts found: ' . count( $this->unique_post_titles ) );

            // Your deduplication logic here, using $this->dry_run and $this->start_post_id to control actions.
            WP_CLI::success( "PDF media deduplication completed for post ID #{$this->start_post_id} through #{$this->last_post_id}." );
        }

        /**
         * Determine the starting post ID from CLI args or saved option.
         *
         * @param array $assoc_args
         */
        private function determine_start_post_id( $assoc_args ) {
            if ( isset( $assoc_args['start-post-id'] ) ) {
                $this->start_post_id = intval( $assoc_args['start-post-id'] );
                WP_CLI::log( "Resuming from saved post ID: {$this->start_post_id}" );
                return; // If a start post ID is provided, it should always take precedence.
            }

            $saved_start_post_id = get_option( 'one-time-script-pdf-deduplication-start-post-id' );
            if ( $saved_start_post_id ) {
                $this->start_post_id = intval( $saved_start_post_id );
                WP_CLI::log( "Resuming from saved post ID: {$this->start_post_id}" );
            }
        }

        private function get_pdf_posts() {
            global $wpdb;

            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "
                    SELECT * FROM {$wpdb->posts}
                    WHERE post_type = %s
                      AND post_mime_type = %s
                      AND ID > %d
                    ORDER BY ID ASC
                    LIMIT %d
                    ",
                    'attachment',
                    'application/pdf',
                    $this->start_post_id,
                    $this->batch_size
                )
            );

            // Set the last_post_id property to the last post ID in the results, if any
            if ( ! empty( $results ) ) {
                $last_post = end( $results );
                $this->last_post_id = $last_post->ID;
            }

            return $results;
        }

        /**
         * Save the last processed post ID to the wp_options table.
         */
        private function save_last_post_id_to_options() {
            if ( ! is_null( $this->last_post_id ) ) {
                update_option( 'one-time-script-pdf-deduplication-start-post-id', $this->last_post_id );
            }
        }

        /**
         * Save the unique post titles array to the wp_options table.
         */
        private function save_unique_post_titles_to_options() {
            if ( ! empty( $this->unique_post_titles ) ) {
                update_option( 'one-time-script-pdf-deduplication-unique-post-titles', $this->unique_post_titles );
            }
        }

        /**
         * Handle a duplicate post when it is found.
         *
         * @var object $post The post object that is a duplicate.
         * @var int|string $matching_post_title_id The IDs of posts with the same title.
         * @return void
         */
        private function handle_duplicate_post( object $post, int|string $matching_post_title_id ): void {
            $this->total_duplicate_posts++;
            $original_pdf_url = get_attached_file( $matching_post_title_id );
            if ( $this->dry_run ) {
                WP_CLI::log( "Dry run: Duplicate PDF found. Original post ID {$matching_post_title_id} with title '{$this->unique_post_titles[$matching_post_title_id]}'
                    (file can be viewed at {$original_pdf_url}).
                    Duplicate post ID {$post->ID} has title '{$post->post_title}' (file can be viewed at {$post->guid})." );
                return;
            }

            if ( ! $this->dry_run ) {
                // Logic to handle duplicates, e.g., delete or mark as duplicate
                WP_CLI::log(
                    "Duplicate PDF found. Original post ID {$matching_post_title_id} with title '{$this->unique_post_titles[$matching_post_title_id]}'
                    (file can be viewed at {$original_pdf_url}).
                    Duplicate post ID {$post->ID} has title '{$post->post_title}' (file can be viewed at {$post->guid})." );
                WP_CLI::confirm( 'Do you want to delete the duplicate post and PDF file?', 'yes' );
                wp_delete_attachment( $post->ID, true );
                WP_CLI::log( "Deleted duplicate post ID {$post->ID}." );
                return;
            }
        }
    }

    WP_CLI::add_command( 'pdf-media-dedup', 'PDF_Media_Deduplication_Command' );
}
