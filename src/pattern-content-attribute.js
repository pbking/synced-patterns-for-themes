/**
 * Declares the `content` attribute on `core/pattern`.
 *
 * Without this the editor drops the attribute the first time a pattern block is
 * parsed and serialized, because block attributes that aren't declared don't
 * survive the round trip. The context it provides mirrors `core/block`, whose
 * block.json reads:
 *
 *     "attributes":      { "ref": {…}, "content": { "type": "object" } }
 *     "providesContext": { "pattern/overrides": "content" }
 */

import { addFilter } from '@wordpress/hooks';

/**
 * Adds the content attribute and the context it provides to `core/pattern`.
 *
 * @param {Object} settings Block type settings.
 * @param {string} name     Block type name.
 * @return {Object} Filtered settings.
 */
export function addPatternContentAttribute( settings, name ) {
	if ( name !== 'core/pattern' ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			content: { type: 'object' },
		},
		providesContext: {
			...settings.providesContext,
			'pattern/overrides': 'content',
		},
		/*
		 * Without this the list view calls every pattern block "Pattern
		 * Placeholder". Core adds a label callback that would use the block's
		 * own `metadata.name`, but only for block types that support renaming,
		 * and `core/pattern` does not. Setting one here takes precedence: core's
		 * callback returns early when a block type already has a label.
		 */
		__experimentalLabel: ( attributes, { context } ) => {
			if ( context !== 'list-view' && context !== 'breadcrumb' ) {
				return undefined;
			}

			return attributes?.metadata?.name || attributes?.slug;
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'synced-patterns-for-themes/pattern-content-attribute',
	addPatternContentAttribute
);
