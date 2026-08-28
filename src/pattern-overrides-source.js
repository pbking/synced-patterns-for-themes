/**
 * Lets a pattern block host content the way a synced pattern does.
 *
 * Core's `core/pattern-overrides` binding source is asymmetric. Reading a value
 * is generic — it comes from `pattern/overrides` block context, which
 * `core/pattern` now provides. Writing one is not: `setValues` looks only for a
 * `core/block` ancestor to store the value on, and with none it falls back to
 * updating every block of the same name in the document, which is the right
 * behaviour when editing a pattern's own source and the wrong one inside an
 * instance.
 *
 * So the source is re-registered with a `setValues` that recognises a
 * `core/pattern` host too, and hands everything else back to core's original.
 * If core ever stores values on a pattern block itself, this becomes a no-op.
 */

import { store as blockEditorStore } from '@wordpress/block-editor';
import {
	getBlockBindingsSource,
	registerBlockBindingsSource,
	unregisterBlockBindingsSource,
} from '@wordpress/blocks';
import { subscribe } from '@wordpress/data';

import { getOverridesUpdate } from './get-overrides-update';

const SOURCE_NAME = 'core/pattern-overrides';
const HOST_BLOCKS = [ 'core/block', 'core/pattern' ];

/**
 * Re-registers the binding source with a pattern-aware `setValues`.
 *
 * @param {Object} source The registered binding source.
 * @return {void}
 */
function extendSource( source ) {
	const originalSetValues = source.setValues;

	unregisterBlockBindingsSource( SOURCE_NAME );

	registerBlockBindingsSource( {
		...source,
		setValues( args ) {
			const { select, dispatch, clientId, bindings } = args;
			const {
				getBlockAttributes,
				getBlockName,
				getBlockParentsByBlockName,
			} = select( blockEditorStore );

			const [ hostClientId ] = getBlockParentsByBlockName(
				clientId,
				HOST_BLOCKS,
				true
			);

			const content = getOverridesUpdate( {
				name: getBlockAttributes( clientId )?.metadata?.name,
				hostBlockName: hostClientId
					? getBlockName( hostClientId )
					: undefined,
				bindings,
				content: getBlockAttributes( hostClientId )?.content,
			} );

			if ( null === content ) {
				return originalSetValues( args );
			}

			dispatch( blockEditorStore ).updateBlockAttributes( hostClientId, {
				content,
			} );
		},
	} );
}

/**
 * Extends the binding source once core has registered it.
 *
 * The editor registers it while booting, which may be after this script runs,
 * so this waits for it rather than assuming it is already there.
 *
 * @return {void}
 */
export function extendPatternOverridesSource() {
	let extended = false;

	const attempt = () => {
		if ( extended ) {
			return true;
		}

		const source = getBlockBindingsSource( SOURCE_NAME );

		if ( ! source?.setValues ) {
			return false;
		}

		extended = true;
		extendSource( source );

		return true;
	};

	if ( attempt() ) {
		return;
	}

	const unsubscribe = subscribe( () => {
		if ( attempt() ) {
			unsubscribe();
		}
	} );
}
