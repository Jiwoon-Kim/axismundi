import { Button } from '../../../../components/buttons/button';
import { Card } from '../../../../components/cards/card';
import { Divider } from '../../../../components/dividers/divider';
import { Icon } from '../../../../components/material/icon';
import { IconButton } from '../../../../components/buttons/icon-button';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import { useEffect, useRef, useState } from '@wordpress/element';
import '../../stylebook-page.css';
import './cards.css';

/*
 * Checked against `products/styleguide/_data/card.yml`.
 *
 * The figure worth keeping in view is how few there are: shape 12, padding-inline
 * 16, between-cards 8 max, labels start-aligned. Everything else a card looks
 * like comes from what is inside it.
 */

const VARIANTS = [ 'elevated', 'filled', 'outlined' ];

const PUBLISHED = {
	elevated: { role: 'surface-container-low', level: 1, outline: 0 },
	filled: { role: 'surface-container-highest', level: 0, outline: 0 },
	outlined: { role: 'surface', level: 0, outline: 1 },
};

function Group( { children, kicker, title } ) {
	return (
		<section className="ax-stylebook-page__group" aria-label={ title }>
			<header className="ax-stylebook-page__group-header">
				<p className="ax-stylebook-page__group-kicker">{ kicker }</p>
				<h2>{ title }</h2>
			</header>
			{ children }
		</section>
	);
}

/*
 * The readout compares the painted container against the role card.yml names,
 * by resolving that role on the same element rather than by hard-coding a hex --
 * the theme owns the value and a scheme change must not make this specimen lie.
 */
function VariantSample( { variant } ) {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const card = hostRef.current?.querySelector( '.ax-card' );
		if ( ! card ) {
			return;
		}

		const computed = window.getComputedStyle( card );
		const published = PUBLISHED[ variant ];
		const expected = computed
			.getPropertyValue( `--md-sys-color-${ published.role }` )
			.trim();
		const probe = document.createElement( 'span' );
		probe.style.color = expected;
		card.appendChild( probe );
		const resolved = window.getComputedStyle( probe ).color;
		probe.remove();

		const layer = card.querySelector( '.ax-elevation' );

		setMetrics( {
			background: computed.backgroundColor,
			matchesRole: computed.backgroundColor === resolved,
			outline: Math.round( parseFloat( computed.borderTopWidth ) ),
			paddingInline: Math.round( parseFloat( computed.paddingInlineStart ) ),
			paddingBlock: Math.round( parseFloat( computed.paddingBlockStart ) ),
			radius: computed.borderTopLeftRadius,
			level: layer
				? Number( window.getComputedStyle( layer ).getPropertyValue( '--md-elevation-level' ) )
				: 0,
		} );
	}, [ variant ] );

	const published = PUBLISHED[ variant ];

	return (
		<div className="ax-stylebook-cards__sample" ref={ hostRef }>
			<Card variant={ variant }>
				<p className="ax-stylebook-cards__headline">{ variant }</p>
				<p className="ax-stylebook-page__note">
					A container. The text, the buttons and the spacing below all come from the
					contents, not from the card.
				</p>
			</Card>
			<p className="ax-stylebook-page__note">
				{ metrics
					? `${ published.role } ${ metrics.matchesRole ? 'resolved' : `MISMATCH ${ metrics.background }` } · radius ${ metrics.radius } (published 12dp) · padding-inline ${ metrics.paddingInline } (published 16dp) · padding-block ${ metrics.paddingBlock } (none published) · outline ${ metrics.outline } (published ${ published.outline }dp) · elevation ${ metrics.level } (published ${ published.level })`
					: 'Measuring...' }
			</p>
		</div>
	);
}

/*
 * The accessibility rule this component exists on the non-actionable side of.
 * The card is not a tab stop; everything actionable inside it is, and they are
 * all visited before focus leaves for the next card. The readout counts the tab
 * stops so the claim is measured rather than asserted.
 */
