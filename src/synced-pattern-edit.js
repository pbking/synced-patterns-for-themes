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

import { cloneBlock } from '@wordpress/blocks';
import {
	BlockControls,
	RecursionProvider,
	useBlockProps,
	useHasRecursion,
	useInnerBlocksProps,
	Warning,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { ToolbarButton, ToolbarGroup } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';

import { applyContent } from './apply-content';

const NOOP = () => {};
const EMPTY_ARRAY = [];

/**
 * Renders one instance of a synced pattern.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {string}   props.clientId      Block client ID.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {JSX.Element} The instance.
 */
export function SyncedPatternEdit( { attributes, clientId, setAttributes } ) {
	const { slug, content } = attributes;

	const pattern = useSelect(
		( select ) =>
			select( blockEditorStore ).__experimentalGetParsedPattern( slug ),
		[ slug ]
	);

	const { replaceBlocks, __unstableMarkLastChangeAsPersistent } =
		useDispatch( blockEditorStore );

	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		value: pattern?.blocks ?? EMPTY_ARRAY,
		onInput: NOOP,
		onChange: NOOP,
	} );

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

	/**
	 * Puts the pattern's own content back.
	 */
	const resetContent = () => {
		__unstableMarkLastChangeAsPersistent();
		setAttributes( { content: undefined } );
	};

	/**
	 * Breaks the link, leaving ordinary editable blocks behind.
	 */
	const detach = () => {
		replaceBlocks(
			clientId,
			applyContent( pattern.blocks, content ).map( ( block ) =>
				cloneBlock( block )
			)
		);
	};

	return (
		<>
			<BlockControls group="other">
				<ToolbarGroup>
					<ToolbarButton
						onClick={ resetContent }
						disabled={ ! content }
					>
						{ __( 'Reset', 'synced-patterns-for-themes' ) }
					</ToolbarButton>
					<ToolbarButton onClick={ detach }>
						{ __( 'Detach', 'synced-patterns-for-themes' ) }
					</ToolbarButton>
				</ToolbarGroup>
			</BlockControls>
			<div { ...innerBlocksProps } />
		</>
	);
}

/**
 * Stops a synced pattern that contains itself from rendering forever.
 *
 * @param {Object} props Block props.
 * @return {JSX.Element} The instance, or a warning.
 */
export function SyncedPatternEditWithRecursionCheck( props ) {
	const { slug } = props.attributes;
	const hasAlreadyRendered = useHasRecursion( slug );
	const blockProps = useBlockProps();

	if ( hasAlreadyRendered ) {
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

	return (
		<RecursionProvider uniqueId={ slug }>
			<SyncedPatternEdit { ...props } />
		</RecursionProvider>
	);
}
