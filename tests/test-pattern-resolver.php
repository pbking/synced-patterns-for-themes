<?php
/**
 * Tests for composing patterns into blocks for the editor.
 *
 * @package SyncedPatternsForThemes
 */

use TwentyBellows\SyncedPatternsForThemes\Pattern_Resolver;

/**
 * Composing patterns and their content into blocks.
 *
 * @covers \TwentyBellows\SyncedPatternsForThemes\Pattern_Resolver
 * @covers \TwentyBellows\SyncedPatternsForThemes\Block_Markup
 * @covers \TwentyBellows\SyncedPatternsForThemes\Inner_HTML_Processor
 */
class Test_Pattern_Resolver extends Pattern_Test_Case {

	/**
	 * Content is written into the pattern's markup.
	 */
	public function test_content_is_written_into_the_markup() {
		$slug = $this->register_pattern( 'test/hero', $this->bound_heading() );

		$resolved = Pattern_Resolver::resolve(
			$this->pattern_block( $slug, array( 'headline' => array( 'content' => 'Hello world' ) ) )
		);

		$this->assertStringContainsString( '<h2 class="wp-block-heading">Hello world</h2>', $resolved );
		$this->assertStringNotContainsString( 'Default headline', $resolved );
	}

	/**
	 * The bindings that asked for content are removed once it is written.
	 */
	public function test_bindings_are_removed() {
		$slug = $this->register_pattern( 'test/hero', $this->bound_heading() );

		$resolved = Pattern_Resolver::resolve(
			$this->pattern_block( $slug, array( 'headline' => array( 'content' => 'Hello world' ) ) )
		);

		$this->assertStringNotContainsString( 'core/pattern-overrides', $resolved );
		$this->assertStringContainsString( '"name":"headline"', $resolved );
	}

	/**
	 * A slot nothing was supplied for keeps its default and loses its binding.
	 *
	 * Left in place the binding would resolve to nothing outside a pattern, and
	 * would make the block read-only in the editor.
	 */
	public function test_unfilled_slot_keeps_its_default_without_its_binding() {
		$slug = $this->register_pattern( 'test/hero', $this->bound_heading() );

		$resolved = Pattern_Resolver::resolve(
			$this->pattern_block( $slug, array( 'somewhere-else' => array( 'content' => 'Hello' ) ) )
		);

		$this->assertStringContainsString( 'Default headline', $resolved );
		$this->assertStringNotContainsString( 'core/pattern-overrides', $resolved );
	}

	/**
	 * Markup with no pattern blocks in it comes back untouched.
	 */
	public function test_markup_without_patterns_is_untouched() {
		$markup = '<!-- wp:paragraph --><p>Nothing to do</p><!-- /wp:paragraph -->';

		$this->assertSame( $markup, Pattern_Resolver::resolve( $markup ) );
	}

	/**
	 * A pattern block with no content is left for core's own resolver.
	 */
	public function test_pattern_without_content_is_left_alone() {
		$slug   = $this->register_pattern( 'test/hero', $this->bound_heading() );
		$markup = $this->pattern_block( $slug );

		$this->assertSame( $markup, Pattern_Resolver::resolve( $markup ) );
	}

