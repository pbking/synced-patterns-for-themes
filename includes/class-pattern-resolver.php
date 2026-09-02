<?php
/**
 * Composes patterns and their content into plain blocks.
 *
 * @package SyncedPatternsForThemes
 */

namespace TwentyBellows\SyncedPatternsForThemes;

use WP_Block_Patterns_Registry;

/**
 * Replaces `core/pattern` blocks that carry content with the referenced
 * pattern's blocks, with the content written into them.
 *
 * The front end does not need this: there, `Pattern_Block` puts the content in
 * block context and core's `core/pattern-overrides` binding source resolves it
 * while rendering. The editor does, because it works with blocks rather than
 * rendered HTML — and because core flattens `core/pattern` blocks server side
 * (`resolve_pattern_blocks()`) before the editor ever sees them, which drops
 * the content attribute along the way.
 *
 * So for editor-facing markup the composition happens here first, and the
 * result is ordinary editable content: values written into the markup, and the
 * `core/pattern-overrides` bindings that asked for them removed. Whatever this
 * leaves behind is a plain pattern block that core resolves as it always has.
 *
 * A reference to a *synced* pattern is the one thing never composed. It is a
 * reference by definition — the editor renders it as an instance, design
 * locked and slots editable, and the front end renders it from the file — so
 * writing its content in would hand the editor a copy with the design
 * unlocked and the link gone. It is kept exactly as written, content and all,
 * and `compose()` exists so the editor's pattern list can inline everything
 * else the way core does without core's resolver flattening these too.
 */
class Pattern_Resolver {

	/**
	 * The block bindings source that marks a pattern's content slots.
	 */
	const OVERRIDES_SOURCE = 'core/pattern-overrides';

	/**
	 * How many patterns have been expanded, for detecting whether a subtree
	 * needed this resolver at all.
	 *
	 * @var int
	 */
	private static $expansions = 0;

	/**
	 * Slugs currently being expanded, to stop a pattern containing itself.
	 *
	 * @var array<string, true>
	 */
	private static $expanding = array();

	/**
	 * Whether plain pattern blocks — no content, none inside — are inlined
	 * too, the way core's own resolver inlines them. Set by `compose()`.
	 *
	 * @var bool
	 */
	private static $inline_plain = false;

	/**
	 * Cheap test for markup that might contain a pattern block.
	 *
	 * Parsing every pattern and template would be wasteful on sites that do not
	 * use the feature, and every one of them would have to be parsed to find
	 * out.
	 *
	 * @param mixed $markup Block markup.
	 * @return bool Whether the markup is worth parsing.
	 */
	public static function contains_pattern_block( $markup ): bool {
		return is_string( $markup ) && false !== strpos( $markup, 'wp:pattern ' );
	}

	/**
	 * Composes every pattern with content in a piece of block markup.
	 *
	 * @param string $markup Block markup.
	 * @return string Block markup with those patterns composed into it, or the
	 *                markup untouched if there were none.
	 */
	public static function resolve( string $markup ): string {
		if ( ! self::contains_pattern_block( $markup ) ) {
			return $markup;
		}

		$expansions = self::$expansions;
		$blocks     = self::resolve_blocks( parse_blocks( $markup ) );

		return self::$expansions === $expansions ? $markup : serialize_blocks( $blocks );
	}

	/**
	 * Composes a pattern the way the editor's pattern list needs it.
	 *
	 * Core flattens every `core/pattern` block in that list server side
	 * (`resolve_pattern_blocks()`), which loses the content attribute and
	 * turns a synced reference into a copy. This does core's job instead:
	 * content is written in, plain references are inlined the way core
	 * inlines them, and synced references are left as written for the
	 * editor to render as instances.
	 *
	 * @param string $markup Block markup.
	 * @return string Block markup with every pattern block composed into it,
	 *                except the synced references, or the markup untouched if
	 *                there were none.
	 */
	public static function compose( string $markup ): string {
		if ( ! self::contains_pattern_block( $markup ) ) {
			return $markup;
		}

		self::$inline_plain = true;

		try {
			$blocks = self::resolve_blocks( parse_blocks( $markup ) );
		} finally {
			self::$inline_plain = false;
		}

		return serialize_blocks( $blocks );
	}

	/**
	 * Composes every pattern with content in a list of parsed blocks.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @return array[] Parsed blocks, with those patterns composed into them.
	 */
	public static function resolve_blocks( array $blocks ): array {
		$resolved = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			foreach ( self::resolve_block( $block ) as $resolved_block ) {
				$resolved[] = $resolved_block;
			}
		}

