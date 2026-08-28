=== Synced Patterns for Themes ===
Contributors:      twentybellows, pbking
Tags:              patterns, block patterns, synced patterns, block bindings, themes
Requires at least: 6.8
Tested up to:      7.1
Requires PHP:      7.4
Stable tag:        2.1.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Let block markup supply the content for a pattern, so one pattern can carry the design and another can carry the words.

== Description ==

A theme pattern is usually two things at once: a design, and the words that happen
to be sitting in it. Reuse the design and you copy the words with it — so a theme
that wants five hero sections ships five near-identical patterns.

This plugin separates the two. A pattern marks the parts of itself that are meant
to be filled in, and any other pattern — or template, or post — can then use that
design and say what goes in it:

`<!-- wp:pattern {"slug":"mytheme/hero","content":{"headline":{"content":"Built for the long haul"}}} /-->`

One design pattern. As many uses as you like, each with its own content, all
expressed in theme files.

The `content` attribute here is not new: it is the same attribute WordPress
already puts on a synced pattern when you edit one of its fields. All this plugin
does is let a theme pattern accept it too.

= Marking the parts to fill in =

In the design pattern, give each block you want filled a name, and bind the
attributes that should take a value. This is core's Pattern Overrides syntax,
unchanged:

`
<!-- wp:heading {"metadata":{"name":"headline","bindings":{"content":{"source":"core/pattern-overrides"}}}} -->
<h2 class="wp-block-heading">Headline goes here</h2>
<!-- /wp:heading -->
`

`metadata.name` names the slot. `bindings` says which of that block's attributes
the slot fills. `bindings` can also be `{"__default":{"source":"core/pattern-overrides"}}`
to open up every attribute the block supports.

= Filling them in =

Anywhere a pattern can be used, `content` supplies the values — keyed by slot
name, then by attribute name:

`
<!-- wp:pattern {"slug":"mytheme/hero","content":{
    "headline": { "content": "Built for the long haul" },
    "photo":    { "url": "…/roof.jpg", "alt": "A tin roof" }
}} /-->
`

Any slot you leave out keeps the design pattern's own content, so a pattern's
defaults double as its documentation.

= Keeping a pattern linked =

By default a pattern is a starting point: insert it and you get ordinary blocks
you are free to change. Add `Synced: yes` to a pattern's header and it behaves
like a synced pattern instead:

`
<?php
/**
 * Title: Notice
 * Slug: mytheme/notice
 * Synced: yes
 */
?>
`

Inserting it stores a reference rather than a copy. The design is rendered from
the theme file and cannot be edited on the page — only the content slots can.
Edit the file and every notice on the site changes with it, keeping whatever
content each one was given.

Unlike version 1, nothing is copied into the database to make this work: the
theme file stays the only source of truth.

**Slots in a synced pattern must bind with `__default`.** WordPress only lets you
type into a bound field inside a pattern instance when the block binds that way:

`
<!-- wp:paragraph {"metadata":{"name":"message","bindings":{"__default":{"source":"core/pattern-overrides"}}}} -->
<p>Edit this file and every notice changes with it.</p>
<!-- /wp:paragraph -->
`

Naming attributes one by one — `{"content":{"source":"core/pattern-overrides"}}` —
still works for content supplied from markup, but the editor renders those slots
read-only inside an instance. That is WordPress's rule, not this plugin's.

= Where it works =

Patterns, templates, template parts, post content, and patterns inside other
patterns, to any depth. In the editor, inserting a pattern that uses this gives
you ordinary editable blocks with the right content already in them.

= What can be filled =

The blocks and attributes WordPress supports for Pattern Overrides:

* Paragraph and Heading — `content`
* Image — `url`, `alt`, `title`, `id`
* Button — `text`, `url`, `linkTarget`, `rel`
* List Item — `content` (WordPress 7.1 and later)

The list is core's, not this plugin's, so it grows as core's does.

== Installation ==

1. Install and activate the plugin.
2. Add `metadata.name` and `core/pattern-overrides` bindings to the blocks in a
   theme pattern you want to fill in.
3. Use that pattern from another pattern, template or post with a `content`
   attribute.

No settings, no admin screen. Nothing is written to the database.

== Frequently Asked Questions ==

= Do I need to mark my patterns as synced? =

Only if you want them to stay linked when someone inserts them. Filling a
pattern's content from block markup needs no header at all.

`Synced: yes` means what it says now — the pattern is inserted as a reference to
the theme file, and edits to that file reach every use. Version 1 used the same
header to copy the pattern into the database as a `wp_block`; version 2 keeps
the file as the only source of truth.

= Can a plugin's patterns be synced? =

Yes. A plugin's patterns have no file header to read, so they opt in through a
filter:

`add_filter( 'synced_patterns_for_themes_synced_patterns', function ( $slugs ) {
	$slugs[] = 'myplugin/notice';
	return $slugs;
} );`

= What happens to the posts version 1 created? =

They stop being used. They are `pb_block` posts, invisible in the admin, and safe
to delete.

= Is this the same as Pattern Overrides on a synced pattern? =

It is the same mechanism, aimed somewhere else. Pattern Overrides let a *person*
fill in a synced pattern from the editor, and the values live in the database.
This lets a *theme* fill in a pattern from block markup, and the values live in
the theme.

= Does the design pattern have to be hidden from the inserter? =

No, but `Inserter: no` in its header is usually what you want: a pattern that
exists to be filled in has little to offer on its own.

= How do I break the link on a page? =

There is no Detach control yet. WordPress renders no block toolbar for a pattern
block, so there is nowhere to put one; the settings sidebar shows WordPress's own
Content panel listing the pattern's slots instead. Removing the block and
inserting the pattern's blocks by hand is the workaround for now.

= Does it work in the site editor? =

Yes. Patterns, templates and template parts are all composed before the editor
loads them, so what you see is what the front end renders.

= What if I use a slot name that the design pattern doesn't have? =

Nothing happens. Unknown slots are ignored, and slots with no value keep the
design pattern's own content.

== Changelog ==

= 2.1.0 =
* `Synced: yes` in a pattern header keeps the pattern linked when it is
  inserted, rendered live from the theme file with only its content slots
  editable — without copying anything into the database.
* Added the `synced_patterns_for_themes_synced_patterns` filter, so patterns
  registered by a plugin can be synced too.
* Slots in a synced pattern must bind with `__default`; WordPress renders any
  other binding read-only inside an instance.

= 2.0.0 =
* Rewritten around a single idea: `core/pattern` accepts a `content` attribute,
  the same way `core/block` already does.
* Removed the `pb_block` custom post type, its capabilities, and the database
  writes that kept it in sync on every request.
* Removed the REST API filters that made those posts look like synced patterns.
* `Synced: true` in a pattern header no longer copies the pattern into the
  database. As of 2.1.0 it keeps the pattern linked instead.
* Patterns now come from wherever WordPress registers them — parent themes,
  child themes, `.php` and `.html` files, and plugins — instead of a glob over
  the active theme's `patterns/*.php`.
* Content now works in templates and template parts, not only in patterns.

= 1.2.0 =
* Added block bindings support for pattern content.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.1.0 =
`Synced: yes` keeps a pattern linked when it is inserted, without copying it
into the database.

= 2.0.0 =
A rewrite. Theme patterns are no longer copied into the database. Patterns now
take their content from a `content` attribute on the pattern block. See the
changelog before upgrading a live site.
