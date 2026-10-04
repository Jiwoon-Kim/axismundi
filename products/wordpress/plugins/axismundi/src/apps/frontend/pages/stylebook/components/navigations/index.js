import { Icon } from '../../../../components/material/icon';
import { NavigationBar } from '../../../../components/navigations/nav-bar';
import { NavigationRail } from '../../../../components/navigations/nav-rail';
import { NavigationItem } from '../../../../components/navigations/navigation-item';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import { useEffect, useRef, useState } from '@wordpress/element';
import '../../stylebook-page.css';
import './navigations.css';

/*
 * Checked against `products/styleguide/_data/navigation_item.yml`.
 *
 * This page will grow two more sections -- `#nav-bar` and
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
		/*
		 * The label's own axis, which is where the active emphasis actually is.
		 * Read under the same suppression as the icon's: the theme registers and
		 * transitions these properties.
		 */
		const labelAxis = ( item ) =>
			window.getComputedStyle( item.querySelector( '.ax-navigation-item__label' ) )
				.fontVariationSettings;
		const activeLabelAxis = labelAxis( active );
		const inactiveLabelAxis = labelAxis( inactive );
		still.remove();

		setMetrics( {
			host: active.tagName.toLowerCase(),
			href: active.getAttribute( 'href' ),
			activeAxis,
			inactiveAxis,
			activeLabelAxis,
			inactiveLabelAxis,
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
					? `indicator ${ channels( metrics.indicator ) } vs secondary-container ${ channels( metrics.secondaryContainer ) }${ sameColor( metrics.indicator, metrics.secondaryContainer ) ? '' : '  MISMATCH' } · active label vs secondary ${ channels( metrics.secondary ) }${ sameColor( metrics.activeLabel, metrics.secondary ) ? '' : '  MISMATCH' } · inactive label vs on-surface-variant ${ channels( metrics.onSurfaceVariant ) }${ sameColor( metrics.inactiveLabel, metrics.onSurfaceVariant ) ? '' : '  MISMATCH' } · weight ${ metrics.activeWeight }/${ metrics.inactiveWeight } (equal: label-medium is already 500) · label axis active [${ metrics.activeLabelAxis }] inactive [${ metrics.inactiveLabelAxis }] · host <${ metrics.host } href="${ metrics.href }"> · icon axis active [${ metrics.activeAxis }] inactive [${ metrics.inactiveAxis }]`
					: 'Measuring...' }
			</p>
		</div>
	);
}

/*
 * The bar's own contract is arithmetic, so this specimen measures the arithmetic
 * rather than showing a picture of it. Two published sentences, two different
 * answers from the same component:
 *
 *   vertical    "divided into equal-width segments"
 *   horizontal  "a fixed width, so extra space is added to the ends"
 *
 * Equal-width is checked as the spread between the widest and narrowest item, so
 * that a label of a different length cannot make a passing readout. The
 * destinations deliberately have labels of unequal length for that reason.
 */
const DESTINATIONS = [
	{ id: 'home', label: 'Home', icon: <Icon name="home" />, href: '#home' },
	{ id: 'community', label: 'Community', icon: <Icon name="forum" />, href: '#community' },
	{ id: 'search', label: 'Search', icon: <Icon name="search" />, href: '#search' },
	{ id: 'alerts', label: 'Notifications', icon: <Icon name="notifications" />, href: '#alerts' },
];

