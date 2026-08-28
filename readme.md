# Synced Patterns for Themes

Let block markup supply the content for a pattern, so one pattern can carry the
design and another can carry the words.

```html
<!-- wp:pattern {"slug":"mytheme/hero","content":{"headline":{"content":"Built for the long haul"}}} /-->
```

## Why

A theme pattern is usually two things at once: a design, and the words that
happen to be sitting in it. Reuse the design and you copy the words with it — so
a theme that wants five hero sections ships five near-identical patterns, and
changing the design means changing all five.

This plugin separates them. A pattern marks the parts of itself that are meant to
be filled in; any other pattern, template or post can then use that design and
say what goes in it.

The `content` attribute is not new. It is the same attribute WordPress already
puts on a synced pattern when you override one of its fields — core just never
offered it to theme patterns. This plugin does.

## How

### 1. Mark the parts to fill in

In the pattern that holds the design, name each block that should be filled and
bind the attributes it fills. This is core's Pattern Overrides syntax, unchanged:

```php
<?php
/**
 * Title: Hero
 * Slug: mytheme/hero
 * Inserter: no
 */
?>
<!-- wp:cover {"url":"<?php echo esc_url( get_theme_file_uri( 'assets/hero.jpg' ) ); ?>","dimRatio":40} -->
<div class="wp-block-cover">
	<!-- wp:heading {"metadata":{"name":"headline","bindings":{"content":{"source":"core/pattern-overrides"}}}} -->
	<h2 class="wp-block-heading">Headline goes here</h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"metadata":{"name":"lede","bindings":{"content":{"source":"core/pattern-overrides"}}}} -->
	<p>A sentence about what this section is for.</p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:cover -->
```

* `metadata.name` names the slot.
* `bindings` says which of that block's attributes the slot fills.
* `{"__default":{"source":"core/pattern-overrides"}}` opens up every attribute the
  block supports, instead of listing them.
* `Inserter: no` keeps a pattern that exists to be filled in out of the inserter.

### 2. Fill them in

```php
<?php
/**
 * Title: Home Page
 * Slug: mytheme/page-home
 * Template Types: front-page
 */
?>
<!-- wp:pattern {"slug":"mytheme/hero","content":{
	"headline": { "content": "Built for the long haul" },
	"lede":     { "content": "Tools that outlast the fashion that made them." }
}} /-->

<!-- wp:pattern {"slug":"mytheme/hero","content":{
	"headline": { "content": "And again, differently" }
}} /-->
```

`content` is keyed by slot name, then by attribute name. Any slot you leave out
keeps the design pattern's own content, which makes a pattern's defaults double
as its documentation.

### Where it works

Patterns, templates, template parts, post content, and patterns inside other
patterns, to any depth. In the editor, inserting a pattern that uses this gives
you ordinary editable blocks with the content already in them.

### What can be filled

Whatever WordPress supports for Pattern Overrides — Paragraph and Heading
(`content`), Image (`url`, `alt`, `title`, `id`), Button (`text`, `url`,
`linkTarget`, `rel`), and List Item (`content`, in WordPress 7.1 and later).

## How it works

Three pieces, described at length in [`docs/PLAN-v2.md`](docs/PLAN-v2.md).

**On the front end**, `core/pattern` is given a `content` attribute and told to
provide it as `pattern/overrides` context — exactly what `core/block` already
declares in its `block.json`:

```jsonc
"attributes":      { "ref": {…}, "content": { "type": "object" } },
"providesContext": { "pattern/overrides": "content" }
```

The pattern's blocks are then rendered as inner blocks of the pattern block, so
that context reaches them, and core's own `core/pattern-overrides` binding source
resolves the values. The plugin never substitutes one itself.

**In the editor**, blocks are what matter rather than rendered HTML, and since
6.6 core flattens `core/pattern` blocks server-side before the editor sees them —
dropping the content on the way. So patterns and templates are composed first:
each pattern block with content becomes the blocks it stands for, the values are
written into their markup, and the bindings that asked for them are removed. What
the editor loads is plain, editable content.

**In the browser**, two small filters cover a pattern block that reaches the
canvas directly: one declares the `content` attribute so it survives a
parse/serialize round trip, the other expands the block with its content applied.

Nothing is written to the database, and no post type, capability or REST route is
added.

## Requirements

* WordPress 6.8 or later
* PHP 7.4 or later

## Upgrading from 1.x

Version 1 reached the same goal by copying theme patterns into the database as
synced patterns (`pb_block` posts) and making the REST API present them as
reusable blocks. None of that is left.

* `Synced: true` in a pattern header no longer does anything. Those patterns keep
  working as ordinary theme patterns.
* The `pb_block` posts version 1 created are no longer used, and are safe to
  delete.
* Patterns now come from wherever WordPress registers them — parent themes, child
  themes, `.php` and `.html` files, plugins — rather than a glob over the active
  theme's `patterns/*.php`.

## Development

```bash
npm install          # editor script dependencies
composer install     # test and linting dependencies

npm run build        # build build/index.js
npm run lint:js      # lint the editor script
composer run lint    # lint the PHP

npm run start        # start WordPress at http://localhost:8978 (needs Docker)
npm run test         # run the PHP tests against that environment
```

`dev-assets/themes/synced-patterns-test` is a small block theme that uses the
feature; `npm run start` mounts it, so it can be activated from Appearance →
Themes.

## License

GPL-2.0-or-later. Developed by [Twenty Bellows](https://twentybellows.com).
