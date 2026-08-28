<?php
/**
 * Title: Card
 * Slug: synced-patterns-test/card
 * Description: An image, a title, a line of text and a link, all filled in from elsewhere.
 * Inserter: no
 *
 * @package SyncedPatternsTest
 */

?>
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:image {"metadata":{"name":"image","bindings":{"__default":{"source":"core/pattern-overrides"}}}} -->
	<figure class="wp-block-image"><img src="<?php echo esc_url( get_theme_file_uri( 'screenshot.png' ) ); ?>" alt="A placeholder image"/></figure>
	<!-- /wp:image -->

	<!-- wp:heading {"level":2,"metadata":{"name":"title","bindings":{"content":{"source":"core/pattern-overrides"}}}} -->
	<h2 class="wp-block-heading">Card title</h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"metadata":{"name":"body","bindings":{"content":{"source":"core/pattern-overrides"}}}} -->
	<p>What this card is about.</p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button {"metadata":{"name":"link","bindings":{"text":{"source":"core/pattern-overrides"},"url":{"source":"core/pattern-overrides"}}}} -->
		<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://wordpress.org">Read more</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
