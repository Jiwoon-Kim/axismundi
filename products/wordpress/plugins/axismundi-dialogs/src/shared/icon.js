/**
 * Editor side of the shared icon rendering (includes/icon.php is the server
 * side). One interface, one backend per source, and no block box: each block
 * places the painted element itself.
 *
 * An icon reference is the same descriptor on both sides:
 *
 *   { source: 'font' | 'registry', name, class, flipHorizontal, flipVertical, rotation }
 *
 * Both sides draw the same element: a <span class="ax-icon">, holding the
 * glyph or the SVG (span>svg). The server wraps a registry SVG in it; the
 * editor has to anyway, since the SVG reaches it as REST markup it cannot put
 * classes or styles on.
 */
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { safeHTML } from '@wordpress/dom';
import { SVG, Rect, Path } from '@wordpress/primitives';

function joinClasses( ...classes ) {
	return classes.filter( Boolean ).join( ' ' );
}

/**
 * A glyph name as the server renders it: ligature names are lower-case words
 * joined by underscores, and anything else could only ever show as text.
 * Mirrors axismundi_dialogs_get_font_icon(), so the editor draws what the page
 * will - a typed "Arrow-Back!" is "arrowback" on both.
 *
 * @param {string} name Stored glyph name.
 * @return {string} Renderable name; '' when nothing is left.
 */
export function glyphName( name ) {
	return String( name || '' ).toLowerCase().replace( /[^a-z0-9_]/g, '' );
}

// The provider's class as the server sanitises it (sanitize_html_class), with
// the same fallback.
function providerClass( className ) {
	return String( className || '' ).replace( /%[a-fA-F0-9][a-fA-F0-9]/g, '' ).replace( /[^A-Za-z0-9_-]/g, '' ) ||
		'material-symbols-outlined';
}

/**
 * The stored transforms, the same for both sources as on the server
 * (includes/icon.php): flip as classes, rotation as the individual `rotate`
 * property, so `transform` stays free for state (assets/icon.css).
 *
 * @param {Object} icon Icon reference.
 * @return {string} Classes, or ''.
 */
export function flipClasses( icon ) {
	return joinClasses(
		icon.flipHorizontal && 'is-flip-horizontal',
		icon.flipVertical && 'is-flip-vertical'
	);
}

/**
 * @param {Object} icon Icon reference.
 * @return {Object} Inline style, empty with no rotation.
 */
export function rotationStyle( icon ) {
	return icon.rotation ? { rotate: `${ icon.rotation }deg` } : {};
}

/**
 * The registry record an icon reference points at, from the entity core/icon
 * previews from, so the editor draws exactly the SVG the server will render.
 *
 * @param {Object} icon Icon reference.
 * @return {{content: string, resolved: boolean}} SVG markup, and whether the lookup finished.
 */
export function useIconRecord( icon ) {
	const isRegistry = icon.source === 'registry';
	const { name } = icon;
	return useSelect(
		( select ) => {
			if ( ! isRegistry || ! name ) {
				return { content: '', resolved: true };
			}
			const store = select( coreStore );
			const record = store.getEntityRecord( 'root', 'icon', name );
			return {
				content: record?.content || '',
				resolved: store.hasFinishedResolution( 'getEntityRecord', [ 'root', 'icon', name ] ),
			};
		},
		[ isRegistry, name ]
	);
}

/**
 * core/icon's IconPlaceholder (edit.js), unchanged but for the class prefix:
 * the editor-only empty state.
 *
 * @param {Object} props           Props.
 * @param {string} props.className Classes.
 * @param {Object} props.style     Inline style.
 */
export function IconPlaceholder( { className, style } ) {
	return (
		<SVG
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 60 60"
			preserveAspectRatio="none"
			fill="none"
			aria-hidden="true"
			className={ joinClasses( 'ax-icon', 'ax-dialog-icon__placeholder', className ) }
			style={ style }
		>
			<Rect width="60" height="60" fill="currentColor" fillOpacity={ 0.1 } />
			<Path
				vectorEffect="non-scaling-stroke"
				stroke="currentColor"
				strokeOpacity={ 0.25 }
				d="M60 60 0 0"
			/>
		</SVG>
	);
}

/**
 * The painted element for an icon reference, or null when there is nothing to
 * draw (no name, or a registry name that does not resolve). The caller decides
 * what to show then.
 *
 * @param {Object} props           Props.
 * @param {Object} props.icon      Icon reference.
 * @param {Object} props.record    useIconRecord() result, for a registry icon.
 * @param {string} props.className Classes for the painted element.
 * @param {Object} props.style     Inline style for the painted element.
 * @param {string} props.label     Accessible name; none makes it decorative.
 */
export function IconElement( { icon, record, className, style, label } ) {
	// The server's rule, for both sources (includes/icon.php).
	const aria = label ? { role: 'img', 'aria-label': label } : { 'aria-hidden': 'true' };

	if ( icon.source === 'registry' ) {
		if ( ! record?.content ) {
			return null;
		}
		return (
			<span
				className={ joinClasses( 'ax-icon', 'ax-icon--svg', className, flipClasses( icon ) ) }
				style={ { ...style, ...rotationStyle( icon ) } }
				{ ...aria }
				// The REST record is sanitised by the registry; safeHTML is the
				// same second pass core/icon applies before rendering it.
				dangerouslySetInnerHTML={ { __html: safeHTML( record.content ) } }
			/>
		);
	}

	const name = glyphName( icon.name );
	if ( ! name ) {
		return null;
	}
	return (
		<span
			className={ joinClasses( 'ax-icon', providerClass( icon.class ), 'notranslate', className, flipClasses( icon ) ) }
			translate="no"
			{ ...aria }
			style={ { ...style, ...rotationStyle( icon ) } }
		>
			{ name }
		</span>
	);
}
