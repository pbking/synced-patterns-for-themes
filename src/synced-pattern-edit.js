/**
 * Renders a synced pattern as a live instance in the editor.
 *
 * A pattern marked `Synced: yes` is inserted as a reference to itself, so this
 * has to show it the way core shows a synced pattern: the design rendered from
 * the theme's pattern file and not editable, the content slots editable.
 *
 * The mechanics are core's, from `ReusableBlockEdit`. The pattern's blocks are
 * handed to `useInnerBlocksProps` as a controlled value with handlers that
 * discard changes, which is what locks the design. The slots stay editable
 * because `core/pattern` provides `pattern/overrides` context and the block
 * bindings machinery reads and writes through it.
 */

import {
	RecursionProvider,
	useBlockProps,
	useHasRecursion,
	useInnerBlocksProps,
	Warning,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

const NOOP = () => {};
const EMPTY_ARRAY = [];
const OVERRIDES_SOURCE = 'core/pattern-overrides';

/**
 * Determines whether a block has a slot the instance can fill.
 *
 * @param {Object} block A block.
 * @return {boolean} Whether any attribute is bound to pattern overrides.
 */
function hasContentSlot( block ) {
	const bindings = block.attributes?.metadata?.bindings ?? {};

	return Object.values( bindings ).some(
		( binding ) => binding?.source === OVERRIDES_SOURCE
	);
}

/**
 * Locks the design of an instance, leaving its content slots editable.
 *
 * Core derives exactly this for a synced pattern, but the reducer that does it
 * collects hosts by block name and only knows about `core/block`:
 *
 *     if ( block?.name === 'core/block' ) { syncedPatternClientIds.push( clientId ); }
 *
 * There is no filter on that, so the modes are set here instead. An explicit
 * mode wins, because the derivation skips any block that already has one.
 *
 * @param {string} clientId The instance's client ID.
 * @return {void}
 */
function useLockedDesign( clientId ) {
	const blocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks( clientId ),
		[ clientId ]
	);

	const { setBlockEditingMode, unsetBlockEditingMode } =
		useDispatch( blockEditorStore );

	useEffect( () => {
		const seen = [];

		const walk = ( list ) => {
			list.forEach( ( block ) => {
				seen.push( block.clientId );
				setBlockEditingMode(
					block.clientId,
					hasContentSlot( block ) ? 'contentOnly' : 'disabled'
				);
				walk( block.innerBlocks ?? [] );
			} );
		};

		walk( blocks ?? [] );

		return () => {
			seen.forEach( ( id ) => unsetBlockEditingMode( id ) );
		};
	}, [ blocks, setBlockEditingMode, unsetBlockEditingMode ] );
}

/**
 * Renders one instance of a synced pattern.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @param {string} props.clientId   Block client ID.
 * @return {JSX.Element} The instance.
 */
export function SyncedPatternEdit( { attributes, clientId } ) {
	const { slug } = attributes;

	const pattern = useSelect(
		( select ) =>
			select( blockEditorStore ).__experimentalGetParsedPattern( slug ),
		[ slug ]
	);

	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		value: pattern?.blocks ?? EMPTY_ARRAY,
		onInput: NOOP,
		onChange: NOOP,
	} );

	useLockedDesign( clientId );

	if ( ! pattern ) {
		return (
			<div { ...blockProps }>
				<Warning>
					{ sprintf(
						/* translators: %s: A pattern's slug. */
						__(
							'The pattern "%s" is not available.',
							'synced-patterns-for-themes'
						),
						slug
					) }
				</Warning>
			</div>
		);
	}

	return <div { ...innerBlocksProps } />;
}

/**
 * Shown in place of a pattern that contains itself.
 *
 * @return {JSX.Element} The warning.
 */
function RecursionWarning() {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<Warning>
				{ __(
					'This pattern cannot be rendered inside itself.',
					'synced-patterns-for-themes'
				) }
			</Warning>
		</div>
	);
}

/**
 * Stops a synced pattern that contains itself from rendering forever.
 *
 * `useBlockProps()` belongs to whichever component actually renders the block,
 * so it is called in the warning or in the instance, never in both.
 *
 * @param {Object} props Block props.
 * @return {JSX.Element} The instance, or a warning.
 */
export function SyncedPatternEditWithRecursionCheck( props ) {
	const { slug } = props.attributes;
	const hasAlreadyRendered = useHasRecursion( slug );

	if ( hasAlreadyRendered ) {
		return <RecursionWarning />;
	}

	return (
		<RecursionProvider uniqueId={ slug }>
			<SyncedPatternEdit { ...props } />
		</RecursionProvider>
	);
}
