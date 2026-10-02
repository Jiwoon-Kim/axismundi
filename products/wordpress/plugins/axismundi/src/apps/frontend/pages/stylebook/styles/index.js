import { Scaffold } from '../../../foundations/layout/scaffold';
import { Elevation } from '../../../components/material/elevation';
import { Icon, IconPlaceholder } from '../../../components/material/icon';
import { useEffect, useRef, useState } from '@wordpress/element';
import './styles.css';

/*
 * Local adaptation of Gutenberg's StyleBookPreview category and color-group
 * structure. This app renders M3 token contracts instead of block examples.
 * Source: packages/editor/src/components/style-book/{index,examples,constants}.
 */
const TYPE_CATEGORIES = [
	{
		name: 'Display',
		styles: [ 'large', 'medium', 'small' ],
	},
	{
		name: 'Headline',
		styles: [ 'large', 'medium', 'small' ],
	},
	{
		name: 'Title',
		styles: [ 'large', 'medium', 'small' ],
	},
	{
		name: 'Body',
		styles: [ 'large', 'medium', 'small' ],
	},
	{
		name: 'Label',
		styles: [ 'large', 'medium', 'small' ],
	},
];

const PALETTES = [
	{
		name: 'Primary',
		slug: 'primary',
		tones: [ 0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 95, 98, 99, 100 ],
	},
	{
		name: 'Secondary',
		slug: 'secondary',
		tones: [ 0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 95, 98, 99, 100 ],
	},
	{
		name: 'Tertiary',
		slug: 'tertiary',
		tones: [ 0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 95, 98, 99, 100 ],
	},
	{
		name: 'Error',
		slug: 'error',
		tones: [ 0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 95, 98, 99, 100 ],
	},
	{
		name: 'Neutral',
		slug: 'neutral',
		tones: [ 0, 4, 6, 10, 12, 17, 20, 22, 24, 30, 40, 50, 60, 70, 80, 87, 90, 92, 94, 95, 96, 98, 99, 100 ],
	},
	{
		name: 'Neutral variant',
		slug: 'neutral-variant',
		tones: [ 0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 95, 98, 99, 100 ],
	},
];

const ACCENT_COLUMNS = [
	[ 'primary', 'on-primary', 'primary-container', 'on-primary-container' ],
	[ 'secondary', 'on-secondary', 'secondary-container', 'on-secondary-container' ],
	[ 'tertiary', 'on-tertiary', 'tertiary-container', 'on-tertiary-container' ],
	[ 'error', 'on-error', 'error-container', 'on-error-container' ],
];

const SURFACE_ROWS = {
	top: [ 'surface-dim', 'surface', 'surface-bright', 'surface-variant' ],
	containers: [
		'surface-container-lowest',
		'surface-container-low',
		'surface-container',
		'surface-container-high',
		'surface-container-highest',
	],
	boundaries: [ 'on-surface', 'on-surface-variant', 'outline', 'outline-variant' ],
	inverse: [ 'inverse-surface', 'inverse-on-surface', 'inverse-primary' ],
	neutral: [ 'scrim', 'shadow' ],
};

const ROLE_TONES = {
	light: {
		primary: 'P-40', 'on-primary': 'P-100', 'primary-container': 'P-90', 'on-primary-container': 'P-30',
		secondary: 'S-40', 'on-secondary': 'S-100', 'secondary-container': 'S-90', 'on-secondary-container': 'S-30',
		tertiary: 'T-40', 'on-tertiary': 'T-100', 'tertiary-container': 'T-90', 'on-tertiary-container': 'T-30',
		error: 'E-40', 'on-error': 'E-100', 'error-container': 'E-90', 'on-error-container': 'E-30',
		'surface-dim': 'N-87', surface: 'N-98', 'surface-bright': 'N-98', 'surface-variant': 'NV-90',
		'surface-container-lowest': 'N-100', 'surface-container-low': 'N-96', 'surface-container': 'N-94', 'surface-container-high': 'N-92', 'surface-container-highest': 'N-90',
		'on-surface': 'N-10', 'on-surface-variant': 'NV-30', outline: 'NV-50', 'outline-variant': 'NV-80',
		'inverse-surface': 'N-20', 'inverse-on-surface': 'N-95', 'inverse-primary': 'P-80', scrim: 'N-0', shadow: 'N-0',
	},
	dark: {
		primary: 'P-80', 'on-primary': 'P-20', 'primary-container': 'P-30', 'on-primary-container': 'P-90',
		secondary: 'S-80', 'on-secondary': 'S-20', 'secondary-container': 'S-30', 'on-secondary-container': 'S-90',
		tertiary: 'T-80', 'on-tertiary': 'T-20', 'tertiary-container': 'T-30', 'on-tertiary-container': 'T-90',
		error: 'E-80', 'on-error': 'E-20', 'error-container': 'E-30', 'on-error-container': 'E-90',
		'surface-dim': 'N-6', surface: 'N-6', 'surface-bright': 'N-24', 'surface-variant': 'NV-30',
		'surface-container-lowest': 'N-4', 'surface-container-low': 'N-10', 'surface-container': 'N-12', 'surface-container-high': 'N-17', 'surface-container-highest': 'N-22',
		'on-surface': 'N-90', 'on-surface-variant': 'NV-80', outline: 'NV-60', 'outline-variant': 'NV-30',
		'inverse-surface': 'N-90', 'inverse-on-surface': 'N-20', 'inverse-primary': 'P-40', scrim: 'N-0', shadow: 'N-0',
	},
};

