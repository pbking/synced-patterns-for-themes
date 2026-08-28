/**
 * Where an edit inside a pattern instance is stored.
 *
 * Kept apart from the binding source it serves so it can be tested on its own:
 * this is the whole of the behaviour core's `setValues` is missing.
 */

/**
 * Works out the content a pattern block should hold after an edit.
 *
 * Returns null when this is not an edit inside a pattern instance, which is
 * every case core already handles correctly.
 *
 * @param {Object} options                 Options.
 * @param {string} [options.name]          The edited block's `metadata.name`.
 * @param {string} [options.hostBlockName] Block name of the nearest content host.
 * @param {Object} options.bindings        The bindings being written, keyed by attribute.
 * @param {Object} [options.content]       The host's current content.
 * @return {Object|null} The host's new content, or null to leave it to core.
 */
export function getOverridesUpdate( {
	name,
	hostBlockName,
	bindings,
	content,
} ) {
	if ( ! name || hostBlockName !== 'core/pattern' ) {
		return null;
	}

	const values = Object.entries( bindings ).reduce(
		( carry, [ attribute, { newValue } ] ) => {
			// An emptied field is stored as '', the way core stores it.
			carry[ attribute ] = newValue === undefined ? '' : newValue;

			return carry;
		},
		{}
	);

	return {
		...content,
		[ name ]: { ...content?.[ name ], ...values },
	};
}
