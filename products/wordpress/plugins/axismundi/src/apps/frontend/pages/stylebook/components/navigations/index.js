import { Icon } from '../../../../components/material/icon';
import { NavigationItem } from '../../../../components/navigations/navigation-item';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import { useEffect, useRef, useState } from '@wordpress/element';
import '../../stylebook-page.css';
import './navigations.css';

/*
 * Checked against `products/styleguide/_data/navigation_item.yml`.
 *
 * This page will grow two more sections -- `#navigation-bar` and
 * `#navigation-rail` -- because the two are one navigation decision across
 * window size classes, and the item's whole contract is about what they share.
 * Splitting them across pages would make the specimen below impossible to read
 * in one place.
 *
 * The specimen that matters is `HostSuppliedSample`: one component, three
 * contexts, three different indicator heights. That is the contract, measured.
 */

/* Resolve a custom property into a real colour by painting a probe with it. */
function resolved( host, property ) {
	const probe = document.createElement( 'span' );
	probe.style.color = window.getComputedStyle( host ).getPropertyValue( property ).trim();
	host.appendChild( probe );
	const value = window.getComputedStyle( probe ).color;
	probe.remove();
	return value;
}

/*
 * MEASURED, AND IT CAUGHT A LIE IN THIS PAGE. Comparing two computed colours as
 * strings reported the active indicator as a mismatch:
 *
 *   color-mix() serialises as  color(srgb 0.290196 0.266667 0.345098)
 *   a painted probe as         rgb(74, 68, 88)
 *
 * which is the same colour twice -- 0.290196 x 255 is 74. The component was
 * right and the readout was wrong, so the channels are compared as numbers.
 */
function channels( value ) {
	const parts = String( value ).match( /[\d.]+/g )?.slice( 0, 3 ) ?? [];
	if ( 3 > parts.length ) {
		return null;
	}
	// `color(srgb …)` carries 0-1 fractions; `rgb()` carries 0-255.
	const scale = String( value ).includes( 'srgb' ) ? 255 : 1;
	return parts.map( ( part ) => Math.round( parseFloat( part ) * scale ) ).join( ',' );
}

function sameColor( a, b ) {
	const left = channels( a );
	return null !== left && left === channels( b );
}

function Group( { children, kicker, title, id } ) {
	return (
		<section className="ax-stylebook-page__group" id={ id } aria-label={ title }>
			<header className="ax-stylebook-page__group-header">
				<p className="ax-stylebook-page__group-kicker">{ kicker }</p>
				<h2>{ title }</h2>
			</header>
			{ children }
		</section>
	);
}

/*
 * Vertical is the shared case: every figure below is published identically by
 * `md.comp.nav-bar.item.vertical.*` and `md.comp.nav-rail.item.vertical.*`, so
 * the component owns them and no host has to pass anything in.
 */
function VerticalSample() {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const item = hostRef.current?.querySelector( '.ax-navigation-item' );
		const indicator = item?.querySelector( '.ax-navigation-item__indicator' );
		const label = item?.querySelector( '.ax-navigation-item__label' );
		if ( ! indicator || ! label ) {
			return;
		}
		const box = window.getComputedStyle( indicator );
		setMetrics( {
			height: Math.round( parseFloat( box.blockSize ) ),
			width: Math.round( parseFloat( box.inlineSize ) ),
			gap: Math.round( parseFloat( window.getComputedStyle( item ).rowGap ) ),
			/*
			 * The structural claim, not a style one: M3 puts the vertical label
			 * "below the icon and indicator", so it must not be inside the
			 * element that paints the pill.
			 */
			labelInsideIndicator: indicator.contains( label ),
		} );
	}, [] );

	return (
		<div ref={ hostRef }>
			<div className="ax-stylebook-navigations__row">
				<NavigationItem icon={ <Icon name="home" /> } label="Home" href="#home" active />
				<NavigationItem icon={ <Icon name="search" /> } label="Search" href="#search" />
				<NavigationItem icon={ <Icon name="notifications" /> } label="Alerts" href="#alerts" />
			</div>
			<p className="ax-stylebook-page__note">
				{ metrics
					? `indicator ${ metrics.height }x${ metrics.width }px (published 32x56) · icon-label gap ${ metrics.gap }px (published 4, space50) · label inside the indicator: ${ metrics.labelInsideIndicator } (published: below it, so false)`
					: 'Measuring...' }
			</p>
		</div>
	);
}

/*
 * The whole reason this component was specified before either host. The bar and
 * the rail publish different horizontal figures, so the component publishes no
 * horizontal default -- a host supplies them, and an item with no host renders
 * visibly unfinished rather than quietly using the bar's numbers inside a rail.
 */