const ICON_AXIS_SAMPLES = [
	{ name: 'search', label: 'Baseline', fill: 0, weight: 400, grade: 0, size: 48 },
	{ name: 'favorite', label: 'Filled', fill: 1, weight: 400, grade: 0, size: 48 },
	{ name: 'menu', label: 'Light', fill: 0, weight: 100, grade: -25, size: 48 },
	{ name: 'notifications', label: 'Emphasized', fill: 0, weight: 700, grade: 200, size: 48 },
];

const ICON_BOX_SIZES = [ 20, 24, 40, 48 ];

const ELEVATION_LEVELS = [ 0, 1, 2, 3, 4, 5 ];

function titleCase( value ) {
	return value.replace( /(^|-)\w/g, ( character ) => character.toUpperCase() );
}

function roleName( role ) {
	return role.split( '-' ).map( ( word ) => word[ 0 ].toUpperCase() + word.slice( 1 ) ).join( ' ' );
}

function getThemeScheme() {
	const theme = document.documentElement.dataset.theme;
	if ( 'dark' === theme ) {
		return 'dark';
	}
	if ( 'light' === theme ) {
		return 'light';
	}
	return window.matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light';
}

function useThemeScheme() {
	const [ scheme, setScheme ] = useState( getThemeScheme );

	useEffect( () => {
		const media = window.matchMedia( '(prefers-color-scheme: dark)' );
		const update = () => setScheme( getThemeScheme() );
		const observer = new MutationObserver( update );

		observer.observe( document.documentElement, { attributes: true, attributeFilter: [ 'data-theme' ] } );
		media.addEventListener( 'change', update );

		return () => {
			observer.disconnect();
			media.removeEventListener( 'change', update );
		};
	}, [] );

	return scheme;
}

function typeTokenName( role, emphasized, property ) {
	return `--md-sys-typescale-${ emphasized ? 'emphasized-' : '' }${ role }-${ property }`;
}

function TypeStyle( { category, size, emphasized } ) {
	const role = `${ category.toLowerCase() }-${ size }`;
	const styleName = `${ category }${ titleCase( size ) }${ emphasized ? 'Emphasized' : '' }`;
	const specimenRef = useRef();
	const [ metrics, setMetrics ] = useState();
	const style = {
		fontFamily: `var( ${ typeTokenName( role, emphasized, 'font' ) } )`,
		fontSize: `var( ${ typeTokenName( role, emphasized, 'size' ) } )`,
		fontWeight: `var( ${ typeTokenName( role, emphasized, 'weight' ) } )`,
		letterSpacing: `var( ${ typeTokenName( role, emphasized, 'tracking' ) } )`,
		lineHeight: `var( ${ typeTokenName( role, emphasized, 'line-height' ) } )`,
	};

	useEffect( () => {
		if ( ! specimenRef.current ) {
			return;
		}

		const computed = window.getComputedStyle( specimenRef.current );
		setMetrics( {
			size: computed.fontSize.replace( 'px', '' ),
			lineHeight: computed.lineHeight.replace( 'px', '' ),
			weight: computed.fontWeight,
			width: computed.fontStretch.replace( '%', '' ),
			tracking: 'normal' === computed.letterSpacing ? '0' : computed.letterSpacing,
		} );
	}, [] );

	return (
		<article className="ax-stylebook-styles__type-style">
			<p className="ax-stylebook-styles__type-name" ref={ specimenRef } style={ style }>{ styleName }</p>
			<p className="ax-stylebook-styles__type-metadata">
				<span>{ `${ category } / ${ titleCase( size ) } / ${ emphasized ? 'Emphasized' : 'Primary' }` }</span>
				<span aria-hidden="true">&#8226;</span>
				<span>Roboto Flex</span>
				<span>{ metrics ? `${ metrics.size }/${ metrics.lineHeight }` : '...' }</span>
				<span>{ metrics?.weight ?? '...' }</span>
				<span>{ metrics?.width ?? '...' }</span>
				<span>{ metrics?.tracking ?? '...' }</span>
			</p>
		</article>
	);
}