function FocusOrderSample() {
	const hostRef = useRef();
	const [ stops, setStops ] = useState();

	useEffect( () => {
		const host = hostRef.current;
		if ( ! host ) {
			return;
		}

		const cards = [ ...host.querySelectorAll( '.ax-card' ) ];
		setStops( {
			cardsAreTabStops: cards.filter( ( card ) => 0 <= card.tabIndex ).length,
			cardsHaveRole: cards.filter( ( card ) => card.getAttribute( 'role' ) ).length,
			inside: [ ...host.querySelectorAll( 'button, a[href]' ) ].length,
		} );
	}, [] );

	return (
		<div ref={ hostRef }>
			<ul className="ax-stylebook-cards__collection">
				{ [ 'First', 'Second' ].map( ( label ) => (
					<Card as="li" key={ label } variant="outlined">
						<p className="ax-stylebook-cards__headline">{ label }</p>
						<div className="ax-stylebook-cards__actions">
							<Button variant="text">Learn more</Button>
							<IconButton
								icon={ <Icon name="favorite" /> }
								label={ `Favourite ${ label }` }
								variant="standard"
							/>
						</div>
					</Card>
				) ) }
			</ul>
			<p className="ax-stylebook-page__note">
				{ stops
					? `cards that are tab stops ${ stops.cardsAreTabStops } · cards with a role ${ stops.cardsHaveRole } · actionable elements inside ${ stops.inside }`
					: 'Measuring...' }
			</p>
		</div>
	);
}

/*
 * Dividers inside a card, which is where M3 puts them: "Dividers can separate
 * regions in cards or indicate areas of a card that can expand." Full width for
 * the boundary between content and actions, inset for related rows.
 *
 * The readout checks the published figures and, more usefully, that the component
 * contributes no margin of its own -- the one thing that would be invisible until
 * it was everywhere.
 */
function DividerSample() {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const read = ( variant ) => {
			const line = hostRef.current?.querySelector( `.ax-divider[data-variant="${ variant }"]` );
			if ( ! line ) {
				return null;
			}
			const computed = window.getComputedStyle( line );
			return {
				start: Math.round( parseFloat( computed.marginInlineStart ) ),
				end: Math.round( parseFloat( computed.marginInlineEnd ) ),
				thickness: Math.round( parseFloat( computed.blockSize ) ),
				block: `${ Math.round( parseFloat( computed.marginBlockStart ) ) }/${ Math.round( parseFloat( computed.marginBlockEnd ) ) }`,
			};
		};

		const vertical = hostRef.current?.querySelector( '.ax-divider[data-orientation="vertical"]' );
		setMetrics( {
			full: read( 'full' ),
			inset: read( 'inset' ),
			middle: read( 'middle-inset' ),
			verticalWidth: vertical ? Math.round( parseFloat( window.getComputedStyle( vertical ).inlineSize ) ) : null,
			verticalHeight: vertical ? Math.round( vertical.getBoundingClientRect().height ) : null,
			announced: [ ...( hostRef.current?.querySelectorAll( '.ax-divider' ) ?? [] ) ].filter(
				( line ) => 'true' !== line.getAttribute( 'aria-hidden' )
			).length,
		} );
	}, [] );

	return (
		<div ref={ hostRef }>
			<Card className="ax-stylebook-cards__divided" variant="outlined">
				<p className="ax-stylebook-cards__headline">Regions</p>
				<p className="ax-stylebook-page__note">
					Supporting text above a full-width divider, which M3 uses to separate a
					card&rsquo;s interactive area from its content.
				</p>
				<Divider />
				<div className="ax-stylebook-cards__rows">
					<p className="ax-stylebook-page__note">Related row</p>
					<Divider variant="inset" />
					<p className="ax-stylebook-page__note">Related row</p>
					<Divider variant="middle-inset" />
					<p className="ax-stylebook-page__note">Nested item</p>
				</div>
				<Divider />
				<div className="ax-stylebook-cards__actions">
					<Button variant="text">Learn more</Button>
					<Divider orientation="vertical" />
					<IconButton
						icon={ <Icon name="favorite" /> }
						label="Favourite Regions"
						variant="standard"
					/>
				</div>
			</Card>
			<p className="ax-stylebook-page__note">
				{ metrics
					? `thickness ${ metrics.full?.thickness }px (published 1dp) · full ${ metrics.full?.start }/${ metrics.full?.end } · inset ${ metrics.inset?.start }/${ metrics.inset?.end } (published 16/0) · middle-inset ${ metrics.middle?.start }/${ metrics.middle?.end } (published 16/16) · block margins on a divider this page does not style ${ metrics.inset?.block } · on one it does ${ metrics.full?.block } · vertical ${ metrics.verticalWidth }x${ metrics.verticalHeight } · announced lines ${ metrics.announced }`
					: 'Measuring...' }
			</p>
		</div>
	);
}

