/**
 * Tests for writing a pattern's content into its blocks.
 */

import { applyContent } from '../apply-content';

jest.mock( '@wordpress/blocks', () => ( {
	getBlockType: ( name ) =>
		name === 'core/paragraph'
			? { attributes: { content: {}, dropCap: {} } }
			: undefined,
} ) );

const OVERRIDES = 'core/pattern-overrides';

const paragraph = ( metadata, innerBlocks = [] ) => ( {
	name: 'core/paragraph',
	attributes: { content: 'Default', metadata },
	innerBlocks,
} );

describe( 'applyContent', () => {
	it( 'fills a bound slot and drops the binding it satisfied', () => {
		const [ block ] = applyContent(
			[
				paragraph( {
					name: 'lede',
					bindings: { content: { source: OVERRIDES } },
				} ),
			],
			{ lede: { content: 'Filled' } }
		);

		expect( block.attributes.content ).toBe( 'Filled' );
		expect( block.attributes.metadata.bindings ).toBeUndefined();
		expect( block.attributes.metadata.name ).toBe( 'lede' );
	} );

	it( 'drops the binding of a slot nothing was supplied for', () => {
		const [ block ] = applyContent(
			[
				paragraph( {
					name: 'lede',
					bindings: { content: { source: OVERRIDES } },
				} ),
			],
			{ somewhereElse: { content: 'Filled' } }
		);

		// Left in place the binding would resolve to nothing and lock the block.
		expect( block.attributes.content ).toBe( 'Default' );
		expect( block.attributes.metadata.bindings ).toBeUndefined();
	} );

	it( 'leaves an unbound attribute alone', () => {
		const [ block ] = applyContent( [ paragraph( { name: 'lede' } ) ], {
			lede: { content: 'Filled' },
		} );

		expect( block.attributes.content ).toBe( 'Default' );
	} );

	it( 'keeps bindings from other sources', () => {
		const [ block ] = applyContent(
			[
				paragraph( {
					name: 'lede',
					bindings: {
						content: { source: OVERRIDES },
						dropCap: { source: 'core/post-meta' },
					},
				} ),
			],
			{ lede: { content: 'Filled' } }
		);

		expect( block.attributes.metadata.bindings ).toEqual( {
			dropCap: { source: 'core/post-meta' },
		} );
	} );

	it( 'fills every declared attribute a __default binding opens up', () => {
		const [ block ] = applyContent(
			[
				paragraph( {
					name: 'lede',
					bindings: { __default: { source: OVERRIDES } },
				} ),
			],
			{ lede: { content: 'Filled', notAnAttribute: 'ignored' } }
		);

		expect( block.attributes.content ).toBe( 'Filled' );
		expect( block.attributes.notAnAttribute ).toBeUndefined();
		expect( block.attributes.metadata.bindings ).toBeUndefined();
	} );

	it( 'reaches slots nested inside the pattern', () => {
		const [ group ] = applyContent(
			[
				{
					name: 'core/group',
					attributes: {},
					innerBlocks: [
						paragraph( {
							name: 'lede',
							bindings: { content: { source: OVERRIDES } },
						} ),
					],
				},
			],
			{ lede: { content: 'Filled' } }
		);

		expect( group.innerBlocks[ 0 ].attributes.content ).toBe( 'Filled' );
	} );

	it( 'returns the blocks untouched when there is no content', () => {
		const blocks = [ paragraph( { name: 'lede' } ) ];

		expect( applyContent( blocks, undefined ) ).toBe( blocks );
	} );
} );