function TypeSet( { emphasized } ) {
	return (
		<section className="ax-stylebook-styles__type-set" aria-labelledby={ `ax-stylebook-${ emphasized ? 'emphasized' : 'baseline' }` }>
			<h2 id={ `ax-stylebook-${ emphasized ? 'emphasized' : 'baseline' }` }>{ emphasized ? 'Emphasis' : 'Baseline' }</h2>
			<div className="ax-stylebook-styles__type-list">
				{ TYPE_CATEGORIES.map( ( category ) => (
					category.styles.map( ( size ) => (
						<TypeStyle category={ category.name } emphasized={ emphasized } key={ `${ category.name }-${ size }` } size={ size } />
					) )
				) ) }
			</div>
		</section>
	);
}

function TonalPalette( { name, slug, tones } ) {
	return (
		<section className="ax-stylebook-styles__palette" aria-labelledby={ `ax-stylebook-palette-${ slug }` }>
			<h3 id={ `ax-stylebook-palette-${ slug }` }>{ name }</h3>
			<div className="ax-stylebook-styles__tones" style={ { '--ax-tone-count': tones.length } }>
				{ tones.map( ( tone ) => {
					const token = `--md-ref-palette-${ slug }-${ tone }`;
					return (
						<div className="ax-stylebook-styles__tone" key={ token } style={ { backgroundColor: `var( ${ token } )`, color: tone < 60 ? 'var( --md-ref-palette-neutral-100 )' : 'var( --md-ref-palette-neutral-0 )' } }>
							<span>{ tone }</span>
						</div>
					);
				} ) }
			</div>
		</section>
	);
}

function foregroundRole( role ) {
	if ( role.startsWith( 'on-' ) ) {
		return role.slice( 3 );
	}
	if ( role.endsWith( '-container' ) ) {
		return `on-${ role }`;
	}
	if ( [ 'primary', 'secondary', 'tertiary', 'error' ].includes( role ) ) {
		return `on-${ role }`;
	}
	if ( 'inverse-surface' === role ) {
		return 'inverse-on-surface';
	}
	if ( 'inverse-on-surface' === role ) {
		return 'inverse-surface';
	}
	if ( 'inverse-primary' === role ) {
		return 'inverse-surface';
	}
	return 'on-surface';
}

function SchemeRole( { role, scheme } ) {
	const token = `--md-sys-color-${ role }`;
	return (
		<div className="ax-stylebook-styles__scheme-role" style={ { backgroundColor: `var( ${ token } )`, color: `var( --md-sys-color-${ foregroundRole( role ) } )` } }>
			<span>{ roleName( role ) }</span>
			<span>{ ROLE_TONES[ scheme ][ role ] }</span>
		</div>
	);
}

