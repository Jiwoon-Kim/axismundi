import { Icon } from '../../../../components/material/icon';
import { IconButton } from '../../../../components/buttons/icon-button';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import { useEffect, useRef, useState } from '@wordpress/element';
import '../../stylebook-page.css';
import './icon-buttons.css';

/*
 * Checked against `products/styleguide/_data/icon_button.yml`.
 *
 * The two groups that exist because of this component specifically are the
 * 5x3 size/width grid and the 48dp target. Width is the only axis M3 gives to
 * one component alone, and the target is the rule that is easiest to implement
 * by breaking the axis it shares a size with.
 */

const VARIANTS = [ 'filled', 'tonal', 'outlined', 'standard' ];

const SIZES = [ 'xsmall', 'small', 'medium', 'large', 'xlarge' ];

const WIDTHS = [ 'narrow', 'default', 'wide' ];

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

function SizeRow( { size } ) {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const controls = [ ...( hostRef.current?.querySelectorAll( '.ax-icon-button' ) ?? [] ) ];
		if ( ! controls.length ) {
			return;
		}

		setMetrics(
			controls
				.map( ( control ) => {
					const box = control.getBoundingClientRect();
					return `${ Math.round( box.width ) }x${ Math.round( box.height ) }`;
				} )
				.join( ' · ' )
		);
	}, [] );

	return (
		<div className="ax-stylebook-icon-buttons__size-row" ref={ hostRef }>
			{ WIDTHS.map( ( width ) => (
				<IconButton
					icon={ <Icon name="star" /> }
					key={ width }
					label={ `${ size } ${ width }` }
					size={ size }
					width={ width }
				/>
			) ) }
			<p className="ax-stylebook-page__note">
				{ metrics ? `${ size } — narrow · default · wide: ${ metrics }` : 'Measuring...' }
			</p>
		</div>
	);
}

function ToggleSample( { shape, variant } ) {
	const [ selected, setSelected ] = useState( false );

	return (
		<IconButton
			icon={ <Icon name="star" /> }
			label="Favourite"
			onSelectedChange={ setSelected }
			selected={ selected }
			shape={ shape }
			toggle
			variant={ variant }
		/>
	);
}

export function StylebookIconButtonsPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--icon-buttons">
			<div className="ax-stylebook-page">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/styles">Styles</a>
					<a href="/social/stylebook/components/buttons">Buttons</a>
				</nav>

				<section className="ax-stylebook-page__section" id="icon-buttons" aria-labelledby="ax-stylebook-icon-buttons-title">
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">Material component</p>
						<h1 id="ax-stylebook-icon-buttons-title">Icon buttons</h1>
					</header>

					<Group kicker="Four, not five: no text and no elevated" title="Colour styles">
						<div className="ax-stylebook-icon-buttons__row">
							{ VARIANTS.map( ( variant ) => (
								<IconButton
									icon={ <Icon name="star" /> }
									key={ variant }
									label={ variant }
									variant={ variant }
								/>
							) ) }
						</div>
						<p className="ax-stylebook-page__note">
							Standard has no container at rest. Hover it and the state layer is the only
							thing that ever paints one.
						</p>
					</Group>

					<Group kicker="Width changes the space either side, never the icon" title="Size and width">
						<div className="ax-stylebook-icon-buttons__sizes">
							{ SIZES.map( ( size ) => <SizeRow key={ size } size={ size } /> ) }
						</div>
						<p className="ax-stylebook-page__note">
							Default width equals the container height at every size, which is why the
							middle control in each row is a circle and the others are not.
						</p>
					</Group>

					<Group kicker="Fixed label, aria-pressed, FILL 0 to 1" title="Toggle">
						<div className="ax-stylebook-icon-buttons__row">
							{ VARIANTS.map( ( variant ) => (
								<ToggleSample key={ variant } shape="round" variant={ variant } />
							) ) }
						</div>
						<div className="ax-stylebook-icon-buttons__row">
							{ VARIANTS.map( ( variant ) => (
								<ToggleSample key={ variant } shape="square" variant={ variant } />
							) ) }
						</div>
						<p className="ax-stylebook-page__note">
							Selecting exchanges the resting shape, so the round row squares off and the
							square row rounds. Standard keeps its missing container selected; only the
							icon turns.
						</p>
					</Group>

					<Group kicker="Target size is not container size" title="48dp target">
						<div className="ax-stylebook-icon-buttons__targets">
							<IconButton icon={ <Icon name="star" /> } label="XS narrow" size="xsmall" width="narrow" />
							<IconButton icon={ <Icon name="star" /> } label="S narrow" size="small" width="narrow" />
							<IconButton icon={ <Icon name="star" /> } label="Medium" size="medium" />
						</div>
						<p className="ax-stylebook-page__note">
							XS and S must be reachable at 48x48 while their containers stay at the
							published 28x32 and 32x40. The hit area is an absolutely positioned overlay,
							so it costs no layout and does not flatten the width axis.
						</p>
					</Group>

					<Group kicker="Container replaced, not faded" title="Disabled">
						<div className="ax-stylebook-icon-buttons__row">
							{ VARIANTS.map( ( variant ) => (
								<IconButton
									disabled
									icon={ <Icon name="star" /> }
									key={ variant }
									label={ variant }
									variant={ variant }
								/>
							) ) }
						</div>
						<p className="ax-stylebook-page__note">
							Outlined keeps its outline at full strength. Standard is the one style with no
							container in any state, disabled included.
						</p>
					</Group>

					<Group kicker="The name is text nobody sees" title="Accessible name">
						<div className="ax-stylebook-icon-buttons__row">
							<IconButton icon={ <Icon name="add" /> } label="Add item" />
							<IconButton icon={ <Icon name="share" /> } label="Share" variant="tonal" />
							<IconButton icon={ <Icon name="more_vert" /> } label="More options" variant="standard" />
						</div>
						<p className="ax-stylebook-page__note">
							Tab to these to see the focus ring. M3 also asks for a tooltip on the web; that
							waits for a Tooltip primitive rather than being faked with `title`, so the
							visible name is pending while the accessible one is not.
						</p>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
