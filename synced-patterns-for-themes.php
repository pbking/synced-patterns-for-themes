<?php
/**
 * @wordpress-plugin
 * Plugin Name: Synced Patterns for Themes
 * Description: Empower Themes to provide Synced Patterns to an environment
 * Version: 1.0
 * Author: pbking
 * Author URI: https://pbking.com
 * License: GPL2
 * Text Domain: synced-patterns-for-themes 
 */

 // TODO: Provide a mechanism for a user to save a user created synced pattern to the theme.
 // TODO: Provide a mechanism for a user to save a user created unsynced pattern to the theme.
 // TODO: Provide a mechanism for a user to make changes to an unsynced pattern.
 // TODO: Provide a mechanism for a user to clear user changes to an unsynced or synced pattern.

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! function_exists( 'post_exists' ) ) {
    require_once ABSPATH . 'wp-admin/includes/post.php';
}

/**
 * Require the class that will handle the synced patterns
 */
require plugin_dir_path( __FILE__ ) . 'includes/synced-patterns-for-themes-class.php';

/**
 * Instantiate the class that will handle the synced patterns
 */
$synced_patterns_for_themes = new Synced_Patterns_For_Themes();