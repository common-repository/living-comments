<?php
/*
 * Plugin Name:       Living Comments – AI Comments & Replies
 * Plugin URI:        https://www.livingcomments.com/
 * Description:       Generate AI comments and replies on WordPress automatically.
 * Version:           1.1.1
 * Requires at least: 3.8
 * Requires PHP:      5.2
 * Author:            Living Comments
 * Author URI:        https://www.livingcomments.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       living-comments
 * Domain Path:       /languages
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// WordPress version check.
if ( ! version_compare( get_bloginfo( 'version' ), '3.8', '>=' ) ) {
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    deactivate_plugins( plugin_basename( __FILE__ ) ); // Deactivate the plugin
    wp_die( 'This plugin requires WordPress version 3.8 or higher.' );
}

// Define the main plugin file.
define( 'LIVCOM_MAIN_FILE', __FILE__ );

/**
 * Initializes the WordPress Filesystem API.
 */
function livcom_init_filesystem() {
    require_once( ABSPATH . 'wp-admin/includes/file.php' );

    WP_Filesystem();
    global $wp_filesystem;

    // Check if the Filesystem API is accessible
    if ( ! $wp_filesystem ) {
        wp_die( 'Filesystem API initialization failed.' );
    }
}

// Initialize the WordPress Filesystem API.
livcom_init_filesystem();

/**
 * Loads plugin dependencies.
 */
function livcom_dependencies() {
    $base_path = plugin_dir_path( __FILE__ ) . 'src/';

    $dependencies = array(
        'init',
        'enqueue',
        'subscription-handling',
        'user-stats',
        'random-post',
        'comment-handling',
        'user-management',
        'cronjob-handling',
        'plugin-controller',
        'plugin-settings',
        'new-user',
    );

    foreach ( $dependencies as $dependency ) {
        require_once $base_path . $dependency . '.php';
    }
}

// Load all the dependencies.
livcom_dependencies();

// Hook the livcom_init_hooks function to the plugins_loaded action.
add_action( 'plugins_loaded', 'livcom_init_hooks' );