/**
 * Converting between a Dialog Button and a Dialog Icon Button.
 *
 * The two are one button with two anatomies - M3's Standard button group calls
 * it "Button type: Icon | Label" - so switching is a change of anatomy, not a
 * change of button. Everything that is not about the anatomy has to survive it:
 * the size, the shape, the link, whether it is a toggle and whether it is
 * selected, and the icon itself.
 *
 * Copied BY NAME, never by spreading the whole attribute object. Spreading
 * moves anything that happens to share a name, including attributes one block
 * has not declared, and they then sit in saved content as values nothing reads.
 * A new attribute has to be added to this list to travel, which is the point:
 * whether it should is a decision, not a default.
 */
import { createBlock } from '@wordpress/blocks';
import { select } from '@wordpress/data';
import { store as blocksStore } from '@wordpress/blocks';

export const BUTTON = 'axismundi/dialog-button';
export const ICON_BUTTON = 'axismundi/dialog-icon-button';

/*
 * Declared by both, and meaning the same thing in both. `text` is the one worth
 * naming: it is the label a Button shows and the name an Icon Button carries
 * out of sight, which is why both store it under the same name - a converted
 * button keeps what it was called either way.
 */
const SHARED = [
	'tagName',
	'type',
	'action',
	'actionTarget',
	'size',
	'shape',
	'togglable',
	'selected',
	'disabled',
	'url',
	'text',
	'linkTarget',
	'rel',
	'iconSource',
	'icon',
	'selectedIcon',
	'fillOnSelect',
	'iconClass',
];

function shared( attributes ) {
	const out = {};
	SHARED.forEach( ( key ) => {
		if ( attributes[ key ] !== undefined ) {
			out[ key ] = attributes[ key ];
		}
	} );
	return out;
}

/*
 * `className` carries two different things: block styles, which belong to a
 * block, and the author's own classes, which belong to the content. So it is
 * filtered rather than copied or dropped - a style the target does not register
 * would otherwise land as an `is-style-*` that nothing defines, and dropping
 * the lot would throw away classes the author wrote by hand.
 *
 * The registry is asked at conversion time instead of a list being kept here,
 * so a style added later by a theme or a plugin is included without this file
 * knowing about it.
 */
function className( value, target ) {
	if ( ! value ) {
		return undefined;
	}
	const styles = ( select( blocksStore ).getBlockStyles( target ) || [] ).map(
		( style ) => `is-style-${ style.name }`
	);
	const kept = value
		.split( /\s+/ )
		.filter( ( name ) => name && ( ! name.startsWith( 'is-style-' ) || styles.includes( name ) ) );
	return kept.length ? kept.join( ' ' ) : undefined;
}

// An Icon Button's name is plain text: it is read aloud and shown as a tooltip,
// and there is nowhere for formatting to appear. A Button's label is rich text,
// so the markup is dropped here rather than carried invisibly - it would show
// as tags in the Label field and be stripped again at render.
function plain( text ) {
	if ( ! text ) {
		return undefined;
	}
	const el = document.createElement( 'div' );
	el.innerHTML = text;
	return ( el.textContent || '' ).trim() || undefined;
}

export function toIconButton( attributes ) {
	return createBlock( ICON_BUTTON, {
		...shared( attributes ),
		text: plain( attributes.text ),
		className: className( attributes.className, ICON_BUTTON ),
		// `showIcon` does not travel: an icon button is its icon, so there is
		// nothing for the setting to turn off. A button that had its icon
		// switched off keeps the icon's name and gets it back here, which is
		// the same promise the switch itself makes.
	} );
}

export function toButton( attributes ) {
	const out = {
		...shared( attributes ),
		className: className( attributes.className, BUTTON ),
	};
	// The mirror of the note above: a Button draws no icon unless told to, so
	// an icon that was the whole control has to be turned on explicitly or it
	// would disappear in the conversion.
	if ( out.icon ) {
		out.showIcon = true;
	}
	// `width` and `showTooltips` stay behind: width is the space around an icon
	// with no label beside it, and tooltips exist because an icon button has no
	// visible name, which this one now has. Standard needs no mention - it is a
	// block style registered on the icon button alone, so the className filter
	// above drops it for the same reason it drops any other style the target
	// does not register.
	return createBlock( BUTTON, out );
}
