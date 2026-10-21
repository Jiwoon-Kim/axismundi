import { useState } from '@wordpress/element';
import { ProgressIndicator } from '../../../../components/progress-indicators';
import { Scaffold } from '../../../../foundations/layout/scaffold';
import '../../stylebook-page.css';
import './progress-page.css';

const patterns = [
	{ name: 'Flat', wavy: false, thick: false },
	{ name: 'Flat thick', wavy: false, thick: true },
	{ name: 'Wavy', wavy: true, thick: false },
	{ name: 'Wavy thick', wavy: true, thick: true },
];

function PatternMatrix( { circular, animated } ) {
	const name = circular ? 'Circular' : 'Linear';
	return <section className="ax-stylebook-page__group">
		<h2>{ name } patterns</h2>
		<div className="ax-progress-page__matrix-scroll"><table className={ `ax-progress-page__matrix${ circular ? ' ax-progress-page__matrix--circular' : '' }` }>
			<thead><tr><th scope="col">Progress</th>{ patterns.map( ( pattern ) => <th key={ pattern.name } scope="col">{ pattern.name }</th> ) }</tr></thead>
			<tbody>{ [ 0, 10, 20, 50, 80, 100 ].map( ( value ) => <tr key={ value }>
				<th scope="row">{ value }%</th>{ patterns.map( ( { name: pattern, ...props } ) => <td key={ pattern }><ProgressIndicator { ...props } circular={ circular } value={ value } max={ 100 } animated={ animated } label={ `${ name } ${ pattern } ${ value }%` } /></td> ) }
			</tr> ) }
			{ [ 0, 675 ].map( ( delay ) => <tr key={ `indeterminate-${ delay }` }><th scope="row">Indeterminate{ delay ? ' B' : ' A' }</th>
				{ patterns.map( ( { name: pattern, ...props } ) => <td key={ pattern }><ProgressIndicator { ...props } circular={ circular } animated={ animated } style={ { animationDelay: `${ -delay }ms` } } label={ `${ name } ${ pattern } indeterminate ${ delay }` } /></td> ) }
			</tr> ) }</tbody>
		</table></div>
	</section>;
}

export function StylebookProgressIndicatorsPage() {
	const [ value, setValue ] = useState( 40 );
	const [ animated, setAnimated ] = useState( true );
	const [ reduced, setReduced ] = useState( false );
	const [ inspect, setInspect ] = useState( false );
	const [ time, setTime ] = useState( 500 );
	const [ reference, setReference ] = useState( false );
	return <Scaffold className="ax-stylebook"><div className="ax-stylebook-page ax-progress-page" data-reduced={ reduced ? '' : undefined }
		data-inspect={ inspect ? '' : undefined } style={ { '--ax-progress-seek': `${ -time }ms` } }>
		<nav aria-label="Stylebook"><a href="/stylebook">Stylebook</a></nav>
		<h1>Progress indicators</h1>
		<details className="ax-stylebook-page__group">
			<summary>Material Web comparison</summary>
			<label><input type="checkbox" checked={ reference } onChange={ ( event ) => setReference( event.target.checked ) } />Material Web demo</label>
			<div className="ax-progress-page__reference-local" key={ `reference-${ inspect }` }>
				<ProgressIndicator value={ value } max={ 100 } label="Comparison determinate linear" />
				<ProgressIndicator animated={ animated } label="Comparison indeterminate linear" />
				<div className="ax-progress-page__circles">
					<ProgressIndicator circular value={ value } max={ 100 } label="Comparison determinate circular" />
					<ProgressIndicator circular animated={ animated } label="Comparison indeterminate circular" />
				</div>
			</div>
			{ reference && <iframe className="ax-progress-page__reference" title="Official Material Web progress demo" src="https://material-web.dev/components/progress/stories/" referrerPolicy="no-referrer" sandbox="allow-scripts allow-same-origin" /> }
			<a href="https://material-web.dev/components/progress/#interactive-demo" target="_blank" rel="noreferrer">Material Web</a>
		</details>
		<div className="ax-progress-page__controls">
			<label>Progress <input type="range" min="0" max="100" value={ value } onChange={ ( event ) => setValue( Number( event.target.value ) ) } /><output>{ value }%</output></label>
			<label><input type="checkbox" checked={ animated } onChange={ ( event ) => setAnimated( event.target.checked ) } />Animation</label>
			<label><input type="checkbox" checked={ reduced } onChange={ ( event ) => setReduced( event.target.checked ) } />Reduced motion preview</label>
			<label><input type="checkbox" checked={ inspect } onChange={ ( event ) => setInspect( event.target.checked ) } />Inspect frame</label>
			{ inspect && <label>Timeline <input type="range" min="0" max="6000" step="50" value={ time } onChange={ ( event ) => setTime( Number( event.target.value ) ) } /><output>{ time }ms</output></label> }
		</div>
		<PatternMatrix circular animated={ animated } key={ `circular-patterns-${ inspect }` } />
		<PatternMatrix animated={ animated } key={ `linear-patterns-${ inspect }` } />
		<section key={ `linear-${ inspect }` } className="ax-stylebook-page__group"><h2>Linear</h2>
			<div className="ax-progress-page__row"><span>Determinate</span><ProgressIndicator value={ value } max={ 100 } label="Uploading media" /></div>
			<div className="ax-progress-page__row"><span>Indeterminate</span><ProgressIndicator animated={ animated } label="Preparing media" /></div>
			<div className="ax-progress-page__row" dir="rtl"><span>RTL</span><ProgressIndicator value={ value } max={ 100 } label="RTL upload" /></div>
		</section>
		<section key={ `circular-${ inspect }` } className="ax-stylebook-page__group"><h2>Circular</h2><div className="ax-progress-page__circles">
			<ProgressIndicator circular value={ value } max={ 100 } label="Uploading attachment" />
			<ProgressIndicator circular animated={ animated } label="Preparing attachment" />
			<ProgressIndicator circular value={ value } max={ 100 } showTrack={ false } label="Trackless upload" />
		</div></section>
		<section className="ax-stylebook-page__group"><h2>Boundaries</h2>
			{ [ 0, .01, .99, 1 ].map( ( progress ) => <div className="ax-progress-page__row" key={ progress }><span>{ progress * 100 }%</span><ProgressIndicator value={ progress } label={ `Boundary ${ progress }` } /><ProgressIndicator circular value={ progress } label={ `Circular boundary ${ progress }` } /></div> ) }
		</section>
	</div></Scaffold>;
}
