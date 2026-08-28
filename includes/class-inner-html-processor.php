<?php
/**
 * Replaces the content inside an HTML element.
 *
 * @package SyncedPatternsForThemes
 */

namespace TwentyBellows\SyncedPatternsForThemes;

use WP_HTML_Processor;
use WP_HTML_Span;
use WP_HTML_Text_Replacement;

/**
 * Adds inner-HTML replacement to the HTML API.
 *
 * `WP_HTML_Processor` has no `set_inner_html()` yet, so core reaches for the
 * same workaround when it resolves a block binding: subclass the processor,
 * find the matching closer, and splice the range between the two tags. This is
 * that workaround, kept to itself.
 *
 * @see WP_Block::replace_html()
 */
class Inner_HTML_Processor extends WP_HTML_Processor {

	/**
	 * Name of the bookmark used to record tag positions.
	 */
	const BOOKMARK = 'synced_patterns_inner_html';

	/**
	 * Replaces the content of the first element matching one of the selectors.
	 *
	 * @param string   $html        HTML to update.
	 * @param string[] $selectors   Tag names to look for, in order.
	 * @param string   $replacement HTML to put inside the element.
	 * @return string|null The updated HTML, or null if nothing was replaced.
	 */
	public static function replace_inner_html( string $html, array $selectors, string $replacement ): ?string {
		foreach ( $selectors as $tag ) {
			$processor = static::create_fragment( $html );

			if ( ! $processor instanceof self ) {
				return null;
			}

			if ( $processor->next_tag( array( 'tag_name' => $tag ) ) && $processor->set_inner_html( $replacement ) ) {
				return $processor->get_updated_html();
			}
		}

		return null;
	}

	/**
	 * Replaces the content between the current tag and its matching closer.
	 *
	 * @param string $html HTML to put inside the element.
	 * @return bool Whether the content was replaced.
	 */
	private function set_inner_html( string $html ): bool {
		if ( $this->is_tag_closer() || ! $this->expects_closer() ) {
			return false;
		}

		$depth    = $this->get_current_depth();
		$tag_name = $this->get_tag();

		$opener = $this->mark_current_token();

		if ( null === $opener ) {
			return false;
		}

		$start = $opener->start + $opener->length;

		// Walk out of the element. The token left behind is its closer.
		while ( $this->next_token() && $this->get_current_depth() >= $depth ) {
			continue;
		}

		if ( ! $this->is_tag_closer() || $tag_name !== $this->get_tag() ) {
			return false;
		}

		$closer = $this->mark_current_token();

		if ( null === $closer ) {
			return false;
		}

		$this->lexical_updates[] = new WP_HTML_Text_Replacement( $start, $closer->start - $start, $html );

		return true;
	}

	/**
	 * Returns the span the current token occupies in the source HTML.
	 *
	 * @return WP_HTML_Span|null The span, or null for a token that isn't in the source.
	 */
	private function mark_current_token(): ?WP_HTML_Span {
		if ( ! $this->set_bookmark( self::BOOKMARK ) ) {
			return null;
		}

		// `WP_HTML_Processor::set_bookmark()` prefixes the name it is given.
		$span = $this->bookmarks[ '_' . self::BOOKMARK ] ?? null;

		return $span instanceof WP_HTML_Span ? $span : null;
	}
}
