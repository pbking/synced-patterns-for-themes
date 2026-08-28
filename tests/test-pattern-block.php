<?php
/**
 * Tests for rendering a pattern block that carries content.
 *
 * @package SyncedPatternsForThemes
 */

/**
 * Rendering a pattern block that carries content.
 *
 * @covers \TwentyBellows\SyncedPatternsForThemes\Pattern_Block
 */
class Test_Pattern_Block extends Pattern_Test_Case {

	/**
	 * The block type carries the attribute and the context it provides.
	 */
	public function test_pattern_block_type_accepts_content() {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/pattern' );

		$this->assertArrayHasKey( 'content', $block_type->attributes );
		$this->assertSame( 'object', $block_type->attributes['content']['type'] );
		$this->assertSame(
			array( 'pattern/overrides' => 'content' ),
			$block_type->provides_context
		);
	}

	/**
	 * Content on the pattern block reaches the pattern's bound blocks.
	 */
	public function test_content_fills_a_slot() {
		$slug = $this->register_pattern( 'test/hero', $this->bound_heading() );

		$rendered = do_blocks(
			$this->pattern_block( $slug, array( 'headline' => array( 'content' => 'Hello world' ) ) )
		);

		$this->assertStringContainsString( 'Hello world', $rendered );
		$this->assertStringNotContainsString( 'Default headline', $rendered );
	}

	/**
	 * A pattern block with no content still renders the pattern's defaults.
	 */
	public function test_pattern_without_content_renders_defaults() {
		$slug = $this->register_pattern( 'test/hero', $this->bound_heading() );

		$rendered = do_blocks( $this->pattern_block( $slug ) );

		$this->assertStringContainsString( 'Default headline', $rendered );
	}

	/**
	 * A slot the content says nothing about keeps the pattern's default.
	 */
	public function test_unfilled_slot_keeps_its_default() {
		$slug = $this->register_pattern(
			'test/hero',
			$this->bound_heading() . "\n"
			. '<!-- wp:paragraph {"metadata":{"name":"lede","bindings":{"content":{"source":"core/pattern-overrides"}}}} -->' . "\n"
			. '<p>Default lede</p>' . "\n"
			. '<!-- /wp:paragraph -->'
		);

		$rendered = do_blocks(
			$this->pattern_block( $slug, array( 'headline' => array( 'content' => 'Hello world' ) ) )
		);

		$this->assertStringContainsString( 'Hello world', $rendered );
		$this->assertStringContainsString( 'Default lede', $rendered );
	}

	/**
	 * Content reaches slots nested inside the pattern's layout blocks.
	 */
	public function test_content_reaches_nested_blocks() {
		$slug = $this->register_pattern(
			'test/hero',
			'<!-- wp:group --><div class="wp-block-group">' . $this->bound_heading() . '</div><!-- /wp:group -->'
		);

		$rendered = do_blocks(
			$this->pattern_block( $slug, array( 'headline' => array( 'content' => 'Nested value' ) ) )
		);

		$this->assertStringContainsString( 'Nested value', $rendered );
	}

	/**
	 * Content passes through a pattern that includes another pattern.
	 */
	public function test_content_passes_through_a_nested_pattern() {
		$inner = $this->register_pattern( 'test/inner', $this->bound_heading() );
		$outer = $this->register_pattern( 'test/outer', $this->pattern_block( $inner ) );

		$rendered = do_blocks(
			$this->pattern_block( $outer, array( 'headline' => array( 'content' => 'Passed down' ) ) )
		);

		$this->assertStringContainsString( 'Passed down', $rendered );
	}

	/**
	 * A pattern block pointing at an unknown pattern renders nothing.
	 */
	public function test_unknown_pattern_renders_nothing() {
		$rendered = do_blocks(
			$this->pattern_block( 'test/not-registered', array( 'headline' => array( 'content' => 'Hello' ) ) )
		);

		$this->assertSame( '', trim( $rendered ) );
	}

	/**
	 * A pattern that includes itself stops instead of recursing.
	 */
	public function test_self_referencing_pattern_halts() {
		$slug = 'test/recursive';
		$this->register_pattern( $slug, $this->pattern_block( $slug, array( 'headline' => array( 'content' => 'x' ) ) ) );

		$rendered = do_blocks( $this->pattern_block( $slug, array( 'headline' => array( 'content' => 'x' ) ) ) );

		if ( WP_DEBUG && WP_DEBUG_DISPLAY ) {
			$this->assertStringContainsString( 'block rendering halted', $rendered );
		} else {
			$this->assertSame( '', trim( $rendered ) );
		}
	}

	/**
	 * The same pattern can appear twice with different content.
	 */
	public function test_one_pattern_can_be_used_twice_with_different_content() {
		$slug = $this->register_pattern( 'test/hero', $this->bound_heading() );

		$rendered = do_blocks(
			$this->pattern_block( $slug, array( 'headline' => array( 'content' => 'First headline' ) ) )
			. $this->pattern_block( $slug, array( 'headline' => array( 'content' => 'Second headline' ) ) )
		);

		$this->assertStringContainsString( 'First headline', $rendered );
		$this->assertStringContainsString( 'Second headline', $rendered );
	}

	/**
	 * A pattern block nested inside another block still gets its content.
	 */
	public function test_pattern_inside_another_block_gets_its_content() {
		$slug = $this->register_pattern( 'test/hero', $this->bound_heading() );

		$rendered = do_blocks(
			'<!-- wp:group --><div class="wp-block-group">'
			. $this->pattern_block( $slug, array( 'headline' => array( 'content' => 'Inside a group' ) ) )
			. '</div><!-- /wp:group -->'
		);

		$this->assertStringContainsString( 'Inside a group', $rendered );
		$this->assertStringContainsString( 'wp-block-group', $rendered );
	}

	/**
	 * A `__default` binding fills every attribute the block supports.
	 */
	public function test_default_binding_fills_supported_attributes() {
		$slug = $this->register_pattern(
			'test/image',
			'<!-- wp:image {"metadata":{"name":"photo","bindings":{"__default":{"source":"core/pattern-overrides"}}}} -->' . "\n"
			. '<figure class="wp-block-image"><img src="https://example.org/default.png" alt="Default alt"/></figure>' . "\n"
			. '<!-- /wp:image -->'
		);

		$rendered = do_blocks(
			$this->pattern_block(
				$slug,
				array(
					'photo' => array(
						'url' => 'https://example.org/filled.png',
						'alt' => 'Filled alt',
					),
				)
			)
		);

		$this->assertStringContainsString( 'https://example.org/filled.png', $rendered );
		$this->assertStringContainsString( 'Filled alt', $rendered );
	}

	/**
	 * Patterns still render the way core renders them when nothing overrides.
	 */
	public function test_plain_pattern_renders_its_blocks() {
		$slug = $this->register_pattern(
			'test/plain',
			'<!-- wp:paragraph --><p>Just a paragraph</p><!-- /wp:paragraph -->'
		);

		$this->assertStringContainsString( 'Just a paragraph', do_blocks( $this->pattern_block( $slug ) ) );
	}
}
