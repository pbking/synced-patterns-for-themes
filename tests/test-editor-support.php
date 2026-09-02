<?php
/**
 * Tests for the markup the block editor is served.
 *
 * @package SyncedPatternsForThemes
 */

use TwentyBellows\SyncedPatternsForThemes\Synced_Patterns;

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
	 * A reference to a synced pattern survives the list, content and all.
	 *
	 * Core's resolver would inline it — the design unlocked, the link gone —
	 * so the editor could never show a page pattern's synced sections as the
	 * instances they are. The reference stays; the editor renders it.
	 */
	public function test_patterns_endpoint_keeps_synced_references() {
		$hero = $this->register_pattern( 'test/hero', $this->bound_heading() );
		$this->mark_synced( $hero );

		$page = $this->register_pattern(
			'test/page',
			'<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
			. $this->pattern_block( $hero, array( 'headline' => array( 'content' => 'From the page pattern' ) ) )
			. '</div><!-- /wp:group -->'
		);

		$pattern = $this->find_pattern( $this->request_patterns(), $page );

		$this->assertNotNull( $pattern );

		$blocks = parse_blocks( $pattern['content'] );
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );

		$reference = $blocks[0]['innerBlocks'][0];
		$this->assertSame( 'core/pattern', $reference['blockName'], 'The synced pattern should still be a reference.' );
		$this->assertSame( $hero, $reference['attrs']['slug'] );
		$this->assertSame(
			array( 'headline' => array( 'content' => 'From the page pattern' ) ),
			$reference['attrs']['content'],
			'The reference should keep the content the page gave it.'
		);
		$this->assertStringNotContainsString( 'Default headline', $pattern['content'] );
	}

	/**
	 * Plain references beside a synced one are still inlined, as core would.
	 */
	public function test_patterns_endpoint_inlines_plain_references_beside_synced_ones() {
		$hero = $this->register_pattern( 'test/hero', $this->bound_heading() );
		$this->mark_synced( $hero );

		$plain = $this->register_pattern(
			'test/plain',
			'<!-- wp:paragraph --><p>Inlined by the plugin</p><!-- /wp:paragraph -->'
		);
		$page  = $this->register_pattern( 'test/page', $this->pattern_block( $plain ) . $this->pattern_block( $hero ) );

		$pattern = $this->find_pattern( $this->request_patterns(), $page );

		$this->assertNotNull( $pattern );

		$names = array_column( parse_blocks( $pattern['content'] ), 'blockName' );
		$this->assertContains( 'core/paragraph', $names, 'The plain pattern should be inlined.' );
		$this->assertContains( 'core/pattern', $names, 'The synced pattern should stay a reference.' );
		$this->assertSame( 1, substr_count( $pattern['content'], 'wp:pattern ' ) );
	}

	/**
	 * A template keeps its synced references for the editor as well.
	 */
	public function test_template_keeps_synced_references_for_the_editor() {
		$hero = $this->register_pattern( 'test/hero', $this->bound_heading() );
		$this->mark_synced( $hero );

		$template          = new WP_Block_Template();
		$template->content = $this->pattern_block( $hero, array( 'headline' => array( 'content' => 'From the template' ) ) );

		add_filter( 'synced_patterns_for_themes_is_editor_request', '__return_true' );
		$filtered = apply_filters( 'get_block_template', $template, 'test//index', 'wp_template' );
		remove_filter( 'synced_patterns_for_themes_is_editor_request', '__return_true' );

		$this->assertStringContainsString( 'wp:pattern', $filtered->content );
		$this->assertStringContainsString( 'From the template', $filtered->content );
		$this->assertStringNotContainsString( 'Default headline', $filtered->content );
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
	 * A synced pattern is offered to the inserter as a reference to itself.
	 *
	 * This goes through a real request rather than calling the plugin's own
	 * helpers: the first version of this feature worked when called directly
	 * and did nothing at all over REST, because it was wired to `init`, where
	 * `wp_is_serving_rest_request()` is still false.
	 */
	public function test_synced_pattern_is_offered_as_a_reference() {
		$this->register_pattern( 'test/hero', $this->bound_heading(), array( 'title' => 'Hero' ) );
		$this->mark_synced( 'test/hero' );

		$patterns  = $this->request_patterns();
		$companion = $this->find_pattern( $patterns, Synced_Patterns::get_inserter_slug( 'test/hero' ) );
		$design    = $this->find_pattern( $patterns, 'test/hero' );

		$this->assertNotNull( $companion, 'The inserter should be offered a reference.' );
		$this->assertSame(
			Synced_Patterns::get_reference_markup( 'test/hero' ),
			$companion['content'],
			'Inserting it should link the pattern rather than copy it.'
		);
		$this->assertSame( 'Hero', $companion['title'] );
		$this->assertTrue( $companion['inserter'] );

		// The pattern itself steps aside so the inserter offers it only once.
		$this->assertNotNull( $design );
		$this->assertFalse( $design['inserter'] );

		/*
		 * It still carries its blocks and their bindings, which is what the
		 * editor renders the instance from.
		 */
		$this->assertStringContainsString( 'core/pattern-overrides', $design['content'] );
		$this->assertStringContainsString( 'Default headline', $design['content'] );
	}

	/**
	 * A pattern already kept out of the inserter gets no companion.
	 */
	public function test_pattern_hidden_from_the_inserter_gets_no_companion() {
		$this->register_pattern(
			'test/hero',
			$this->bound_heading(),
			array( 'inserter' => false )
		);
		$this->mark_synced( 'test/hero' );

		$this->assertNull(
			$this->find_pattern(
				$this->request_patterns(),
				Synced_Patterns::get_inserter_slug( 'test/hero' )
			)
		);
	}

	/**
	 * A pattern that is not synced is offered exactly as core offers it.
	 */
	public function test_unsynced_pattern_is_untouched() {
		$this->register_pattern( 'test/plain', $this->bound_heading() );

		$patterns = $this->request_patterns();
		$plain    = $this->find_pattern( $patterns, 'test/plain' );

		$this->assertNotNull( $plain );
		$this->assertNotFalse( $plain['inserter'] ?? true );
		$this->assertNull(
			$this->find_pattern( $patterns, Synced_Patterns::get_inserter_slug( 'test/plain' ) )
		);
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
