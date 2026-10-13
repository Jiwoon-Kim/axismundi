import { useState } from '@wordpress/element';
import { ExtendedFab, Fab } from '../../../../components/fabs';
import { Icon } from '../../../../components/material/icon';
import { NavigationRail } from '../../../../components/navigations/nav-rail';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import '../../stylebook-page.css';
import './fabs.css';

const COLOURS = [
	'primary-container',
	'secondary-container',
	'tertiary-container',
	'primary',
	'secondary',
	'tertiary',
];

const DESTINATIONS = [
	{ id: 'home', label: 'Home', icon: <Icon name="home" />, href: '#home' },
	{ id: 'people', label: 'People', icon: <Icon name="group" />, href: '#people' },
	{ id: 'settings', label: 'Settings', icon: <Icon name="settings" />, href: '#settings' },
];

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

function ActionFab( { colour, size } ) {
	const [ count, setCount ] = useState( 0 );
	return (
		<div className="ax-stylebook-fabs__action">
			<Fab
				colour={ colour }
				icon={ <Icon name="edit" /> }
				label="Compose post"
				onClick={ () => setCount( ( value ) => value + 1 ) }
				size={ size }
			/>
			<span className="ax-stylebook-fabs__readout" aria-live="polite">{ count ? `activated ${ count } time${ 1 === count ? '' : 's' }` : 'hover, focus, or press the FAB' }</span>
		</div>
	);
}

export function StylebookFabsPage() {
	return (
		<Scaffold className="ax-stylebook ax-stylebook--fabs">
			<div className="ax-stylebook-page" aria-labelledby="ax-stylebook-fabs-title">
				<header className="ax-stylebook-page__header">
					<p className="ax-stylebook-page__eyebrow">Material component</p>
					<h1 id="ax-stylebook-fabs-title">FABs</h1>
				</header>

				<Group kicker="Three square action sizes" title="Floating action button">
					<div className="ax-stylebook-fabs__row">
						{ [ 'small', 'medium', 'large' ].map( ( size ) => <ActionFab key={ size } size={ size } /> ) }
					</div>
					<p className="ax-stylebook-page__note">FAB is an icon-only screen action. Its tooltip uses the same text as its accessible name; unavailable actions are removed rather than disabled.</p>
				</Group>

				<Group kicker="Colour is configuration, not a variant" title="Six colour styles">
					<div className="ax-stylebook-fabs__row">
						{ COLOURS.map( ( colour ) => (
							<Fab colour={ colour } icon={ <Icon name="add" /> } key={ colour } label={ `Create ${ colour } item` } />
						) ) }
					</div>
				</Group>

				<Group kicker="A label is required; an icon is optional" title="Extended FAB">
					<div className="ax-stylebook-fabs__row">
						<ExtendedFab icon={ <Icon name="person_add" /> } label="Add contact" />
						<ExtendedFab icon={ <Icon name="person_add" /> } label="Add contact" size="medium" />
						<ExtendedFab icon={ <Icon name="person_add" /> } label="Add contact" size="large" />
						<ExtendedFab label="Create" />
					</div>
					<p className="ax-stylebook-page__note">The icon follows the label in RTL. Unlike FAB, extended FAB already has a visible label and therefore has no tooltip.</p>
				</Group>

				<Group kicker="The first consumer already had the slot" title="Navigation rail">
					<div className="ax-stylebook-fabs__rail">
						<NavigationRail
							activeId="home"
							destinations={ DESTINATIONS }
							fab={ <ExtendedFab icon={ <Icon name="person_add" /> } label="Add contact" /> }
							filled
							label="FAB rail specimen"
							variant="expanded"
						/>
					</div>
				</Group>
			</div>
		</Scaffold>
	);
}
