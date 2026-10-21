import { useState } from '@wordpress/element';
import { LoadingIndicator } from '../../../../components/loading-indicators';
import { Button } from '../../../../components/buttons/button';
import { Icon } from '../../../../components/material/icon';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import '../../stylebook-page.css';

export function StylebookLoadingIndicatorsPage() {
	const [ animated, setAnimated ] = useState( true );
	return (
		<Scaffold className="ax-stylebook">
			<div className="ax-stylebook-page" aria-labelledby="loading-title">
				<nav aria-label="Stylebook"><a href="/stylebook">Stylebook</a></nav>
				<h1 id="loading-title">Loading indicators</h1>
				<Button icon={ <Icon name={ animated ? 'pause' : 'play_arrow' } /> } onClick={ () => setAnimated( ! animated ) }>{ animated ? 'Pause' : 'Play' }</Button>
				<section className="ax-stylebook-page__group" aria-label="Default and contained">
					<h2>Default and contained</h2>
					<div style={ { display: 'flex', gap: 24, alignItems: 'center' } }>
						<LoadingIndicator animated={ animated } label="Loading articles" />
						<LoadingIndicator animated={ animated } contained label="Refreshing feed" />
					</div>
				</section>
				<section className="ax-stylebook-page__group" aria-label="Sizes">
					<h2>Sizes</h2>
					<div style={ { display: 'flex', gap: 24, alignItems: 'center', flexWrap: 'wrap' } }>
						{ [ 24, 48, 96, 240 ].map( ( size ) => <LoadingIndicator key={ size } size={ size } animated={ animated } label={ `Loading media (${ size })` } /> ) }
					</div>
				</section>
				<section className="ax-stylebook-page__group" aria-label="Static alternative">
					<h2>Static alternative</h2>
					<LoadingIndicator animated={ false } contained label="Loading comments" />
				</section>
			</div>
		</Scaffold>
	);
}
