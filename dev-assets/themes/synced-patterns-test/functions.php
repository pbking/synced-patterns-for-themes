<?php
/**
 * Theme functions.
 *
 * @package SyncedPatternsTest
 */

/**
 * Removes core's block patterns, so the inserter only shows this theme's.
 */
add_action(
	'after_setup_theme',
	static function () {
		remove_theme_support( 'core-block-patterns' );
	}
);
