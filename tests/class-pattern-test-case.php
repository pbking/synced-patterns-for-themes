<?php
/**
 * Shared setup for the plugin's tests.
 *
 * @package SyncedPatternsForThemes
 */

/**
 * Registers patterns for a test and cleans them up afterwards.
 */
abstract class Pattern_Test_Case extends WP_UnitTestCase {

	/**
	 * Slugs of the patterns registered by the running test.
	 *
	 * @var string[]
	 */
	private $registered = array();

	/**
	 * Sets the test up.
	 */
	public function set_up() {
		parent::set_up();

		add_filter( 'should_load_remote_block_patterns', '__return_false' );
	}

	/**
	 * Tears the test down.
	 */
	public function tear_down() {
		$registry = WP_Block_Patterns_Registry::get_instance();

		foreach ( $this->registered as $slug ) {
			if ( $registry->is_registered( $slug ) ) {
				$registry->unregister( $slug );
			}
		}

		$this->registered = array();

		\TwentyBellows\SyncedPatternsForThemes\Synced_Patterns::flush();

		parent::tear_down();
	}

	/**
	 * Registers a pattern for the duration of the test.
	 *
	 * @param string $slug       Pattern slug, including namespace.
	 * @param string $content    Pattern content.
	 * @param array  $properties Additional pattern properties.
	 * @return string The slug.
	 */
	protected function register_pattern( string $slug, string $content, array $properties = array() ): string {
		register_block_pattern(
			$slug,
			array_merge(
				array(
					'title'   => $slug,
					'content' => $content,
				),
				$properties
			)
		);

		$this->registered[] = $slug;

		return $slug;
	}

	/**
	 * A heading with one content slot named "headline".
	 *
	 * @param string $text The heading's default text.
	 * @return string Block markup.
	 */
	protected function bound_heading( string $text = 'Default headline' ): string {
		return '<!-- wp:heading {"metadata":{"name":"headline","bindings":{"content":{"source":"core/pattern-overrides"}}}} -->' . "\n"
			. '<h2 class="wp-block-heading">' . $text . '</h2>' . "\n"
			. '<!-- /wp:heading -->';
	}

	/**
	 * Serializes a `core/pattern` block that carries content.
	 *
	 * @param string $slug    Pattern slug.
	 * @param array  $content Content, keyed by slot name and then attribute name.
	 * @return string Block markup.
	 */
	protected function pattern_block( string $slug, array $content = array() ): string {
		$attributes = array( 'slug' => $slug );

		if ( ! empty( $content ) ) {
			$attributes['content'] = $content;
		}

		return '<!-- wp:pattern ' . wp_json_encode( $attributes ) . ' /-->';
	}
}
