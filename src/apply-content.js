/**
 * Writes a pattern's content into that pattern's blocks.
 *
 * The counterpart of `Pattern_Resolver::apply_content()` on the server, and it
 * follows the same rules: a block takes part when `metadata.name` matches a key
 * in the content, an attribute is filled when a `core/pattern-overrides`
 * binding asks for it, and every one of those bindings is removed afterwards so
 * what's left is ordinary editable content.
 */

import { getBlockType } from '@wordpress/blocks';

const OVERRIDES_SOURCE = 'core/pattern-overrides';

const hasValue = ( values, key ) =>
	Object.prototype.hasOwnProperty.call( values, key );

/**
 * Lists the attributes a block has content slots for.
 *
 * @param {Object} block  A block.
 * @param {Object} values Values supplied for this block.
 * @return {string[]} Attribute names.
 */
function getContentSlots( block, values ) {
	const bindings = block.attributes?.metadata?.bindings ?? {};
	const slots = new Set();

	if ( bindings.__default?.source === OVERRIDES_SOURCE ) {
		const declared = getBlockType( block.name )?.attributes ?? {};

		Object.keys( values )
			.filter( ( key ) => key in declared )
			.forEach( ( key ) => slots.add( key ) );
	}

	Object.entries( bindings ).forEach( ( [ key, binding ] ) => {
		if ( key !== '__default' && binding?.source === OVERRIDES_SOURCE ) {
			slots.add( key );
		}
	} );

	return [ ...slots ];
}

/**
 * Fills one block's content slots and removes its bindings.
 *
 * @param {Object} block  A block.
 * @param {Object} values Values for this block, keyed by attribute name.
 * @return {Object} The updated block.
 */
function fillSlots( block, values ) {
	const metadata = block.attributes?.metadata;
	const bindings = metadata?.bindings;

	if ( ! bindings ) {
		return block;
	}

	const bindsEverything = bindings.__default?.source === OVERRIDES_SOURCE;
	const slots = getContentSlots( block, values );

	if ( ! slots.length && ! bindsEverything ) {
		return block;
	}

	const attributes = { ...block.attributes };
	const nextBindings = { ...bindings };

	slots.forEach( ( key ) => {
		if ( hasValue( values, key ) ) {
			attributes[ key ] = values[ key ];
		}

		delete nextBindings[ key ];
	} );

	if ( bindsEverything ) {
		delete nextBindings.__default;
	}

	const nextMetadata = { ...metadata };

	if ( Object.keys( nextBindings ).length ) {
		nextMetadata.bindings = nextBindings;
	} else {
		delete nextMetadata.bindings;
	}

	attributes.metadata = nextMetadata;

	return { ...block, attributes };
}

/**
 * Writes a pattern's content into a tree of blocks.
 *
 * @param {Object[]} blocks  The pattern's blocks.
 * @param {Object}   content Content, keyed by slot name and then attribute name.
 * @return {Object[]} The blocks with the content written into them.
 */
export function applyContent( blocks, content ) {
	if ( ! content || typeof content !== 'object' ) {
		return blocks;
	}

	return blocks.map( ( block ) => {
		const name = block.attributes?.metadata?.name;
		const values =
			typeof name === 'string' &&
			content[ name ] &&
			typeof content[ name ] === 'object'
				? content[ name ]
				: {};

		const filled = fillSlots( block, values );

		return {
			...filled,
			innerBlocks: applyContent( filled.innerBlocks ?? [], content ),
		};
	} );
}
