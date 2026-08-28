=== Synced Patterns for Themes ===
Contributors:      twentybellows, pbking
Tags:              patterns, block patterns, block bindings, themes, block editor
Requires at least: 6.8
Tested up to:      7.1
Requires PHP:      7.4
Stable tag:        2.0.0
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

No. Version 1 of this plugin copied theme patterns into the database as synced
patterns, because a synced pattern was the only kind that would accept content.
Version 2 does not: `Synced: true` in a pattern header now does nothing, and
patterns are read from theme files as WordPress reads them normally.

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

= Does it work in the site editor? =

Yes. Patterns, templates and template parts are all composed before the editor
loads them, so what you see is what the front end renders.

= What if I use a slot name that the design pattern doesn't have? =

Nothing happens. Unknown slots are ignored, and slots with no value keep the
design pattern's own content.

== Changelog ==

= 2.0.0 =
* Rewritten around a single idea: `core/pattern` accepts a `content` attribute,
  the same way `core/block` already does.
* Removed the `pb_block` custom post type, its capabilities, and the database
  writes that kept it in sync on every request.
* Removed the REST API filters that made those posts look like synced patterns.
* `Synced: true` in a pattern header no longer does anything.
* Patterns now come from wherever WordPress registers them — parent themes,
  child themes, `.php` and `.html` files, and plugins — instead of a glob over
  the active theme's `patterns/*.php`.
* Content now works in templates and template parts, not only in patterns.

= 1.2.0 =
* Added block bindings support for pattern content.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
A rewrite. Theme patterns are no longer copied into the database, and
`Synced: true` no longer does anything. Patterns now take their content from a
`content` attribute on the pattern block. See the changelog before upgrading a
live site.
