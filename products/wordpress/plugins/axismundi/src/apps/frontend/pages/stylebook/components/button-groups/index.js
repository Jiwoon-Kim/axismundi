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

					<Group kicker="Children keep what they chose" title="Mixed colours and widths">
						<ButtonGroup size="small">
							<Button>Filled</Button>
							<Button variant="tonal">Tonal</Button>
							<Button variant="outlined">Outlined</Button>
							<IconButton icon={ <Icon name="more_vert" /> } label="More" variant="outlined" width="narrow" />
						</ButtonGroup>
						<p className="ax-stylebook-page__note">
							Mixing colour is allowed here and is the point of a standard group. A connected
							group is the one M3 tells you not to mix.
						</p>
					</Group>

					<Group kicker="Not built yet, and named so it is not mistaken for done" title="Adjacent interaction">
						<ButtonGroup size="small">
							<Button>Press me</Button>
							<Button variant="tonal">Watch my width</Button>
							<Button variant="outlined">And mine</Button>
						</ButtonGroup>
						<p className="ax-stylebook-page__note">
							M3&rsquo;s defining behaviour for a standard group is that pressing or selecting a
							button widens it by 15% while its neighbours move and compress. These buttons do
							not do that: the container and its spacing are implemented, the interaction is not.
						</p>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
