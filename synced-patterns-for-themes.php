<?php
/**
 * Plugin Name:       Synced Patterns for Themes
 * Plugin URI:        https://github.com/Twenty-Bellows/synced-patterns-for-themes
 * Description:       Lets block markup supply the content for a pattern, so one pattern can carry the design and another can carry the words.
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Version:           2.1.0
 * Author:            Twenty Bellows
 * Author URI:        https://twentybellows.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       synced-patterns-for-themes
 *
 * @package SyncedPatternsForThemes
 */

namespace TwentyBellows\SyncedPatternsForThemes;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-inner-html-processor.php';
require_once __DIR__ . '/includes/class-block-markup.php';
require_once __DIR__ . '/includes/class-pattern-resolver.php';
require_once __DIR__ . '/includes/class-pattern-block.php';
require_once __DIR__ . '/includes/class-synced-patterns.php';
require_once __DIR__ . '/includes/class-editor-support.php';
require_once __DIR__ . '/includes/class-plugin.php';

Plugin::boot( __FILE__ );
