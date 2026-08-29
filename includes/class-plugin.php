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

		// A theme switch changes which pattern files the synced lookup reads.
		add_action( 'switch_theme', array( Synced_Patterns::class, 'flush' ) );
	}
}