function SchematicGroup( { scheme } ) {
	return (
		<section className="ax-stylebook-styles__schematic" aria-label={ `${ scheme } color scheme` }>
			<div className="ax-stylebook-styles__accent-columns">
				{ ACCENT_COLUMNS.map( ( roles ) => (
					<div className="ax-stylebook-styles__accent-column" key={ roles[ 0 ] }>
						{ roles.map( ( role ) => <SchemeRole key={ role } role={ role } scheme={ scheme } /> ) }
					</div>
				) ) }
			</div>
			<div className="ax-stylebook-styles__surface-layout">
				<div className="ax-stylebook-styles__surface-main">
					<div className="ax-stylebook-styles__surface-row ax-stylebook-styles__surface-row--top">
						{ SURFACE_ROWS.top.map( ( role ) => <SchemeRole key={ role } role={ role } scheme={ scheme } /> ) }
					</div>
					<div className="ax-stylebook-styles__surface-row ax-stylebook-styles__surface-row--containers">
						{ SURFACE_ROWS.containers.map( ( role ) => <SchemeRole key={ role } role={ role } scheme={ scheme } /> ) }
					</div>
					<div className="ax-stylebook-styles__surface-row ax-stylebook-styles__surface-row--boundaries">
						{ SURFACE_ROWS.boundaries.map( ( role ) => <SchemeRole key={ role } role={ role } scheme={ scheme } /> ) }
					</div>
				</div>
				<div className="ax-stylebook-styles__inverse-column">
					{ SURFACE_ROWS.inverse.map( ( role ) => <SchemeRole key={ role } role={ role } scheme={ scheme } /> ) }
				</div>
			</div>
			<div className="ax-stylebook-styles__neutral-roles">
				{ SURFACE_ROWS.neutral.map( ( role ) => <SchemeRole key={ role } role={ role } scheme={ scheme } /> ) }
			</div>
		</section>
	);
}

function IconAxisSample( { fill, grade, label, name, size, weight } ) {
	const specimenRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const icon = specimenRef.current?.querySelector( '.ax-icon' );
		if ( ! icon ) {
			return;
		}

		const computed = window.getComputedStyle( icon );
		setMetrics( {
			box: `${ computed.inlineSize } x ${ computed.blockSize }`,
			opticalSizing: computed.fontOpticalSizing,
			variation: computed.fontVariationSettings,
			weight: computed.fontWeight,
		} );
	}, [] );

	return (
		<article className="ax-stylebook-styles__icon-sample">
			<div className="ax-stylebook-styles__icon-glyph" ref={ specimenRef }>
				<Icon fill={ fill } grade={ grade } name={ name } size={ size } weight={ weight } />
			</div>
			<div className="ax-stylebook-styles__icon-details">
				<h3>{ label }</h3>
				<p>{ name }</p>
				<p>{ `FILL ${ fill } · wght ${ weight } · GRAD ${ grade }` }</p>
				<p>{ metrics ? `${ metrics.box } · wght ${ metrics.weight } · opsz ${ metrics.opticalSizing } · ${ metrics.variation }` : 'Measuring computed icon values...' }</p>
			</div>
		</article>
	);
}

function IconBoxReference() {
	return (
		<section className="ax-stylebook-styles__icon-box-reference" aria-labelledby="ax-stylebook-icon-box-title">
			<header className="ax-stylebook-styles__group-header">
				<p className="ax-stylebook-styles__group-kicker">Rendering contract</p>
				<h2 id="ax-stylebook-icon-box-title">Glyph boxes</h2>
			</header>
			<div className="ax-stylebook-styles__icon-boxes">
				{ ICON_BOX_SIZES.map( ( size ) => (
					<div className="ax-stylebook-styles__icon-box" key={ size }>
						<Icon name="home" size={ size } />
						<span>{ `${ size }px` }</span>
					</div>
				) ) }
			</div>
			<div className="ax-stylebook-styles__icon-state-row">
				<div>
					<IconPlaceholder size={ 40 } />
					<span>Reserved placeholder</span>
				</div>
				<div>
					<Icon hidden name="visibility_off" size={ 40 } />
					<span>Hidden glyph</span>
				</div>
			</div>
		</section>
	);
}

function IconReference() {
	return (
		<section className="ax-stylebook-styles__section" id="icons" aria-labelledby="ax-stylebook-icons-title">
			<header className="ax-stylebook-styles__section-header">
				<p className="ax-stylebook-styles__eyebrow">Material Symbols Outlined</p>
				<h1 id="ax-stylebook-icons-title">Icons</h1>
			</header>
			<section className="ax-stylebook-styles__icon-axis-reference" aria-labelledby="ax-stylebook-icon-axis-title">
				<header className="ax-stylebook-styles__group-header">
					<p className="ax-stylebook-styles__group-kicker">Variable font axes</p>
					<h2 id="ax-stylebook-icon-axis-title">Symbol treatments</h2>
				</header>
				<div className="ax-stylebook-styles__icon-samples">
					{ ICON_AXIS_SAMPLES.map( ( sample ) => <IconAxisSample { ...sample } key={ sample.label } /> ) }
				</div>
			</section>
			<IconBoxReference />
		</section>
	);
}

