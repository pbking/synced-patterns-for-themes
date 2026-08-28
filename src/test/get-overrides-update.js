/**
 * Tests for hosting pattern content on a pattern block.
 */

import { getOverridesUpdate } from '../get-overrides-update';

describe( 'getOverridesUpdate', () => {
	const bindings = { content: { newValue: 'New words' } };

	it( 'stores the value under the block name when the host is a pattern', () => {
		expect(
			getOverridesUpdate( {
				name: 'headline',
				hostBlockName: 'core/pattern',
				bindings,
				content: undefined,
			} )
		).toEqual( { headline: { content: 'New words' } } );
	} );

	it( 'leaves a reusable block host to core', () => {
		expect(
			getOverridesUpdate( {
				name: 'headline',
				hostBlockName: 'core/block',
				bindings,
			} )
		).toBeNull();
	} );

	it( 'leaves an unhosted block to core, which syncs by name', () => {
		expect(
			getOverridesUpdate( { name: 'headline', bindings } )
		).toBeNull();
	} );

	it( 'ignores a block with no name, which has no slot to fill', () => {
		expect(
			getOverridesUpdate( { hostBlockName: 'core/pattern', bindings } )
		).toBeNull();
	} );

	it( 'keeps the content of other slots and other attributes', () => {
		expect(
			getOverridesUpdate( {
				name: 'headline',
				hostBlockName: 'core/pattern',
				bindings,
				content: {
					headline: { content: 'Old words', extra: 'kept' },
					lede: { content: 'Untouched' },
				},
			} )
		).toEqual( {
			headline: { content: 'New words', extra: 'kept' },
			lede: { content: 'Untouched' },
		} );
	} );

	it( 'stores an emptied field as an empty string, the way core does', () => {
		expect(
			getOverridesUpdate( {
				name: 'headline',
				hostBlockName: 'core/pattern',
				bindings: { content: { newValue: undefined } },
			} )
		).toEqual( { headline: { content: '' } } );
	} );
} );
