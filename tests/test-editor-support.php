<?php
/**
 * Tests for the markup the block editor is served.
 *
 * @package SyncedPatternsForThemes
 */

/**
 * The markup the block editor is served.
 *
 * @covers \TwentyBellows\SyncedPatternsForThemes\Editor_Support
 */
class Test_Editor_Support extends Pattern_Test_Case {

	/**
	 * Sets the test up.
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	/**
	 * Requests the block editor's list of patterns.
	 *
	 * @return array[] The patterns in the response.
	 */
	private function request_patterns(): array {
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/block-patterns/patterns' ) );

		$this->assertSame( 200, $response->get_status() );

		return $response->get_data();
	}

	/**
	 * Finds one pattern in a REST response.
	 *
	 * @param array[] $patterns Patterns from the response.
	 * @param string  $name     Pattern name.
	 * @return array|null The pattern.
	 */
	private function find_pattern( array $patterns, string $name ): ?array {
		foreach ( $patterns as $pattern ) {
			if ( isset( $pattern['name'] ) && $name === $pattern['name'] ) {
				return $pattern;
			}
		}

		return null;
	}

	/**
	 * The patterns the editor lists have their content composed into them.
	 */
	public function test_patterns_endpoint_composes_content() {
		$hero = $this->register_pattern( 'test/hero', $this->bound_heading() );
		$page = $this->register_pattern(
			'test/page',
			$this->pattern_block( $hero, array( 'headline' => array( 'content' => 'From the page pattern' ) ) )
		);

		$pattern = $this->find_pattern( $this->request_patterns(), $page );

		$this->assertNotNull( $pattern, 'The page pattern should be in the response.' );
		$this->assertStringContainsString( 'From the page pattern', $pattern['content'] );
		$this->assertStringNotContainsString( 'Default headline', $pattern['content'] );
		$this->assertStringNotContainsString( 'core/pattern-overrides', $pattern['content'] );
	}

	/**
	 * Patterns that use no content are left exactly as core prepares them.
	 */
	public function test_patterns_endpoint_leaves_other_patterns_alone() {
		$plain = $this->register_pattern(
			'test/plain',
			'<!-- wp:paragraph --><p>Nothing to compose</p><!-- /wp:paragraph -->'
		);

		$pattern = $this->find_pattern( $this->request_patterns(), $plain );

		$this->assertNotNull( $pattern );
		$this->assertStringContainsString( 'Nothing to compose', $pattern['content'] );
	}

	/**
	 * Template content is composed for the editor.
	 */
	public function test_template_content_is_composed_for_the_editor() {
		$hero = $this->register_pattern( 'test/hero', $this->bound_heading() );

		$template          = new WP_Block_Template();
		$template->content = $this->pattern_block( $hero, array( 'headline' => array( 'content' => 'From the template' ) ) );

		add_filter( 'synced_patterns_for_themes_is_editor_request', '__return_true' );
		$filtered = apply_filters( 'get_block_template', $template, 'test//index', 'wp_template' );
		remove_filter( 'synced_patterns_for_themes_is_editor_request', '__return_true' );

		$this->assertStringContainsString( 'From the template', $filtered->content );
		$this->assertStringNotContainsString( 'wp:pattern', $filtered->content );
	}

	/**
	 * Template content is left alone outside the editor, where the pattern
	 * block renders the content itself.
	 */
	public function test_template_content_is_untouched_on_the_front_end() {
		$hero   = $this->register_pattern( 'test/hero', $this->bound_heading() );
		$markup = $this->pattern_block( $hero, array( 'headline' => array( 'content' => 'From the template' ) ) );

		$template          = new WP_Block_Template();
		$template->content = $markup;

		add_filter( 'synced_patterns_for_themes_is_editor_request', '__return_false' );
		$filtered = apply_filters( 'get_block_template', $template, 'test//index', 'wp_template' );
		remove_filter( 'synced_patterns_for_themes_is_editor_request', '__return_false' );

		$this->assertSame( $markup, $filtered->content );
		$this->assertStringContainsString( 'From the template', do_blocks( $filtered->content ) );
	}

	/**
	 * A list of templates is composed the same way a single one is.
	 */
	public function test_template_lists_are_composed() {
		$hero = $this->register_pattern( 'test/hero', $this->bound_heading() );

		$template          = new WP_Block_Template();
		$template->content = $this->pattern_block( $hero, array( 'headline' => array( 'content' => 'In a list' ) ) );

		add_filter( 'synced_patterns_for_themes_is_editor_request', '__return_true' );
		$filtered = apply_filters( 'get_block_templates', array( $template ), array(), 'wp_template' );
		remove_filter( 'synced_patterns_for_themes_is_editor_request', '__return_true' );

		$this->assertStringContainsString( 'In a list', $filtered[0]->content );
	}

	/**
	 * Other REST responses pass through untouched.
	 */
	public function test_other_rest_routes_are_untouched() {
		$post_id  = self::factory()->post->create( array( 'post_title' => 'A post' ) );
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'A post', $response->get_data()['title']['rendered'] );
	}
}
