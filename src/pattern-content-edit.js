/**
 * Expands a pattern block that carries content, in the editor canvas.
 *
 * Most of the time the editor never sees one of these: core composes patterns
 * and templates on the server, and this plugin composes the content into them
 * before that happens. This covers what's left — a pattern block written by
 * hand into post content, or one added while editing a template.
 *
 * It mirrors core's own `PatternEdit`, which replaces a pattern block with the
 * blocks it stands for, and adds one step: the content goes in first.
 */

import { cloneBlock } from '@wordpress/blocks';
import {
	store as blockEditorStore,
	useBlockProps,
} from '@wordpress/block-editor';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';

import { applyContent } from './apply-content';

/**
 * Replaces a pattern block with the pattern's blocks, content written in.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @param {string} props.clientId   Block client ID.
 * @return {JSX.Element} A placeholder, until the replacement lands.
 */
export function PatternContentEdit( { attributes, clientId } ) {
	const registry = useRegistry();
	const blockProps = useBlockProps();

	const pattern = useSelect(
		( select ) =>
			select( blockEditorStore ).__experimentalGetParsedPattern(
				attributes.slug
			),
		[ attributes.slug ]
	);

	const {
		replaceBlocks,
		setBlockEditingMode,
		__unstableMarkNextChangeAsNotPersistent,
	} = useDispatch( blockEditorStore );

	const { getBlockRootClientId, getBlockEditingMode } =
		useSelect( blockEditorStore );

	const { content } = attributes;

	useEffect( () => {
		if ( ! pattern?.blocks?.length ) {
			return;
		}

		window.queueMicrotask( () => {
			const blocks = applyContent( pattern.blocks, content ).map(
				( block ) => cloneBlock( block )
			);

			const rootClientId = getBlockRootClientId( clientId );
			const rootEditingMode = getBlockEditingMode( rootClientId );

			registry.batch( () => {
				/*
				 * The root block is briefly set to its default editing mode, so
				 * the replacement is allowed even where edits to non-content
				 * blocks are disabled. Core's `PatternEdit` does the same.
				 */
				__unstableMarkNextChangeAsNotPersistent();
				setBlockEditingMode( rootClientId, 'default' );
				__unstableMarkNextChangeAsNotPersistent();
				replaceBlocks( clientId, blocks );
				__unstableMarkNextChangeAsNotPersistent();
				setBlockEditingMode( rootClientId, rootEditingMode );
			} );
		} );
	}, [
		clientId,
		content,
		pattern,
		registry,
		getBlockEditingMode,
		getBlockRootClientId,
		replaceBlocks,
		setBlockEditingMode,
		__unstableMarkNextChangeAsNotPersistent,
	] );

	return <div { ...blockProps } />;
}

/**
 * Uses the component above for pattern blocks that carry content.
 *
 * @param {Function} BlockEdit The block's edit component.
 * @return {Function} The filtered edit component.
 */
export const withPatternContent = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		const { name, attributes } = props;

		if (
			name === 'core/pattern' &&
			attributes?.slug &&
			attributes?.content
		) {
			return <PatternContentEdit { ...props } />;
		}

		return <BlockEdit { ...props } />;
	},
	'withPatternContent'
);

addFilter(
	'editor.BlockEdit',
	'synced-patterns-for-themes/pattern-content-edit',
	withPatternContent
);
