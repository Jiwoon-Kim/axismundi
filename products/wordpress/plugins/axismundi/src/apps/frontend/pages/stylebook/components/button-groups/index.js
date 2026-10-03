import { Button } from '../../../../components/buttons/button';
import { ButtonGroup } from '../../../../components/buttons/button-group';
import { Icon } from '../../../../components/material/icon';
import { IconButton } from '../../../../components/buttons/icon-button';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import { useEffect, useRef, useState } from '@wordpress/element';
import '../../stylebook-page.css';
import './button-groups.css';

/*
 * Checked against `products/styleguide/_data/button_group.yml`.
 *
 * The group paints nothing, so what there is to verify is the gap per size and
 * that the children keep their own colour, width and shape. The media-player
 * row is M3's own worked example of a group with no selection at all, which is
 * the half of "standard" that is easy to forget.
 */

const SIZES = [ 'xsmall', 'small', 'medium', 'large', 'xlarge' ];

const GAPS = { xsmall: 18, small: 12, medium: 8, large: 8, xlarge: 8 };

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

function GapSample( { size } ) {
	const hostRef = useRef();
	const [ gap, setGap ] = useState();

	useEffect( () => {
		const group = hostRef.current?.querySelector( '.ax-button-group' );
		if ( group ) {
			setGap( window.getComputedStyle( group ).columnGap );
		}
	}, [] );

	return (
		<div className="ax-stylebook-button-groups__sample" ref={ hostRef }>
			<ButtonGroup size={ size }>
				<IconButton icon={ <Icon name="skip_previous" /> } label="Previous" size={ size } variant="outlined" />
				<Button size={ size }>Play</Button>
				<IconButton icon={ <Icon name="skip_next" /> } label="Next" size={ size } variant="outlined" />
			</ButtonGroup>
			<p className="ax-stylebook-page__note">
				{ gap ? `${ size } — gap ${ gap } (published ${ GAPS[ size ] }dp)` : 'Measuring...' }
			</p>
		</div>
	);
}

function AdjacentSample() {
	const hostRef = useRef();
	const [ selected, setSelected ] = useState( 'one' );
	const [ widths, setWidths ] = useState();

	useEffect( () => {
		const read = ( group ) =>
			[ ...( group?.children ?? [] ) ]
				.map( ( child ) => Math.round( child.getBoundingClientRect().width ) )
				.join( ' / ' );

		const groups = hostRef.current?.querySelectorAll( '.ax-button-group' );
		if ( groups?.length === 2 ) {
			setWidths( { fill: read( groups[ 0 ] ), hug: read( groups[ 1 ] ) } );
		}
	}, [ selected ] );

	const row = ( distribution ) => (
		<ButtonGroup distribution={ distribution } size="small">
			{ [ 'one', 'two', 'three' ].map( ( value ) => (
				<Button
					key={ value }
					onSelectedChange={ () => setSelected( value ) }
					selected={ selected === value }
					toggle
					variant="tonal"
				>
					{ `Option ${ value }` }
				</Button>
			) ) }
		</ButtonGroup>
	);

	return (
		<div className="ax-stylebook-button-groups__adjacent" ref={ hostRef }>
			{ row( 'fill' ) }
			{ row( 'hug' ) }
			<p className="ax-stylebook-page__note">
				{ widths ? `fill ${ widths.fill } · hug ${ widths.hug }` : 'Measuring...' }
			</p>
		</div>
	);
}

export function StylebookButtonGroupsPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--button-groups">
			<div className="ax-stylebook-page">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/components/buttons">Buttons</a>
					<a href="/social/stylebook/components/icon-buttons">Icon buttons</a>
				</nav>

				<section className="ax-stylebook-page__section" id="button-groups" aria-labelledby="ax-stylebook-button-groups-title">
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">Material component</p>
						<h1 id="ax-stylebook-button-groups-title">Button groups</h1>
					</header>

					<Group kicker="The gap keeps a 48dp target reachable, so it is per size" title="Standard, by size">
						<div className="ax-stylebook-button-groups__sizes">
							{ SIZES.map( ( size ) => <GapSample key={ size } size={ size } /> ) }
						</div>
						<p className="ax-stylebook-page__note">
							XS and S carry the larger gaps because their controls are the ones that would
							otherwise sit too close to a neighbour.
						</p>
					</Group>

					<Group kicker="M3's own example, and it has no selection in it" title="An action group">
						<ButtonGroup label="Playback" size="medium">
							<IconButton icon={ <Icon name="skip_previous" /> } label="Previous" size="medium" variant="outlined" />
							<Button size="medium" variant="tonal">Play</Button>
							<IconButton icon={ <Icon name="skip_next" /> } label="Next" size="medium" variant="outlined" />
						</ButtonGroup>
						<p className="ax-stylebook-page__note">
							Emphasis comes from the buttons&rsquo; own size, colour and width. The container
							arranges and nothing else &mdash; it has no colour axis, and M3 publishes no shape
							tokens for a standard group either.
						</p>
					</Group>

					<Group kicker="Allowed for hero moments; the gap stays the group's" title="Mixed sizes">
						<div className="ax-stylebook-button-groups__sizes">
							<ButtonGroup label="Playback" size="small">
								<IconButton icon={ <Icon name="skip_previous" /> } label="Previous" variant="outlined" />
								<Button size="medium" variant="tonal">Play</Button>
								<IconButton icon={ <Icon name="skip_next" /> } label="Next" variant="outlined" />
							</ButtonGroup>
							<ButtonGroup size="large">
								<Button size="large">Large</Button>
								<Button size="small" variant="outlined">Small inside a large group</Button>
							</ButtonGroup>
						</div>
						<p className="ax-stylebook-page__note">
							The hero is a different size and the group&rsquo;s gap does not follow it. The
							second row is the case worth checking: XS and S carry the wider gaps so a small
							control still clears its neighbour, and a small button placed in a large group
							takes the large group&rsquo;s narrower one instead.
						</p>
					</Group>

					<Group kicker="Children keep what they chose" title="Mixed colours and widths">
						<ButtonGroup size="small">
							<Button>Filled</Button>
							<Button variant="tonal">Tonal</Button>
							<Button variant="outlined">Outlined</Button>
							<IconButton icon={ <Icon name="more_vert" /> } label="More" variant="outlined" />
						</ButtonGroup>
						<p className="ax-stylebook-page__note">
							Mixing colour is allowed here and is the point of a standard group. A connected
							group is the one M3 tells you not to mix.
						</p>
						<p className="ax-stylebook-page__note">
							Width is the exception. The group&rsquo;s gap is what delivers the 48dp target
							&mdash; a container plus its gap clears 48 at every size &mdash; and a narrow XS
							or S control is the one thing that leaves it short, by 2 to 6px depending on the
							pair. Narrow is therefore unsupported in a group for now, and the component says
							so in development.
						</p>
					</Group>

					<Group kicker="Opt-in, because it contradicts hugging" title="Adjacent interaction">
						<AdjacentSample />
						<p className="ax-stylebook-page__note">
							Selecting widens the chosen button by 15% and its neighbours compress to pay for
							it. This needs <code>distribution=&quot;fill&quot;</code>: a group sized to its
							content has nothing to redistribute, so the same markup at the default
							<code> hug </code>does not move at all. Both are below.
						</p>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
