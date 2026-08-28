<?php
/**
 * Title: Hero
 * Slug: synced-patterns-test/hero
 * Description: A headline and a lede, filled in by whatever uses this pattern.
 * Inserter: no
 *
 * @package SyncedPatternsTest
 */

?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}},"color":{"background":"#e9e4f5"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="background-color:#e9e4f5;padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">
	<!-- wp:heading {"level":1,"metadata":{"name":"headline","bindings":{"content":{"source":"core/pattern-overrides"}}}} -->
	<h1 class="wp-block-heading">Headline goes here</h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"metadata":{"name":"lede","bindings":{"content":{"source":"core/pattern-overrides"}}}} -->
	<p>A sentence about what this section is for.</p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