const HOSTS = [
	{
		name: 'no host',
		expected: 'no published figure',
		style: {},
	},
	{
		name: 'nav-bar',
		expected: '40px, space50, label-medium',
		style: {
			'--md-comp-navigation-item-horizontal-indicator-height': '40px',
			'--md-comp-navigation-item-horizontal-icon-label-space': 'var( --md-sys-measurement-space50 )',
			'--md-comp-navigation-item-horizontal-label-text-font': 'var( --md-sys-typescale-label-medium-font )',
			'--md-comp-navigation-item-horizontal-label-text-size': 'var( --md-sys-typescale-label-medium-size )',
			'--md-comp-navigation-item-horizontal-label-text-weight': 'var( --md-sys-typescale-label-medium-weight )',
			'--md-comp-navigation-item-horizontal-label-text-tracking': 'var( --md-sys-typescale-label-medium-tracking )',
			'--md-comp-navigation-item-horizontal-label-text-line-height': 'var( --md-sys-typescale-label-medium-line-height )',
		},
	},
	{
		name: 'nav-rail',
		expected: '56px, space100, label-large',
		style: {
			'--md-comp-navigation-item-horizontal-indicator-height': '56px',
			'--md-comp-navigation-item-horizontal-icon-label-space': 'var( --md-sys-measurement-space100 )',
			'--md-comp-navigation-item-horizontal-label-text-font': 'var( --md-sys-typescale-label-large-font )',
			'--md-comp-navigation-item-horizontal-label-text-size': 'var( --md-sys-typescale-label-large-size )',
			'--md-comp-navigation-item-horizontal-label-text-weight': 'var( --md-sys-typescale-label-large-weight )',
			'--md-comp-navigation-item-horizontal-label-text-tracking': 'var( --md-sys-typescale-label-large-tracking )',
			'--md-comp-navigation-item-horizontal-label-text-line-height': 'var( --md-sys-typescale-label-large-line-height )',
		},
	},
];

function HostSuppliedSample() {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const root = hostRef.current;
		if ( ! root ) {
			return;
		}
		const scale = ( property ) =>
			window.getComputedStyle( root ).getPropertyValue( property ).trim();

		setMetrics( {
			medium: scale( '--md-sys-typescale-label-medium-size' ),
			large: scale( '--md-sys-typescale-label-large-size' ),
			rows: HOSTS.map( ( { name } ) => {
				const cell = root.querySelector( `[data-host="${ name }"]` );
				const item = cell?.querySelector( '.ax-navigation-item' );
				const indicator = item?.querySelector( '.ax-navigation-item__indicator' );
				const label = item?.querySelector( '.ax-navigation-item__label' );
				return {
					name,
					height: Math.round( parseFloat( window.getComputedStyle( indicator ).blockSize ) ),
					gap: window.getComputedStyle( indicator ).columnGap,
					size: window.getComputedStyle( label ).fontSize,
					labelInsideIndicator: indicator.contains( label ),
				};
			} ),
		} );
	}, [] );

	return (
		<div ref={ hostRef }>
			<div className="ax-stylebook-navigations__hosts">
				{ HOSTS.map( ( { name, expected, style } ) => (
					<div className="ax-stylebook-navigations__host" data-host={ name } key={ name } style={ style }>
						<p className="ax-stylebook-page__note">
							{ name } &mdash; { expected }
						</p>
						<NavigationItem
							icon={ <Icon name="forum" /> }
							label="Community"
							href="#community"
							orientation="horizontal"
							active
						/>
					</div>
				) ) }
			</div>
			<p className="ax-stylebook-page__note">
				{ metrics
					? metrics.rows
						.map( ( row ) => `${ row.name }: ${ row.height }px / gap ${ row.gap } / ${ row.size }` )
						.join( ' · ' ) +
					  ` — label-medium is ${ metrics.medium }, label-large is ${ metrics.large }; label inside the indicator: ${ metrics.rows.every( ( row ) => row.labelInsideIndicator ) }`
					: 'Measuring...' }
			</p>
		</div>
	);
}

/*
 * Colour is read by resolving the role on the element rather than by comparing
 * against a hex, so a scheme change cannot make this specimen lie.
 */