function ElevationSample( { level } ) {
	const surfaceRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const layer = surfaceRef.current?.querySelector( '.ax-elevation' );
		if ( ! layer ) {
			return;
		}

		setMetrics( {
			ambient: window.getComputedStyle( layer, '::after' ).boxShadow,
			key: window.getComputedStyle( layer, '::before' ).boxShadow,
		} );
	}, [] );

	return (
		<article className="ax-stylebook-styles__elevation-surface" ref={ surfaceRef }>
			<Elevation level={ level } />
			<div className="ax-stylebook-styles__elevation-details">
				<h3>{ `Level ${ level }` }</h3>
				<p>{ level <= 3 ? 'Resting level' : 'Interaction level' }</p>
				<p>{ metrics ? `Key: ${ metrics.key }` : 'Measuring key shadow...' }</p>
				<p>{ metrics ? `Ambient: ${ metrics.ambient }` : 'Measuring ambient shadow...' }</p>
			</div>
		</article>
	);
}

function ElevationReference() {
	return (
		<section className="ax-stylebook-styles__section" id="elevation" aria-labelledby="ax-stylebook-elevation-title">
			<header className="ax-stylebook-styles__section-header">
				<p className="ax-stylebook-styles__eyebrow">Material elevation</p>
				<h1 id="ax-stylebook-elevation-title">Elevation levels</h1>
			</header>
			<section className="ax-stylebook-styles__elevation-reference" aria-label="Elevation rendering contract">
				<header className="ax-stylebook-styles__group-header">
					<p className="ax-stylebook-styles__group-kicker">Visual layer, not stacking order</p>
					<h2>Shadow geometry</h2>
				</header>
				<div className="ax-stylebook-styles__elevation-samples">
					{ ELEVATION_LEVELS.map( ( level ) => <ElevationSample key={ level } level={ level } /> ) }
				</div>
			</section>
		</section>
	);
}

function TypographyReference() {
	return (
		<section className="ax-stylebook-styles__section" id="typography" aria-labelledby="ax-stylebook-typography-title">
			<header className="ax-stylebook-styles__section-header">
				<h1 id="ax-stylebook-typography-title">Type scale</h1>
			</header>
			<div className="ax-stylebook-styles__type-sets">
				<TypeSet emphasized={ false } />
				<TypeSet emphasized />
			</div>
		</section>
	);
}

function ColorReference() {
	const scheme = useThemeScheme();

	return (
		<section className="ax-stylebook-styles__section" id="colors" aria-labelledby="ax-stylebook-colors-title">
			<header className="ax-stylebook-styles__section-header">
				<p className="ax-stylebook-styles__eyebrow">Color guidance</p>
				<h1 id="ax-stylebook-colors-title">Color system</h1>
			</header>
			<section className="ax-stylebook-styles__color-section" aria-labelledby="ax-stylebook-tonal-palettes-title">
				<header className="ax-stylebook-styles__group-header">
					<p className="ax-stylebook-styles__group-kicker">Reference tokens</p>
					<h2 id="ax-stylebook-tonal-palettes-title">Tonal palettes</h2>
				</header>
				<div className="ax-stylebook-styles__palettes">
					{ PALETTES.map( ( palette ) => <TonalPalette { ...palette } key={ palette.slug } /> ) }
				</div>
			</section>
			<section className="ax-stylebook-styles__color-section" aria-labelledby="ax-stylebook-scheme-groups-title">
				<header className="ax-stylebook-styles__group-header">
					<p className="ax-stylebook-styles__group-kicker">Current theme: { titleCase( scheme ) }</p>
					<h2 id="ax-stylebook-scheme-groups-title">Schematic group</h2>
				</header>
				<SchematicGroup scheme={ scheme } />
			</section>
		</section>
	);
}

/**
 * M3 token reference surface modeled after Gutenberg's Style Book categories.
 *
 * @return {import('@wordpress/element').ReactNode} Styles reference page.
 */
export function StylebookStylesPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--styles">
			<div className="ax-stylebook-styles">
				<nav className="ax-stylebook-styles__navigation" aria-label="Stylebook sections">
					<a href="#typography">Typography</a>
					<a href="#colors">Colors</a>
					<a href="#icons">Icons</a>
					<a href="#elevation">Elevation</a>
				</nav>
				<TypographyReference />
				<ColorReference />
				<IconReference />
				<ElevationReference />
			</div>
		</Scaffold>
	);
}
