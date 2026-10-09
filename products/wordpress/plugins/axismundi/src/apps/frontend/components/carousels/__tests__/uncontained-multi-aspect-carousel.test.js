import { createElement } from '@wordpress/element';

import { CarouselItem } from '../carousel-item';
import { CarouselItemMedia } from '../carousel-item-media';
import { CarouselItemText } from '../carousel-item-text';
import { declaresRatio } from '../uncontained-multi-aspect-carousel';

/*
 * The contract this wrapper exists for: a multi-aspect carousel's width is the
 * sum of its height times each item's ratio, so one item without a ratio makes
 * the sum unknowable. CarouselItemMedia defaults its own aspectRatio, so the
 * default never reaches the element's props and a missing one is really
 * missing -- which is what makes this checkable at all.
 */
describe( 'multi-aspect item ratio contract', () => {
	const item = ( ...children ) =>
		createElement( CarouselItem, { href: '#x', label: 'x' }, ...children );

	test( 'an item whose media declares a ratio satisfies it', () => {
		expect(
			declaresRatio(
				item(
					createElement( CarouselItemMedia, { aspectRatio: '16:9' } )
				)
			)
		).toBe( true );
	} );

	test( 'media relying on the default ratio does not', () => {
		expect(
			declaresRatio( item( createElement( CarouselItemMedia, {} ) ) )
		).toBe( false );
	} );

	test( 'an item with no media does not', () => {
		expect( declaresRatio( item() ) ).toBe( false );
		expect(
			declaresRatio(
				item( createElement( CarouselItemText, { label: 'x' } ) )
			)
		).toBe( false );
	} );

	test.each( [ null, 0, '', 'garbage', '16:0', '1:' ] )(
		'a ratio of %p does not satisfy it',
		( aspectRatio ) => {
			/*
			 * Present is not the same as usable. These all passed an undefined
			 * check and then fell back to a box with no ratio, which is the state
			 * the wrapper exists to prevent.
			 */
			expect(
				declaresRatio(
					item( createElement( CarouselItemMedia, { aspectRatio } ) )
				)
			).toBe( false );
		}
	);

	test( 'the ratio is found beside other item content', () => {
		expect(
			declaresRatio(
				item(
					createElement( CarouselItemMedia, { aspectRatio: '9:16' } ),
					createElement( CarouselItemText, { label: 'x' } )
				)
			)
		).toBe( true );
	} );
} );