function ActiveSample() {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const root = hostRef.current;
		const active = root?.querySelector( '[aria-current]' );
		const inactive = root?.querySelector( '.ax-navigation-item:not([aria-current])' );
		if ( ! active || ! inactive ) {
			return;
		}
		const indicator = active.querySelector( '.ax-navigation-item__indicator' );

		/*
		 * The theme registers `--md-icon-fill` and transitions it, so reading the
		 * axis straight after it applies returns the start value. Transitions are
		 * suppressed for the read and restored immediately -- the trap the
		 * conventions record, met here for real.
		 */
		const still = document.createElement( 'style' );
		still.textContent = '*{transition:none !important}';
		document.head.appendChild( still );
		const axis = ( item ) =>
			window.getComputedStyle( item.querySelector( '.ax-icon' ) ).fontVariationSettings;
		const activeAxis = axis( active );
		const inactiveAxis = axis( inactive );
		still.remove();

		setMetrics( {
			host: active.tagName.toLowerCase(),
			href: active.getAttribute( 'href' ),
			activeAxis,
			inactiveAxis,
			indicator: window.getComputedStyle( indicator ).backgroundColor,
			secondaryContainer: resolved( root, '--md-sys-color-secondary-container' ),
			activeLabel: window.getComputedStyle( active.querySelector( '.ax-navigation-item__label' ) ).color,
			secondary: resolved( root, '--md-sys-color-secondary' ),
			inactiveLabel: window.getComputedStyle( inactive.querySelector( '.ax-navigation-item__label' ) ).color,
			onSurfaceVariant: resolved( root, '--md-sys-color-on-surface-variant' ),
			/* Published as the same weight for both; no weight token is live. */
			activeWeight: window.getComputedStyle( active.querySelector( '.ax-navigation-item__label' ) ).fontWeight,
			inactiveWeight: window.getComputedStyle( inactive.querySelector( '.ax-navigation-item__label' ) ).fontWeight,
		} );
	}, [] );

	return (
		<div ref={ hostRef }>
			<div className="ax-stylebook-navigations__row">
				<NavigationItem icon={ <Icon name="home" /> } label="Active" href="#a" active />
				<NavigationItem icon={ <Icon name="search" /> } label="Inactive" href="#b" />
			</div>
			<p className="ax-stylebook-page__note">
				{ metrics
					? `indicator ${ channels( metrics.indicator ) } vs secondary-container ${ channels( metrics.secondaryContainer ) }${ sameColor( metrics.indicator, metrics.secondaryContainer ) ? '' : '  MISMATCH' } · active label vs secondary ${ channels( metrics.secondary ) }${ sameColor( metrics.activeLabel, metrics.secondary ) ? '' : '  MISMATCH' } · inactive label vs on-surface-variant ${ channels( metrics.onSurfaceVariant ) }${ sameColor( metrics.inactiveLabel, metrics.onSurfaceVariant ) ? '' : '  MISMATCH' } · weight ${ metrics.activeWeight }/${ metrics.inactiveWeight } (equal on purpose) · host <${ metrics.host } href="${ metrics.href }"> · icon axis active [${ metrics.activeAxis }] inactive [${ metrics.inactiveAxis }]`
					: 'Measuring...' }
			</p>
		</div>
	);
}

export function StylebookNavigationsPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--navigations">
			<div className="ax-stylebook-page">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/components/buttons">Buttons</a>
					<a href="/social/stylebook/components/icon-buttons">Icon buttons</a>
					<a href="/social/stylebook/components/button-groups">Button groups</a>
					<a href="/social/stylebook/components/split-buttons">Split buttons</a>
					<a href="/social/stylebook/components/cards">Cards</a>
					<a href="/social/stylebook/components/dividers">Dividers</a>
				</nav>

				<section
					className="ax-stylebook-page__section"
					id="navigations"
					aria-labelledby="ax-stylebook-navigations-title"
				>
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">Material component</p>
						<h1 id="ax-stylebook-navigations-title">Navigation</h1>
					</header>

					<Group
						id="navigation-item"
						kicker="M3 publishes no navigation item; its tokens live inside two hosts"
						title="Navigation item — vertical"
					>
						<VerticalSample />
						<p className="ax-stylebook-page__note">
							Every vertical figure is published identically by{ ' ' }
							<code>md.comp.nav-bar.item.vertical.*</code> and{ ' ' }
							<code>md.comp.nav-rail.item.vertical.*</code>, so the component owns them. The
							label sits below the indicator rather than inside it, which is a structural
							difference and not a style one &mdash; a label inside the element that paints
							the pill would be on the fill.
						</p>
					</Group>

					<Group
						id="navigation-item-horizontal"
						kicker="One component, three contexts, three indicator heights"
						title="Navigation item — horizontal is the host&rsquo;s"
					>
						<HostSuppliedSample />
						<p className="ax-stylebook-page__note">
							The bar publishes a 40dp indicator with <code>space50</code> and{ ' ' }
							<code>label-medium</code>; the rail publishes 56dp with <code>space100</code> and{ ' ' }
							<code>label-large</code>. There is no figure that is true of both, so the
							component publishes no horizontal default and reads the unqualified slot. The
							first cell has no host and is therefore unfinished on purpose: an auto-height
							indicator is visible, where a silent fallback to the bar&rsquo;s 40dp would be
							wrong in a rail and look right.
						</p>
					</Group>

					<Group
						id="navigation-item-active"
						kicker="Colour and the indicator are the whole of the published selected state"
						title="Navigation item — active"
					>
						<ActiveSample />
						<p className="ax-stylebook-page__note">
							The guidelines also ask for a bold label, and no live token carries it: the
							flexible namespaces publish no label weight, and the only one that exists is in
							the deprecated baseline namespace pointing at{ ' ' }
							<code>md.sys.typescale.label-medium.weight.prominent</code>, which the same table
							marks deprecated. So the two weights above are equal deliberately, and the active
							item is distinguished by the indicator and by colour &mdash; both published.
						</p>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