function BarSample( { itemLayout } ) {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const bar = hostRef.current?.querySelector( '.ax-nav-bar' );
		if ( ! bar ) {
			return;
		}
		const box = bar.getBoundingClientRect();
		/*
		 * A ZERO-WIDTH VIEWPORT MAKES THIS READOUT LIE, and it lied once. With the
		 * browser pane hidden the viewport is 0x0, the page grid collapses, and
		 * every box here is sized by its own content instead of by the container.
		 * The spread then reads 0 and the slack reads 0/0 -- which is what a
		 * correct vertical bar looks like, so the line prints as a pass. The
		 * conventions record the 0x0 trap; this is it reached through layout
		 * rather than through a transition.
		 */
		if ( 0 === box.width ) {
			setMetrics( { void: true } );
			return;
		}
		const items = [ ...bar.querySelectorAll( '.ax-nav-bar__item' ) ];
		const widths = items.map( ( item ) => item.getBoundingClientRect().width );
		const first = items[ 0 ]?.getBoundingClientRect();
		const last = items[ items.length - 1 ]?.getBoundingClientRect();

		/*
		 * The gap between the INDICATORS, which is the separation a person
		 * actually sees. The gap between item boxes is zero in both orientations,
		 * and reporting only that made the horizontal row look like it had the
		 * items touching -- which is how a wrong implementation passed a reading.
		 */
		const pills = items.map( ( item ) =>
			item.querySelector( '.ax-navigation-item__indicator' ).getBoundingClientRect()
		);

		setMetrics( {
			height: Math.round( box.height ),
			/* Does the container fill its parent, as "100% of the window width" asks. */
			fillsParent:
				Math.round( box.width ) ===
				Math.round( hostRef.current.getBoundingClientRect().width ),
			spread: Math.round( Math.max( ...widths ) - Math.min( ...widths ) ),
			gap: window.getComputedStyle( bar ).columnGap,
			/*
			 * The slack, measured at both ends. Equal and non-zero is "extra
			 * space is added to the ends"; both zero is a divided container.
			 */
			/*
			 * The window-edge margin, which is what the published "Dynamic width"
			 * beside a horizontal row refers to: it is produced by the containers
			 * not growing, not declared.
			 */
			leading: Math.round( first.left - box.left ),
			trailing: Math.round( box.right - last.right ),
			grows: window.getComputedStyle( items[ 0 ] ).flexGrow,
			itemHeight: Math.round( first.height ),
			pillWidths: pills.map( ( pill ) => Math.round( pill.width ) ).join( '/' ),
			pillHeight: Math.round( pills[ 0 ].height ),
			pillGap: Math.round( pills[ 1 ].left - pills[ 0 ].right ),
			/* 6dp when vertical, 12dp when horizontal, both from the kit. */
			pillOffsetTop: Math.round( pills[ 0 ].top - first.top ),
			host: bar.tagName.toLowerCase(),
			label: bar.getAttribute( 'aria-label' ),
			current: bar.querySelectorAll( '[aria-current]' ).length,
			/* Elevation is a child layer, not a `box-shadow` on the container. */
			elevation: bar
				.querySelector( '.ax-elevation' )
				?.style.getPropertyValue( '--ax-elevation-resting-level' ),
		} );
	}, [ itemLayout ] );

	return (
		<div
			ref={ hostRef }
			className={
				'horizontal' === itemLayout ? 'ax-stylebook-navigations__medium-window' : undefined
			}
		>
			<NavigationBar
				destinations={ DESTINATIONS }
				activeId="community"
				label={ `Specimen, ${ itemLayout } items` }
				itemLayout={ itemLayout }
			/>
			<p className="ax-stylebook-page__note">
				{ metrics?.void
					? 'The container measures 0px wide, so every figure below it would be sized by its own content rather than by the container. Nothing is reported; open this page in a visible viewport.'
					: metrics
					? `bar ${ metrics.height }px (64 is the minimum, not a box) · spans the parent: ${ metrics.fillsParent } · item CONTAINER ${ metrics.itemHeight }px tall, width spread ${ metrics.spread }px · container gap ${ metrics.gap } · active INDICATOR ${ metrics.pillWidths }x${ metrics.pillHeight }px, ${ metrics.pillGap }px apart, ${ metrics.pillOffsetTop }px from the container's top · container flex-grow ${ metrics.grows }, window-edge margin ${ metrics.leading }/${ metrics.trailing }px · elevation level ${ metrics.elevation } · <${ metrics.host } aria-label="${ metrics.label }"> · aria-current count ${ metrics.current }`
					: 'Measuring...' }
			</p>
		</div>
	);
}

/*
 * The rail's own claims are structural, so this specimen checks structure. The
 * two that matter most are the ones no screenshot would catch: the target area
 * spans the full rail while the indicator hugs its content, and the arrow keys
 * are added to the tab order rather than replacing it.
 *
 * The arrow check moves focus for real and reads where it landed. Every item must
 * still carry `tabindex` 0 afterwards -- a roving `tabindex` would implement the
 * published `Arrows` row by deleting the published `Tab` row, which is the
 * mistake this repository already made once on the button group.
 */