export function StylebookCardsPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--cards">
			<div className="ax-stylebook-page">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/components/buttons">Buttons</a>
					<a href="/social/stylebook/components/icon-buttons">Icon buttons</a>
					<a href="/social/stylebook/components/button-groups">Button groups</a>
					<a href="/social/stylebook/components/split-buttons">Split buttons</a>
					<a href="/social/stylebook/components/dividers">Dividers</a>
				</nav>

				<section className="ax-stylebook-page__section" id="cards" aria-labelledby="ax-stylebook-cards-title">
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">Material component</p>
						<h1 id="ax-stylebook-cards-title">Cards</h1>
					</header>

					<Group kicker="Three styles, no size axis, and the same legibility in each" title="Colour styles">
						<div className="ax-stylebook-cards__variants">
							{ VARIANTS.map( ( variant ) => (
								<VariantSample key={ variant } variant={ variant } />
							) ) }
						</div>
						<p className="ax-stylebook-page__note">
							M3: &ldquo;Each provides the same legibility and functionality, so the variant you
							use depends on style alone.&rdquo; Unlike Button, nothing here chooses a style for
							the caller, and there is no published default &mdash; this component defaults to
							elevated because the guidelines place it between the other two.
						</p>
						<p className="ax-stylebook-page__note">
							padding-block reads 0 at every variant and that is the published state: M3&rsquo;s
							measurement table has one padding row, left/right 16dp. The vertical rhythm belongs
							to the headline, subhead and supporting text blocks, which do not exist yet, so no
							figure is invented for it here.
						</p>
					</Group>

					<Group kicker="Two published tokens, three insets, and no space of its own" title="Divider, in its first consumer">
						<DividerSample />
						<p className="ax-stylebook-page__note">
							M3 gives divider two tokens &mdash; a 1dp line in outline-variant &mdash; and three
							insets, although its prose says two ways and the measurements table publishes
							<code> inset </code>and<code> middle-inset </code>separately. The vertical rule
							shares only the colour and the thickness; its length comes from the row it sits in.
						</p>
						<p className="ax-stylebook-page__note">
							The 4dp gap to supporting text and the 8dp right and bottom margins are in
							divider&rsquo;s own measurements table and are not divider&rsquo;s. The readout
							proves it from both sides: a divider this page leaves alone has no block margins
							at all, and the full-width ones have 16 because this page asked for it. A
							component that shipped them would put space into every card using one, with no
							way for the caller to take it back.
						</p>
					</Group>

					<Group kicker="The card is the container; the actions inside keep their own focus" title="Non-actionable, and what that means">
						<FocusOrderSample />
						<p className="ax-stylebook-page__note">
							M3: &ldquo;An action shouldn&rsquo;t be placed on an actionable surface.&rdquo; So this
							card has no role, no tab stop, no hover state and no ripple, and the two controls
							inside each have all of those. Tab visits both of them before leaving for the next
							card. A card that is itself one target is a different component with that exclusion
							to enforce, and it is not built until a surface needs one.
						</p>
						<p className="ax-stylebook-page__note">
							These are <code>as=&quot;li&quot;</code> inside a list, which is why the host element
							is a prop here and not on Button: forcing a <code>div</code> would make a semantic
							collection impossible. The allowlist holds non-interactive containers only.
						</p>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
