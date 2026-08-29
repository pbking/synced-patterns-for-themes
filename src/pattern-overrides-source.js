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
import { getBlockBindingsSource } from '@wordpress/blocks';
import { subscribe } from '@wordpress/data';

import { getOverridesUpdate } from './get-overrides-update';

const SOURCE_NAME = 'core/pattern-overrides';
const HOST_BLOCKS = [ 'core/block', 'core/pattern' ];

/**
 * Teaches the registered source to write to a pattern host.
 *
 * The source is amended in place rather than replaced. There is no supported
 * way to replace one: `registerBlockBindingsSource()` refuses a name that is
 * already registered, and unregistering first cannot be undone, because the
 * client-side `core/pattern-overrides` object carries no `label` and
 * registration requires one. Unregistering and failing to re-register would
 * leave the site with no pattern overrides at all.
 *
 * @param {Object} source The registered binding source.
 * @return {boolean} Whether the source was amended.
 */
function extendSource( source ) {
	const originalSetValues = source.setValues;

	if ( typeof originalSetValues !== 'function' ) {
		return false;
	}

	// A compatible plugin (or an earlier run) already amended the source.
	if ( originalSetValues.patternHostAmended ) {
		return true;
	}

	const setValues = ( args ) => {
		const { select, dispatch, clientId, bindings } = args;
		const { getBlockAttributes, getBlockName, getBlockParentsByBlockName } =
			select( blockEditorStore );

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
	};

	// The marker lets another copy of this amendment detect and skip this one.
	setValues.patternHostAmended = true;

	try {
		source.setValues = setValues;
	} catch ( error ) {
		return false;
	}

	// A frozen source silently keeps its own function.
	return source.setValues === setValues;
}

/**
 * Amends the binding source once core has registered it.
 *
 * The editor registers it while booting, which may be after this script runs,
 * so this waits for it rather than assuming it is already there.
 *
 * @return {void}
 */
export function extendPatternOverridesSource() {
	let done = false;

	const attempt = () => {
		if ( done ) {
			return true;
		}

		/*
		 * The server bootstraps this source with just a label and its context,
		 * and the editor adds the functions later. Waiting for `setValues` is
		 * what distinguishes the finished source from that stub.
		 */
		const source = getBlockBindingsSource( SOURCE_NAME );

		if ( typeof source?.setValues !== 'function' ) {
			return false;
		}

		done = extendSource( source );

		return done;
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