function RailSample( { variant, alignment = 'top', narrow = false, divider = false, menu } ) {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const rail = hostRef.current?.querySelector( '.ax-nav-rail' );
		if ( ! rail ) {
			return;
		}
		const box = rail.getBoundingClientRect();
		if ( 0 === box.width ) {
			setMetrics( { void: true } );
			return;
		}
		const items = [ ...rail.querySelectorAll( '.ax-nav-rail__item' ) ];
		const indicator = items[ 0 ].querySelector( '.ax-navigation-item__indicator' );

		items[ 0 ].focus();
		const landed = ( key ) => {
			items[ 0 ].focus();
			items[ 0 ].dispatchEvent(
				new KeyboardEvent( 'keydown', { key, bubbles: true, cancelable: true } )
			);
			return items.indexOf( document.activeElement );
		};
		const down = landed( 'ArrowDown' );
		const up = ( () => {
			items[ 0 ].focus();
			items[ 0 ].dispatchEvent(
				new KeyboardEvent( 'keydown', { key: 'ArrowUp', bubbles: true, cancelable: true } )
			);
			return items.indexOf( document.activeElement );
		} )();
		document.activeElement?.blur();

		setMetrics( {
			width: Math.round( box.width ),
			height: Math.round( box.height ),
			/* The published rule, measured as two numbers rather than asserted. */
			itemWidth: Math.round( items[ 0 ].getBoundingClientRect().width ),
			/*
			 * The published 64dp minimum. Reported because it was recorded in the
			 * data file and not implemented, and the items rendered at their
			 * content height -- 52dp collapsed, and 56dp expanded, which looked
			 * like the published `short` figure while being the indicator's own
			 * height. Both numbers are shown so a coincidence cannot pass again.
			 */
			itemHeight: Math.round( items[ 0 ].getBoundingClientRect().height ),
			itemMinHeight: window.getComputedStyle( items[ 0 ] ).minBlockSize,
			indicatorWidth: Math.round( indicator.getBoundingClientRect().width ),
			indicatorHeight: Math.round( indicator.getBoundingClientRect().height ),
			/* Where the pill starts, relative to the rail's leading edge. */
			indicatorInset: Math.round(
				indicator.getBoundingClientRect().left - box.left
			),
			gap: window.getComputedStyle( rail.querySelector( '.ax-nav-rail__items' ) ).rowGap,
			headerGap: menu
				? window.getComputedStyle( rail.querySelector( '.ax-nav-rail__items' ) ).marginBlockStart
				: 'no header',
			/* Arrows move focus; index 1 down from 0, and no wrap upwards from 0. */
			arrowDown: down,
			arrowUpAtStart: up,
			tabStops: items.filter( ( item ) => 0 === item.tabIndex ).length,
			divider: rail.querySelectorAll( '.ax-nav-rail__divider' ).length,
			host: rail.tagName.toLowerCase(),
			current: rail.querySelectorAll( '[aria-current]' ).length,
			elevation: rail.querySelectorAll( '.ax-elevation' ).length,
		} );
	}, [ variant, alignment, narrow, divider, menu ] );

	return (
		<div ref={ hostRef }>
			<div className="ax-stylebook-navigations__rail-frame">
				<NavigationRail
					destinations={ DESTINATIONS }
					activeId="community"
					label={ `Specimen, ${ variant }${ narrow ? ' narrow' : '' }` }
					variant={ variant }
					alignment={ alignment }
					narrow={ narrow }
					divider={ divider }
					menu={ menu }
				/>
				<div className="ax-stylebook-navigations__rail-content">
					Body content. The rail is the leading edge of the window and sits outside any
					pane, so its width is read against what it leaves behind.
				</div>
			</div>
			<p className="ax-stylebook-page__note">
				{ metrics?.void
					? 'The rail measures 0px wide; nothing is reported. Open this page in a visible viewport.'
					: metrics
					? `rail ${ metrics.width }px wide · target area ${ metrics.itemWidth }x${
							metrics.itemHeight
					  }px (full width: ${
							metrics.itemWidth === metrics.width
					  }, min-height ${ metrics.itemMinHeight } for a published 64dp minimum) · indicator ${ metrics.indicatorWidth }x${ metrics.indicatorHeight }px, inset ${
							metrics.indicatorInset
					  }px · item gap ${ metrics.gap } · header gap ${ metrics.headerGap } · ArrowDown moved focus 0 -> ${
							metrics.arrowDown
					  } · ArrowUp at the first item stayed ${ metrics.arrowUpAtStart } · tab stops ${
							metrics.tabStops
					  }/${ DESTINATIONS.length } · dividers ${ metrics.divider } · elevation layers ${
							metrics.elevation
					  } (level 0, so none) · <${ metrics.host }> · aria-current ${ metrics.current }`
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
							marks deprecated. The emphasis is implemented anyway, as a typeface axis rather
							than a weight &mdash; <code>GRAD 125</code>, written in this component&rsquo;s{ ' ' }
							<code>axismundi.theme</code> layer as a product decision. It is not tokenised,
							because the axis belongs to the typeface and not to the component:
							m3.material.io&rsquo;s own sidebar emphasises its active label with{ ' ' }
							<code>GRAD 125</code>, the Google Fonts rail with <code>ROND 100</code>, and
							Roboto Flex has the first and not the second. The two weights above are equal
							because <code>label-medium</code> already resolves to 500, so a weight would
							emphasise nothing.
						</p>
					</Group>

					<Group
						id="nav-bar"
						kicker="Three to five destinations, divided equally across the window"
						title="Navigation bar — vertical items"
					>
						<BarSample itemLayout="vertical" />
						<p className="ax-stylebook-page__note">
							The item containers divide the window equally and the separation you see is the
							56dp indicator centred in a much wider container; the gap between containers is{ ' ' }
							<code>space0</code>. The 6dp above the indicator is <code>space75</code>, the
							container&rsquo;s own vertical padding. Height is the published 64dp as a{ ' ' }
							<em>minimum</em>, so a scaled label can grow it.
						</p>
					</Group>

					<Group
						id="nav-bar-horizontal"
						kicker="The same component, the other published configuration"
						title="Navigation bar — horizontal items"
					>
						<BarSample itemLayout="horizontal" />
						<p className="ax-stylebook-page__note">
							Shown at 720px, inside the medium band this configuration is published for. The
							containers grow and stop at a 120dp cap, and what the cap leaves becomes the
							window-edge margin &mdash; a capped fluid target, not the published fixed one,
							because a fixed width narrower than a long label&rsquo;s indicator would force
							the shrinking M3 prohibits. The cap is Axismundi policy; the reasoning and the
							divergence are in <code>navigation_bar.yml</code>.
						</p>
					</Group>

					<Group
						id="nav-rail"
						kicker="Three to seven destinations, on the leading edge, medium and up"
						title="Navigation rail — collapsed"
					>
						<RailSample variant="collapsed" />
						<p className="ax-stylebook-page__note">
							96dp wide, <code>surface</code> with the fill off, elevation level 0 &mdash; so
							no elevation layer is rendered at all. The item spans the full rail while its
							indicator stays 56dp: &ldquo;The navigation item&rsquo;s target area always spans
							the full width of the nav rail, even if the item container hugs its
							contents.&rdquo; The 4dp gap is <code>collapsed.item.vertical-space</code>, chosen
							over an unqualified <code>item.container.vertical-space</code> of 6dp because it
							names this variant &mdash; an inference recorded in{ ' ' }
							<code>navigation_rail.yml</code>.
						</p>
					</Group>

					<Group
						id="nav-rail-narrow"
						kicker="The published narrow width, and the centre alignment"
						title="Navigation rail — narrow, centre-aligned, with a divider"
					>
						<RailSample variant="collapsed" narrow alignment="center" divider />
						<p className="ax-stylebook-page__note">
							80dp is <code>collapsed.narrow.container.width</code> &mdash; the same figure as
							the <em>deprecated baseline</em> rail&rsquo;s only width, which is why this
							component and its stylesheet are named <code>nav-rail</code> after the live
							namespace. &ldquo;Navigation rail items can be aligned as a group to the top or
							center of a layout&rdquo;, and the optional divider sits on the trailing edge,
							&ldquo;adjacent to the app&rsquo;s content area&rdquo;.
						</p>
					</Group>

					<Group
						id="nav-rail-expanded"
						kicker="Horizontal items, and the pill hugging the label"
						title="Navigation rail — expanded (standard)"
					>
						<RailSample
							variant="expanded"
							menu={ <Icon name="menu" /> }
						/>
						<p className="ax-stylebook-page__note">
							220&ndash;360dp, written as the range it is published as. Here the two insets
							that are both <code>space200</code> become visible and are not the same
							measurement: <code>full-width.leading-space</code> holds the pill 16dp off the
							rail&rsquo;s edge, and the item&rsquo;s own indicator space holds the icon 16dp
							inside the pill. The header gap is <code>header-space-minimum</code> (
							<code>space500</code>), kept as a minimum because centring can only enlarge it.
							The menu is a slot: its collapse/expand behaviour, the modal layout and
							predictive back are all deferred, with reasons, under <code>scope</code> in{ ' ' }
							<code>navigation_rail.yml</code>.
						</p>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
