<?php
/**
 * Plugin bootstrap.
 *
 * @package SyncedPatternsForThemes
 */

namespace TwentyBellows\SyncedPatternsForThemes;

/**
 * Wires the plugin's pieces up to WordPress.
 */
final class Plugin {

	/**
	 * Absolute path to the main plugin file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Constructor.
	 *
	 * @param string $file Absolute path to the main plugin file.
	 */
	public function __construct( string $file ) {
		$this->file = $file;
	}

	/**
	 * Creates the plugin and registers its hooks.
	 *
	 * @param string $file Absolute path to the main plugin file.
	 * @return Plugin The booted plugin.
	 */
	public static function boot( string $file ): Plugin {
		$plugin = new self( $file );
		$plugin->register();

		return $plugin;
	}

	/**
	 * Registers every hook the plugin needs.
	 *
	 * @return void
	 */
	public function register(): void {
		( new Pattern_Block() )->register();
		( new Editor_Support( $this->file ) )->register();

		/*
		 * Only the editor needs the companion patterns that offer a synced
		 * pattern as a reference. Saved markup names the pattern itself, which
		 * the front end renders without any of this.
		 */
		add_action( 'init', array( $this, 'register_synced_patterns' ), 20 );
	}

	/**
	 * Registers the companion patterns that insert a reference.
	 *
	 * @return void
	 */
	public function register_synced_patterns(): void {
		if ( ! Editor_Support::is_editor_request() && ! is_admin() ) {
			return;
		}

		Synced_Patterns::register_inserter_patterns();
	}
}
