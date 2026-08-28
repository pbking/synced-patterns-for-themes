<?php
/**
 * Title: Home Page
 * Slug: synced-patterns-test/page-home
 * Description: A hero and two cards, all built from the theme's design patterns.
 * Categories: featured
 *
 * @package SyncedPatternsTest
 */

?>
<!-- wp:pattern {"slug":"synced-patterns-test/hero","content":{
	"headline": { "content": "One design, many pages" },
	"lede":     { "content": "The words on this page live in this pattern. The design lives in the Hero pattern." }
}} /-->

<!-- wp:columns {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}}} -->
<div class="wp-block-columns" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:pattern {"slug":"synced-patterns-test/card","content":{
			"title": { "content": "Design lives here" },
			"body":  { "content": "Change the Card pattern and every card changes with it." },
			"link":  { "text": "See the pattern", "url": "https://wordpress.org/documentation/article/block-pattern/" },
			"image": { "alt": "The first card" }
		}} /-->
	</div>
	<!-- /wp:column -->

	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:pattern {"slug":"synced-patterns-test/card","content":{
			"title": { "content": "Content lives there" },
			"body":  { "content": "Two cards, one design, different words in each." },
			"link":  { "text": "See the docs", "url": "https://developer.wordpress.org/block-editor/reference-guides/block-api/block-bindings/" },
			"image": { "alt": "The second card" }
		}} /-->
	</div>
	<!-- /wp:column -->
</div>
<!-- /wp:columns -->
