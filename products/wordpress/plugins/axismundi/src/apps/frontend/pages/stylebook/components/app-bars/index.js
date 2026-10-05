import { AppBar } from '../../../../components/app-bars/app-bar';
import { IconButton } from '../../../../components/buttons/icon-button';
import { Icon } from '../../../../components/material/icon';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import { useEffect, useRef, useState } from '@wordpress/element';
import '../../stylebook-page.css';
import './app-bars.css';

const VARIANTS = [
	{ name: 'small', title: 'Library' },
	{ name: 'medium', title: 'A title that can grow onto another line' },
	{ name: 'large', title: 'A longer page title that can grow onto another line' },
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

function BarSample( { centered = false, scrolled = false, subtitle, variant, title } ) {
	const hostRef = useRef();
	const [ metrics, setMetrics ] = useState();

	useEffect( () => {
		const root = hostRef.current;
		const bar = root?.querySelector( '.ax-app-bar' );
		if ( ! bar ) {
			return;
		}

		const box = bar.getBoundingClientRect();
		const titleNode = bar.querySelector( '.ax-app-bar__title' );
		const textBox = bar.querySelector( '.ax-app-bar__text' ).getBoundingClientRect();
		setMetrics( {
			height: Math.round( box.height ),
			width: Math.round( box.width ),
			color: window.getComputedStyle( bar ).backgroundColor,
			centerOffset: Math.round( ( textBox.left + textBox.right - box.left - box.right ) / 2 ),
			titleTag: titleNode?.firstElementChild?.tagName.toLowerCase() ?? 'text',
		} );
	}, [ centered, scrolled, subtitle, variant, title ] );

	return (
		<div ref={ hostRef } className="ax-stylebook-app-bars__sample">
			<AppBar
				alignment={ centered ? 'centered' : 'leading' }
				actions={
					<>
						<IconButton icon={ <Icon name="edit" /> } label="Edit" variant="standard" />
						<IconButton icon={ <Icon name="more_vert" /> } label="More options" variant="standard" />
					</>
				}
				leading={
					<IconButton icon={ <Icon name="menu" /> } label="Open navigation" variant="standard" />
				}
				scrolled={ scrolled }
				subtitle={ subtitle }
				title={ <span>{ title }</span> }
				variant={ variant }
			/>
			<p className="ax-stylebook-page__note">
				{ metrics
					? variant + ( subtitle ? ' with subtitle' : '' ) + ': ' + metrics.width + 'x' + metrics.height + 'px · title slot child <' + metrics.titleTag + '> · centre offset ' + metrics.centerOffset + 'px · surface ' + metrics.color
					: 'Measuring...' }
			</p>
		</div>
	);
}

function ScaffoldSample() {
	return (
		<div className="ax-stylebook-app-bars__scaffold-frame">
			<Scaffold
				appBar={
					<AppBar
						leading={
							<IconButton icon={ <Icon name="arrow_back" /> } label="Back" variant="standard" />
						}
						title={ <span>Scaffold-owned band</span> }
					/>
				}
			>
				<div className="ax-stylebook-app-bars__scaffold-body">Page content begins below the app bar.</div>
			</Scaffold>
		</div>
	);
}

export function StylebookAppBarsPage() {
	return (
		<Scaffold className="axismundi-social ax-stylebook ax-stylebook--app-bars">
			<div className="ax-stylebook-page">
				<nav className="ax-stylebook-page__navigation" aria-label="Stylebook">
					<a href="/social/stylebook">Stylebook</a>
					<a href="/social/stylebook/components/navigations">Navigation</a>
					<a href="/social/stylebook/components/icon-buttons">Icon buttons</a>
				</nav>

				<section className="ax-stylebook-page__section" aria-labelledby="ax-stylebook-app-bars-title">
					<header className="ax-stylebook-page__section-header">
						<p className="ax-stylebook-page__eyebrow">Material component</p>
						<h1 id="ax-stylebook-app-bars-title">App bars</h1>
					</header>

					<Group kicker="Small, medium flexible, and large flexible" title="Size variants">
						{ VARIANTS.map( ( sample ) => (
							<BarSample { ...sample } key={ sample.name } variant={ sample.name } />
						) ) }
					</Group>

					<Group kicker="Flexible bars grow when a subtitle is present" title="Subtitle">
						<BarSample subtitle={ <span>Collection details</span> } title="Saved items" variant="medium" />
						<BarSample subtitle={ <span>Collection details</span> } title="Saved items" variant="large" />
					</Group>

					<Group kicker="State is supplied by the scrolling surface" title="On scroll">
						<BarSample scrolled title="Scrolled content" variant="small" />
					</Group>

					<Group kicker="The page supplies title semantics; Scaffold supplies the band" title="Composition">
						<BarSample centered title="Centered title" variant="small" />
						<ScaffoldSample />
					</Group>
				</section>
			</div>
		</Scaffold>
	);
}
