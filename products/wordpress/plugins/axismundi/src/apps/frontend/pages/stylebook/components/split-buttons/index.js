import { Icon } from '../../../../components/material/icon';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import { SplitButton } from '../../../../components/buttons/split-button';
import { useEffect, useRef, useState } from '@wordpress/element';
import '../../stylebook-page.css';
import './split-buttons.css';

/*
 * Checked against `docs/REFERENCE-M3-SPLIT-BUTTON.md`.
 *
 * The group worth having is the trailing width: at XS and S it is exactly 48,
 * which is why M3 gives split buttons no separate target rule.
 */

const VARIANTS = [ 'filled', 'tonal', 'elevated', 'outlined' ];

const SIZES = [ 'xsmall', 'small', 'medium', 'large', 'xlarge' ];

const TRAILING = { xsmall: 48, small: 48, medium: 56, large: 96, xlarge: 136 };

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

function SizeSample( { size } ) {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const root = hostRef.current?.querySelector( '.ax-split-button' );
		const leading = root?.querySelector( '.ax-split-button__leading' );
		const trailing = root?.querySelector( '.ax-split-button__trailing' );
		if ( ! trailing ) {
			return;
		}

		setMetrics( {
			gap: window.getComputedStyle( root ).columnGap,
			height: Math.round( trailing.getBoundingClientRect().height ),
			leading: Math.round( leading.getBoundingClientRect().width ),
			trailing: Math.round( trailing.getBoundingClientRect().width ),
		} );
	}, [] );

	return (
		<div className="ax-stylebook-split-buttons__sample" ref={ hostRef }>
			<SplitButton
				icon={ <Icon name="stars" /> }
				label={ size }
				menuIcon={ <Icon name="arrow_drop_down" /> }
				menuLabel={ `More ${ size } options` }
				size={ size }
			/>
			<p className="ax-stylebook-page__note">
				{ metrics
					? `h ${ metrics.height } · leading ${ metrics.leading } · trailing ${ metrics.trailing } (published ${ TRAILING[ size ] }) · seam ${ metrics.gap }`
					: 'Measuring...' }
			</p>
		</div>
	);
}

function ExpandedSample() {
	const [ open, setOpen ] = useState( false );

	return (
		<div className="ax-stylebook-split-buttons__row">
			<SplitButton
				label="Save"
				menuIcon={ <Icon name="arrow_drop_down" /> }
				menuLabel="More save options"
				trailingProps={ {
					'aria-expanded': open,
					onClick: () => setOpen( ( value ) => ! value ),
				} }
				variant="tonal"
			/>
		</div>
	);
}

export function StylebookSplitButtonsPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--split-buttons">
			<div className="ax-stylebook-page">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/components/buttons">Buttons</a>
					<a href="/social/stylebook/components/icon-buttons">Icon buttons</a>
					<a href="/social/stylebook/components/button-groups">Button groups</a>
				</nav>

				<section className="ax-stylebook-page__section" id="split-buttons" aria-labelledby="ax-stylebook-split-buttons-title">
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">Material component</p>
						<h1 id="ax-stylebook-split-buttons-title">Split buttons</h1>
					</header>

					<Group kicker="Four: no text, and elevated is back" title="Colour styles">
						<div className="ax-stylebook-split-buttons__row">
							{ VARIANTS.map( ( variant ) => (
								<SplitButton
									key={ variant }
									label={ variant }
									menuIcon={ <Icon name="arrow_drop_down" /> }
									menuLabel={ `More ${ variant } options` }
									variant={ variant }
								/>
							) ) }
						</div>
						<p className="ax-stylebook-page__note">
							Button has five styles and the icon button four, with a different fourth. Three
							components, three sets.
						</p>
					</Group>

					<Group kicker="The trailing width is the 48dp target" title="Sizes">
						<div className="ax-stylebook-split-buttons__sizes">
							{ SIZES.map( ( size ) => <SizeSample key={ size } size={ size } /> ) }
						</div>
						<p className="ax-stylebook-page__note">
							13 + 22 + 13 at XS and S. M3 gives icon buttons a 48dp target rule and split
							buttons none, because the published width already is one. The seam is 2dp at
							every size.
						</p>
					</Group>

					<Group kicker="Driven by aria-expanded, which a menu will supply" title="Open state">
						<ExpandedSample />
						<p className="ax-stylebook-page__note">
							Press the trailing half. The menu icon rotates 180 degrees inward on the standard
							motion scheme, its optical offset returns to centre, and the inner corner opens to
							the pill. No menu exists yet, so nothing announces one: this component sets no
							aria-haspopup and no aria-expanded of its own.
						</p>
					</Group>

					<Group kicker="Only the inner corners move" title="Disabled and state">
						<div className="ax-stylebook-split-buttons__row">
							<SplitButton
								disabled
								label="Disabled"
								menuIcon={ <Icon name="arrow_drop_down" /> }
								menuLabel="More options"
							/>
							<SplitButton
								disabled
								label="Disabled"
								menuIcon={ <Icon name="arrow_drop_down" /> }
								menuLabel="More options"
								variant="outlined"
							/>
						</div>
						<p className="ax-stylebook-page__note">
							Hovering either half opens its inner corner while the outer pill stays put, which
							is what keeps the pair reading as one control. Unlike a toggle button, selecting
							changes no colour &mdash; only a state layer is applied.
						</p>
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
