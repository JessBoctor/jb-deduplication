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

use WP_CLI;
// File: pdf-media-deduplication.php

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
         * The number of pdf posts returned by the last query.
         */
        private $pdf_posts_count = 0;

        /**
         * Holds the last post ID returned in the batch.
         *
         * @var int|null
         */
        private $last_post_id = null;

        /**
         * Constructor.
         */
        public function __construct( $assoc_args ) {
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
            $this->pdf_posts_count = count( $pdf_posts );
            WP_CLI::log( "Found {$this->pdf_posts_count} PDF posts to process." );
            $this->save_last_post_id_to_options();
            WP_CLI::log( "Last post ID in batch: {$this->last_post_id}" );

            // Loop through the PDF posts and check for duplicates

            // Your deduplication logic here, using $this->dry_run and $this->start_post_id to control actions.
            WP_CLI::success( 'PDF media deduplication completed.' );
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
    }

    WP_CLI::add_command( 'pdf-media-dedup', 'PDF_Media_Deduplication_Command' );
}