	/**
	 * Values are written into HTML attributes when that is where they live.
	 */
	public function test_html_attributes_are_written() {
		$slug = $this->register_pattern(
			'test/image',
			'<!-- wp:image {"metadata":{"name":"photo","bindings":{"url":{"source":"core/pattern-overrides"},"alt":{"source":"core/pattern-overrides"}}}} -->' . "\n"
			. '<figure class="wp-block-image"><img src="https://example.org/default.png" alt="Default alt"/></figure>' . "\n"
			. '<!-- /wp:image -->'
		);

		$resolved = Pattern_Resolver::resolve(
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

		$this->assertStringContainsString( 'src="https://example.org/filled.png"', $resolved );
		$this->assertStringContainsString( 'alt="Filled alt"', $resolved );
	}

	/**
	 * A button's text and link are both filled, inside its wrapper block.
	 */
	public function test_button_text_and_link_are_written() {
		$slug = $this->register_pattern(
			'test/cta',
			'<!-- wp:buttons --><div class="wp-block-buttons">'
			. '<!-- wp:button {"metadata":{"name":"cta","bindings":{"text":{"source":"core/pattern-overrides"},"url":{"source":"core/pattern-overrides"}}}} -->'
			. '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.org/old">Old label</a></div>'
			. '<!-- /wp:button --></div><!-- /wp:buttons -->'
		);

		$resolved = Pattern_Resolver::resolve(
			$this->pattern_block(
				$slug,
				array(
					'cta' => array(
						'text' => 'New label',
						'url'  => 'https://example.org/new',
					),
				)
			)
		);

		$this->assertStringContainsString( 'New label', $resolved );
		$this->assertStringContainsString( 'https://example.org/new', $resolved );
		$this->assertStringNotContainsString( 'Old label', $resolved );

		// The wrapper block survived the rewrite.
		$this->assertStringContainsString( '<div class="wp-block-buttons">', $resolved );
		$this->assertStringContainsString( '<!-- /wp:buttons -->', $resolved );
	}

	/**
	 * A `__default` binding fills every attribute the block supports.
	 */
	public function test_default_binding_fills_supported_attributes() {
		$slug = $this->register_pattern(
			'test/hero',
			'<!-- wp:paragraph {"metadata":{"name":"lede","bindings":{"__default":{"source":"core/pattern-overrides"}}}} -->' . "\n"
			. '<p>Default lede</p>' . "\n"
			. '<!-- /wp:paragraph -->'
		);

		$resolved = Pattern_Resolver::resolve(
			$this->pattern_block( $slug, array( 'lede' => array( 'content' => 'Filled lede' ) ) )
		);

		$this->assertStringContainsString( '<p>Filled lede</p>', $resolved );
		$this->assertStringNotContainsString( 'core/pattern-overrides', $resolved );
	}

	/**
	 * A pattern standing inside another block keeps that block's structure.
	 */
	public function test_pattern_inside_a_block_is_spliced_correctly() {
		$slug = $this->register_pattern(
			'test/pair',
			'<!-- wp:paragraph --><p>One</p><!-- /wp:paragraph -->' . "\n"
			. '<!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->' . "\n"
			. $this->bound_heading()
		);

		$resolved = Pattern_Resolver::resolve(
			'<!-- wp:group --><div class="wp-block-group">'
			. $this->pattern_block( $slug, array( 'headline' => array( 'content' => 'Three' ) ) )
			. '</div><!-- /wp:group -->'
		);

		$this->assertStringContainsString( '<p>One</p>', $resolved );
		$this->assertStringContainsString( '<p>Two</p>', $resolved );
		$this->assertStringContainsString( 'Three', $resolved );

		// All three blocks landed inside the group, and it still closes.
		$group = substr(
			$resolved,
			strpos( $resolved, '<div class="wp-block-group">' ),
			strpos( $resolved, '</div>' )
		);
		$this->assertStringContainsString( '<p>One</p>', $group );
		$this->assertStringContainsString( 'Three', $group );
		$this->assertStringContainsString( '<!-- /wp:group -->', $resolved );

		// The parse survives a round trip, which it would not if the inner
		// content markers had fallen out of step with the inner blocks.
		$blocks = parse_blocks( $resolved );
		$this->assertCount( 1, array_filter( $blocks, static fn( $block ) => 'core/group' === $block['blockName'] ) );
		$this->assertCount( 3, $blocks[0]['innerBlocks'] );
	}

	/**
	 * Content survives a pattern that is reached through another pattern.
	 */
	public function test_content_survives_an_intermediate_pattern() {
		$inner  = $this->register_pattern( 'test/inner', $this->bound_heading() );
		$middle = $this->register_pattern(
			'test/middle',
			$this->pattern_block( $inner, array( 'headline' => array( 'content' => 'From the middle' ) ) )
		);
		$outer  = $this->register_pattern( 'test/outer', $this->pattern_block( $middle ) );

		$resolved = Pattern_Resolver::resolve( $this->pattern_block( $outer ) );

		$this->assertStringContainsString( 'From the middle', $resolved );
		$this->assertStringNotContainsString( 'Default headline', $resolved );
		$this->assertStringNotContainsString( 'wp:pattern', $resolved );
	}

	/**
	 * A reference to a synced pattern is kept as written, content included.
	 */
	public function test_synced_reference_is_kept() {
		$hero = $this->register_pattern( 'test/hero', $this->bound_heading() );
		$this->mark_synced( $hero );

		$markup = $this->pattern_block( $hero, array( 'headline' => array( 'content' => 'Stays a reference' ) ) );

		$this->assertSame( $markup, Pattern_Resolver::resolve( $markup ) );
	}

	/**
	 * A synced reference reached through an unsynced pattern is kept too,
	 * while the pattern around it is composed.
	 */
	public function test_synced_reference_inside_a_composed_pattern_is_kept() {
		$hero = $this->register_pattern( 'test/hero', $this->bound_heading() );
		$this->mark_synced( $hero );

		$page = $this->register_pattern(
			'test/page',
			$this->bound_heading( 'Page title' )
			. $this->pattern_block( $hero, array( 'headline' => array( 'content' => 'Section' ) ) )
		);

		$resolved = Pattern_Resolver::resolve(
			$this->pattern_block( $page, array( 'headline' => array( 'content' => 'Composed title' ) ) )
		);

		$blocks = array_values( array_filter( parse_blocks( $resolved ), static fn( $block ) => null !== $block['blockName'] ) );

		$this->assertSame( 'core/heading', $blocks[0]['blockName'] );
		$this->assertStringContainsString( 'Composed title', $resolved );
		$this->assertSame( 'core/pattern', $blocks[1]['blockName'] );
		$this->assertSame( $hero, $blocks[1]['attrs']['slug'] );
		$this->assertSame( array( 'headline' => array( 'content' => 'Section' ) ), $blocks[1]['attrs']['content'] );
	}

	/**
	 * `compose()` inlines plain references the way core does, and only those.
	 */
	public function test_compose_inlines_plain_references_and_keeps_synced_ones() {
		$hero  = $this->register_pattern( 'test/hero', $this->bound_heading(), array( 'title' => 'Hero' ) );
		$plain = $this->register_pattern(
			'test/plain',
			'<!-- wp:paragraph --><p>Plain</p><!-- /wp:paragraph -->',
			array( 'title' => 'Plain' )
		);
		$this->mark_synced( $hero );

		$composed = Pattern_Resolver::compose( $this->pattern_block( $plain ) . $this->pattern_block( $hero ) );

		$blocks = array_values( array_filter( parse_blocks( $composed ), static fn( $block ) => null !== $block['blockName'] ) );

		$this->assertCount( 2, $blocks );
		$this->assertSame( 'core/paragraph', $blocks[0]['blockName'] );
		$this->assertSame( $plain, $blocks[0]['attrs']['metadata']['patternName'], 'An inlined pattern reads as an instance, as core marks it.' );
		$this->assertSame( 'Plain', $blocks[0]['attrs']['metadata']['name'] );
		$this->assertSame( 'core/pattern', $blocks[1]['blockName'] );
		$this->assertSame( $hero, $blocks[1]['attrs']['slug'] );
	}

	/**
	 * `compose()` does not change what `resolve()` does afterwards: a plain
	 * reference is still left for core there.
	 */
	public function test_resolve_still_leaves_plain_references_after_compose() {
		$plain = $this->register_pattern( 'test/plain', '<!-- wp:paragraph --><p>Plain</p><!-- /wp:paragraph -->' );

		Pattern_Resolver::compose( $this->pattern_block( $plain ) );

		$markup = $this->pattern_block( $plain );
		$this->assertSame( $markup, Pattern_Resolver::resolve( $markup ) );
	}

	/**
	 * A pattern that includes itself is dropped rather than recursing.
	 */
	public function test_self_referencing_pattern_is_dropped() {
		$slug = 'test/recursive';
		$this->register_pattern(
			$slug,
			'<!-- wp:paragraph --><p>Once</p><!-- /wp:paragraph -->'
			. $this->pattern_block( $slug, array( 'headline' => array( 'content' => 'x' ) ) )
		);

		$resolved = Pattern_Resolver::resolve(
			$this->pattern_block( $slug, array( 'headline' => array( 'content' => 'x' ) ) )
		);

		$this->assertSame( 1, substr_count( $resolved, '<p>Once</p>' ) );
	}

	/**
	 * Values are escaped on their way into the markup.
	 */
	public function test_values_are_escaped() {
		$slug = $this->register_pattern( 'test/hero', $this->bound_heading() );

		$resolved = Pattern_Resolver::resolve(
			$this->pattern_block(
				$slug,
				array( 'headline' => array( 'content' => 'Safe<script>alert(1)</script>' ) )
			)
		);

		$this->assertStringContainsString( 'Safe', $resolved );
		$this->assertStringNotContainsString( '<script>', $resolved );
	}

	/**
	 * A value written into the block comment, for an attribute with no HTML source.
	 */
	public function test_sourceless_attributes_go_in_the_block_comment() {
		$slug = $this->register_pattern(
			'test/image',
			'<!-- wp:image {"metadata":{"name":"photo","bindings":{"id":{"source":"core/pattern-overrides"}}}} -->' . "\n"
			. '<figure class="wp-block-image"><img src="https://example.org/default.png" alt=""/></figure>' . "\n"
			. '<!-- /wp:image -->'
		);

		$resolved = Pattern_Resolver::resolve(
			$this->pattern_block( $slug, array( 'photo' => array( 'id' => 42 ) ) )
		);

		$this->assertStringContainsString( '"id":42', $resolved );
	}

	/**
	 * A slot bound to an attribute the block does not have writes nothing.
	 */
	public function test_unknown_attributes_are_not_written() {
		$slug = $this->register_pattern(
			'test/hero',
			'<!-- wp:heading {"metadata":{"name":"headline","bindings":{"contnet":{"source":"core/pattern-overrides"}}}} -->' . "\n"
			. '<h2 class="wp-block-heading">Default headline</h2>' . "\n"
			. '<!-- /wp:heading -->'
		);

		$resolved = Pattern_Resolver::resolve(
			$this->pattern_block( $slug, array( 'headline' => array( 'contnet' => 'Typo' ) ) )
		);

		$this->assertStringContainsString( 'Default headline', $resolved );
		$this->assertStringNotContainsString( 'Typo', $resolved );
	}

	/**
	 * A value for an attribute with no binding is ignored.
	 */
	public function test_unbound_attributes_are_ignored() {
		$slug = $this->register_pattern(
			'test/hero',
			'<!-- wp:heading {"metadata":{"name":"headline"}} --><h2>Default headline</h2><!-- /wp:heading -->'
		);

		$resolved = Pattern_Resolver::resolve(
			$this->pattern_block( $slug, array( 'headline' => array( 'content' => 'Should not appear' ) ) )
		);

		$this->assertStringContainsString( 'Default headline', $resolved );
		$this->assertStringNotContainsString( 'Should not appear', $resolved );
	}
}