		return $resolved;
	}

	/**
	 * Resolves a single parsed block.
	 *
	 * A pattern block becomes the blocks it stands for, which is why this
	 * returns a list rather than a block.
	 *
	 * @param array $block A parsed block.
	 * @return array[] The blocks that replace it.
	 */
	private static function resolve_block( array $block ): array {
		if ( 'core/pattern' === ( $block['blockName'] ?? null ) ) {
			$expanded = self::expand_pattern_block( $block );

			// Null means core's own resolver can take this one from here.
			return null === $expanded ? array( $block ) : $expanded;
		}

		if ( empty( $block['innerBlocks'] ) || empty( $block['innerContent'] ) ) {
			return array( $block );
		}

		/*
		 * `serialize_block()` walks `innerContent` and consumes one inner block
		 * for every null in it, so the two have to be rebuilt together: a
		 * pattern standing in one null slot may resolve to any number of blocks.
		 */
		$inner_blocks  = array();
		$inner_content = array();
		$index         = 0;

		foreach ( $block['innerContent'] as $chunk ) {
			if ( is_string( $chunk ) ) {
				$inner_content[] = $chunk;
				continue;
			}

			if ( ! isset( $block['innerBlocks'][ $index ] ) ) {
				continue;
			}

			$resolved = self::resolve_block( $block['innerBlocks'][ $index ] );
			++$index;

			foreach ( $resolved as $resolved_block ) {
				$inner_blocks[]  = $resolved_block;
				$inner_content[] = null;
			}
		}

		$block['innerBlocks']  = $inner_blocks;
		$block['innerContent'] = $inner_content;

		return array( $block );
	}

	/**
	 * Replaces a pattern block with the pattern's blocks, content written in.
	 *
	 * A pattern block with no content of its own is still expanded when the
	 * pattern it points at reaches one that has some — otherwise core would
	 * flatten its way down to that pattern and drop the content. A pattern
	 * block that leads nowhere near any content is left for core, unless
	 * `compose()` asked for it to be inlined here instead.
	 *
	 * A reference to a synced pattern is never expanded: it is returned as
	 * written, content and all, so the editor renders it as an instance.
	 *
	 * @param array $block A parsed `core/pattern` block.
	 * @return array[]|null The blocks that replace it, an empty array to drop
	 *                      it, or null to leave it to core's resolver.
	 */
	private static function expand_pattern_block( array $block ): ?array {
		$slug     = $block['attrs']['slug'] ?? null;
		$content  = $block['attrs'][ Pattern_Block::CONTENT_ATTRIBUTE ] ?? null;
		$registry = WP_Block_Patterns_Registry::get_instance();

		if ( ! is_string( $slug ) || ! $registry->is_registered( $slug ) ) {
			return null;
		}

		/*
		 * Kept, not left to core: null would hand it to `resolve_pattern_blocks()`,
		 * which inlines it and drops the content, and that is exactly the copy
		 * a synced pattern must never become.
		 */
		if ( Synced_Patterns::is_synced( $slug ) ) {
			return array( $block );
		}

		// A pattern that contains itself is dropped, the way core drops it.
		if ( isset( self::$expanding[ $slug ] ) ) {
			return array();
		}

		$pattern     = $registry->get_registered( $slug );
		$has_content = is_array( $content ) && ! empty( $content );

		if ( ! self::$inline_plain && ! $has_content && ! self::contains_pattern_block( $pattern['content'] ?? null ) ) {
			return null;
		}

		$blocks = parse_blocks( $pattern['content'] );

		if ( $has_content ) {
			$blocks = self::apply_content( $blocks, $content );
			++self::$expansions;
		}

		$expansions               = self::$expansions;
		self::$expanding[ $slug ] = true;
		$blocks                   = self::resolve_blocks( $blocks );
		unset( self::$expanding[ $slug ] );

		// Nothing inside needed this resolver, so core should expand it instead.
		if ( ! self::$inline_plain && ! $has_content && self::$expansions === $expansions ) {
			return null;
		}

		return self::add_pattern_metadata( $blocks, $pattern );
	}

	/**
	 * Marks a single-block pattern as an instance of that pattern.
	 *
	 * Mirrors what core's `resolve_pattern_blocks()` does when it inlines a
	 * pattern, so a pattern expanded here still reads as a pattern instance in
	 * the editor.
	 *
	 * @param array[] $blocks  The pattern's blocks.
	 * @param array   $pattern The registered pattern.
	 * @return array[] The blocks.
	 */
	private static function add_pattern_metadata( array $blocks, array $pattern ): array {
		if ( 1 !== count( $blocks ) || empty( $pattern['name'] ) ) {
			return $blocks;
		}

		$metadata                = $blocks[0]['attrs']['metadata'] ?? array();
		$metadata['patternName'] = $pattern['name'];

		/*
		 * A block's own name wins over the pattern's title, which is the one place
		 * this departs from core's resolver. A block that names a content slot has
		 * just had that slot filled, and renaming it would throw away what it was
		 * for. Core's editor makes the same choice when it expands a pattern.
		 */
		$values = array(
			'name'        => $metadata['name'] ?? $pattern['title'] ?? null,
			'description' => $pattern['description'] ?? $metadata['description'] ?? null,
			'categories'  => $pattern['categories'] ?? $metadata['categories'] ?? null,
		);

		foreach ( $values as $key => $value ) {
			if ( ! $value ) {
				continue;
			}

			$metadata[ $key ] = is_array( $value )
				? array_map( 'sanitize_text_field', $value )
				: sanitize_text_field( $value );
		}

		$blocks[0]['attrs']['metadata'] = $metadata;

		return $blocks;
	}

	/**
	 * Writes a pattern's content into that pattern's blocks.
	 *
	 * Every `core/pattern-overrides` binding in the tree is removed afterwards,
	 * including the ones no value was supplied for. The composed blocks are no
	 * longer inside a pattern, so a binding left behind would resolve to
	 * nothing and would only make the block read-only in the editor.
	 *
	 * @param array[] $blocks  The pattern's parsed blocks.
	 * @param array   $content Content, keyed by slot name and then attribute name.
	 * @return array[] The blocks with the content written into them.
	 */
	public static function apply_content( array $blocks, array $content ): array {
		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name   = $block['attrs']['metadata']['name'] ?? null;
			$values = ( is_string( $name ) && isset( $content[ $name ] ) && is_array( $content[ $name ] ) )
				? $content[ $name ]
				: array();

			$block = self::fill_slots( $block, $values );

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::apply_content( $block['innerBlocks'], $content );
			}

			$blocks[ $index ] = $block;
		}

		return $blocks;
	}

	/**
	 * Writes values into one block's content slots and removes its bindings.
	 *
	 * @param array $block  A parsed block.
	 * @param array $values Values for this block, keyed by attribute name.
	 * @return array The updated block.
	 */
	private static function fill_slots( array $block, array $values ): array {
		$bindings = $block['attrs']['metadata']['bindings'] ?? null;

		if ( ! is_array( $bindings ) || empty( $bindings ) ) {
			return $block;
		}

		$binds_everything = self::OVERRIDES_SOURCE === ( $bindings['__default']['source'] ?? null );
		$slots            = $binds_everything
			? self::get_supported_attributes( $block['blockName'] ?? '' )
			: array();

		foreach ( $bindings as $attribute => $binding ) {
			if ( '__default' !== $attribute && self::OVERRIDES_SOURCE === ( $binding['source'] ?? null ) ) {
				$slots[] = $attribute;
			}
		}

		foreach ( array_unique( $slots ) as $attribute ) {
			if ( array_key_exists( $attribute, $values ) ) {
				$block = Block_Markup::set_attribute( $block, (string) $attribute, $values[ $attribute ] );
			}

			unset( $bindings[ $attribute ] );
		}

		if ( $binds_everything ) {
			unset( $bindings['__default'] );
		}

		if ( ! empty( $bindings ) ) {
			$block['attrs']['metadata']['bindings'] = $bindings;

			return $block;
		}

		unset( $block['attrs']['metadata']['bindings'] );

		if ( empty( $block['attrs']['metadata'] ) ) {
			unset( $block['attrs']['metadata'] );
		}

		return $block;
	}

	/**
	 * Lists the attributes a block type can bind, for `__default` bindings.
	 *
	 * @param string $block_name Block type name, including namespace.
	 * @return string[] Attribute names.
	 */
	private static function get_supported_attributes( string $block_name ): array {
		if ( function_exists( 'get_block_bindings_supported_attributes' ) ) {
			return get_block_bindings_supported_attributes( $block_name );
		}

		// WordPress 6.8 and earlier keep this list private to `WP_Block`.
		$supported = array(
			'core/paragraph' => array( 'content' ),
			'core/heading'   => array( 'content' ),
			'core/image'     => array( 'id', 'url', 'title', 'alt' ),
			'core/button'    => array( 'url', 'text', 'linkTarget', 'rel' ),
		);

		return $supported[ $block_name ] ?? array();
	}
}
