<?php
/**
 * Plugin Name: JB Deduplication
 * Description: Removes duplicate posts and media files from WordPress.
 * Version: 1.0.0
 * Author: Jess Boctor
 * License: GPL2
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define( 'JB_DEDUP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once JB_DEDUP_PLUGIN_DIR . 'jb-pdf-media-deduplication.php';
require_once JB_DEDUP_PLUGIN_DIR . 'jb-dlp-document-deduplication.php';