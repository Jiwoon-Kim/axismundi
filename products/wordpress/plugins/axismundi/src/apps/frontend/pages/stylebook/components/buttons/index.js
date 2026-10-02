import { Button } from '../../../../components/buttons/button';
import { Icon } from '../../../../components/material/icon';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import { useEffect, useRef, useState } from '@wordpress/element';
import '../../stylebook-page.css';
import './buttons.css';

/*
 * Buttons is a Components page, not a Styles page. The Styles pages verify one
 * M3 axis each -- colour, typography, icons, elevation -- while this verifies a
 * control that consumes several of them at once.
 *
 * The published values it is checked against are in
 * `products/styleguide/_data/button.yml`.
 */

const VARIANTS = [ 'filled', 'elevated', 'tonal', 'outlined', 'text' ];

const SIZES = [ 'xsmall', 'small', 'medium', 'large', 'xlarge' ];

const TOGGLE_VARIANTS = [ 'filled', 'elevated', 'tonal', 'outlined' ];

function SizeSample( { size } ) {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const control = hostRef.current?.querySelector( '.ax-button' );
		const glyph = control?.querySelector( '.ax-icon' );
		if ( ! control ) {
			return;
		}

		const computed = window.getComputedStyle( control );
		setMetrics( {
			height: computed.blockSize,
			icon: glyph ? window.getComputedStyle( glyph ).fontSize : 'n/a',
			label: computed.fontSize,
			radius: computed.borderStartStartRadius,
			space: computed.paddingInlineStart,
		} );
	}, [] );

	return (
		<div className="ax-stylebook-buttons__sample" ref={ hostRef }>
			<Button icon={ <Icon name="star" /> } size={ size }>{ size }</Button>
			<p className="ax-stylebook-page__note">
				{ metrics
					? `${ metrics.height } · r ${ metrics.radius } · ${ metrics.space } space · ${ metrics.label } label · ${ metrics.icon } icon`
					: 'Measuring...' }
			</p>
		</div>
	);
}

function ToggleSample( { variant } ) {
	const [ selected, setSelected ] = useState( false );

	return (
		<Button
			icon={ <Icon name="stars" /> }
			onSelectedChange={ setSelected }
			selected={ selected }
			toggle
			variant={ variant }
		>
			Favourite
		</Button>
	);
}

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

export function StylebookButtonsPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--buttons">
			<div className="ax-stylebook-page">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/styles">Styles</a>
				</nav>

				<section className="ax-stylebook-page__section" id="buttons" aria-labelledby="ax-stylebook-buttons-title">
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">Material component</p>
						<h1 id="ax-stylebook-buttons-title">Buttons</h1>
					</header>

					<Group kicker="Independent of size and shape" title="Colour styles">
						<div className="ax-stylebook-buttons__row">
							{ VARIANTS.map( ( variant ) => (
								<Button key={ variant } variant={ variant }>{ variant }</Button>
							) ) }
						</div>
					</Group>

					<Group kicker="Height, label type, icon and radius per size" title="Sizes">
						<div className="ax-stylebook-buttons__sizes">
							{ SIZES.map( ( size ) => <SizeSample key={ size } size={ size } /> ) }
						</div>
					</Group>

					<Group kicker="Round is half the height; press morphs the corner" title="Shape">
						<div className="ax-stylebook-buttons__row">
							<Button shape="round" size="medium">Round</Button>
							<Button shape="square" size="medium">Square</Button>
							<Button shape="round" size="medium" variant="outlined">Round outlined</Button>
							<Button shape="square" size="medium" variant="outlined">Square outlined</Button>
						</div>
					</Group>

					<Group kicker="Fixed label, aria-pressed, FILL 0 to 1" title="Toggle">
						<div className="ax-stylebook-buttons__row">
							{ TOGGLE_VARIANTS.map( ( variant ) => (
								<ToggleSample key={ variant } variant={ variant } />
							) ) }
						</div>
						<p className="ax-stylebook-page__note">
							The accessible name stays &ldquo;Favourite&rdquo; in both states. M3 publishes no
							Toggle Text button, so that combination has no container to recolour.
						</p>
					</Group>

					<Group kicker="Container replaced, not faded" title="Disabled">
						<div className="ax-stylebook-buttons__row">
							{ VARIANTS.map( ( variant ) => (
								<Button disabled key={ variant } variant={ variant }>{ variant }</Button>
							) ) }
						</div>
						<p className="ax-stylebook-page__note">
							Outlined keeps its outline at full strength and gains no container.
						</p>
					</Group>

					<Group kicker="Nodes, not names: the slot sizes what it holds" title="Icon slots">
						<div className="ax-stylebook-buttons__row">
							<Button icon={ <Icon name="add" /> }>Leading</Button>
							<Button trailingIcon={ <Icon name="arrow_forward" /> }>Trailing</Button>
							<Button icon={ <Icon name="sync" /> } trailingIcon={ <Icon name="expand_more" /> }>Both</Button>
						</div>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
