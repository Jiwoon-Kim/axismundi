const EDGE = 8;

function fits( start, size, limit ) {
	return start >= EDGE && start + size <= limit - EDGE;
}

function clamp( value, size, limit ) {
	return Math.min( Math.max( EDGE, value ), Math.max( EDGE, limit - size - EDGE ) );
}

/**
 * Fit a popover beside an anchor rectangle. The main axis flips first, then
 * the cross axis shifts while preserving an anchor edge; only a box that fits
 * on neither edge is clamped free of its anchor.
 *
 * @param {HTMLElement} menu Popover host.
 * @param {DOMRect}     anchor Anchor rectangle.
 * @param {'block'|'inline'} [axis='block'] Axis on which the popup opens.
 * @param {number} [gap=0] Space between anchor and popup on the main axis.
 * @param {boolean} [preferEnd=true] Default direction before a lack of space flips it.
 * @param {'start'|'center'} [crossAlignment='start'] Cross-axis anchor alignment.
 * @return {void}
 */
export function placePopover(
	menu,
	anchor,
	axis = 'block',
	gap = 0,
	preferEnd = true,
	crossAlignment = 'start'
) {
	if ( ! menu || ! anchor ) {
		return;
	}

	const blockAxis = 'block' === axis;
	const mainLimit = blockAxis ? window.innerHeight : window.innerWidth;
	const crossLimit = blockAxis ? window.innerWidth : window.innerHeight;
	const mainEnd = blockAxis ? anchor.bottom : anchor.right;
	const mainStart = blockAxis ? anchor.top : anchor.left;
	const crossStart = blockAxis ? anchor.left : anchor.top;
	const crossEnd = blockAxis ? anchor.right : anchor.bottom;
	const endSpace = Math.max( 0, mainLimit - mainEnd - EDGE - gap );
	const startSpace = Math.max( 0, mainStart - EDGE - gap );

	menu.style.maxBlockSize = '';
	menu.style.maxInlineSize = '';
	const naturalMainSize = blockAxis ? menu.scrollHeight : menu.scrollWidth;
	const preferredSpace = preferEnd ? endSpace : startSpace;
	const alternateSpace = preferEnd ? startSpace : endSpace;
	const opensEnd = preferEnd
		? naturalMainSize <= preferredSpace || preferredSpace >= alternateSpace
		: !( naturalMainSize <= preferredSpace || preferredSpace >= alternateSpace );
	const availableMainSize = opensEnd ? endSpace : startSpace;

	if ( blockAxis ) {
		menu.style.maxBlockSize = `${ availableMainSize }px`;
	} else {
		menu.style.maxInlineSize = `${ availableMainSize }px`;
		menu.style.maxBlockSize = `${ Math.max( 0, window.innerHeight - EDGE * 2 ) }px`;
	}
	const box = menu.getBoundingClientRect();
	const mainSize = blockAxis ? box.height : box.width;
	const crossSize = blockAxis ? box.width : box.height;
	const mainPosition = opensEnd ? mainEnd + gap : mainStart - mainSize - gap;
	const centeredCrossPosition = crossStart + ( crossEnd - crossStart - crossSize ) / 2;
	const shiftedCrossPosition = 'center' === crossAlignment
		? clamp( centeredCrossPosition, crossSize, crossLimit )
		: fits( crossStart, crossSize, crossLimit )
			? crossStart
			: fits( crossEnd - crossSize, crossSize, crossLimit )
				? crossEnd - crossSize
				: clamp( crossStart, crossSize, crossLimit );

	menu.style.inset = 'auto';
	menu.style.top = `${ blockAxis ? mainPosition : shiftedCrossPosition }px`;
	menu.style.left = `${ blockAxis ? shiftedCrossPosition : mainPosition }px`;
	menu.dataset.popoverPlacement = `${ axis }-${ opensEnd ? 'end' : 'start' }`;
}
