import { Scaffold } from './foundations/layout/scaffold';
import { Component, Suspense, lazy, useEffect, useState } from '@wordpress/element';
import { LoadingIndicator } from './components/loading-indicators';

/*
 * The Stylebook is a development and VQA surface, and it was a third of the
 * CSS everyone downloaded to read a feed. It is the right first route to split
 * because it is also the one that can fail safely: there is no offline caching
 * yet, so a chunk that cannot be fetched is a page that does not open, and a
 * stylebook that does not open offline is not an incident.
 *
 * Its CSS travels with it. Moving the import out of `styles/index.css` is what
 * actually splits the stylesheet -- a lazy route whose CSS is still reached
 * from the entry graph loads exactly as much CSS as before.
 */
const StylebookPage = lazy( () =>
	import( './pages/stylebook' ).then( ( module ) => ( { default: module.StylebookPage } ) )
);

function getFrontendRoute() {
	return window.axismundiCapstone?.resolvedRoute ?? { name: 'home' };
}

function isAuthenticatedViewer() {
	return true === window.axismundiCapstone?.viewer?.authenticated;
}

function HomeTemplate() {
	const authenticated = isAuthenticatedViewer();

	return (
		<section className="ax-social-shell" aria-labelledby="ax-social-shell-title">
			<header className="ax-social-shell__header">
				<p className="ax-social-shell__eyebrow">Axismundi</p>
				<h1 id="ax-social-shell-title" className="ax-social-shell__title">
					{ authenticated ? 'Home' : 'Social' }
				</h1>
			</header>
			<p className="ax-social-shell__message">
				{ authenticated
					? 'Your home feed will render here.'
					: 'Sign in to view your home feed.' }
			</p>
			{ ! authenticated && window.axismundiCapstone?.loginUrl ? (
				<p>
					<a href={ window.axismundiCapstone.loginUrl }>Sign in</a>
				</p>
			) : null }
		</section>
	);
}

function PublicObjectTemplate( { objectId } ) {
	const [ result, setResult ] = useState( { loading: true } );
	useEffect( () => {
		const controller = new AbortController();
		setResult( { loading: true } );
		const config = window.axismundiCapstone?.objectRead;
		if ( ! /^[1-9][0-9]*$/.test( objectId ?? '' ) || ! config?.url ) {
			setResult( { error: 'Invalid object identifier.' } );
			return () => controller.abort();
		}
		const headers = config.nonce ? { 'X-WP-Nonce': config.nonce } : {};
		fetch( config.url + encodeURIComponent( objectId ), { credentials: 'same-origin', headers, signal: controller.signal } )
			.then( async ( response ) => {
				const document = await response.json();
				if ( ! response.ok && response.status !== 410 ) {
					throw new Error( document.message || 'Object could not be loaded.' );
				}
				if ( ! controller.signal.aborted ) setResult( { document } );
			} )
			.catch( ( error ) => {
				if ( ! controller.signal.aborted ) setResult( { error: error.message } );
			} );
		return () => controller.abort();
	}, [ objectId ] );
	return (
		<section className="ax-social-shell" aria-labelledby="ax-social-object-title" aria-busy={ !! result.loading }>
			<header className="ax-social-shell__header">
				<p className="ax-social-shell__eyebrow">Axismundi</p>
				<h1 id="ax-social-object-title" className="ax-social-shell__title">Object</h1>
			</header>
			{ result.loading ? <LoadingIndicator label="Loading object" /> : null }
			{ result.error ? <p role="alert">{ result.error }</p> : null }
			{ result.document ? <pre style={ { whiteSpace: 'pre-wrap', overflowWrap: 'anywhere' } }>{ JSON.stringify( result.document, null, 2 ) }</pre> : null }
		</section>
	);
}

/*
 * A dynamic import can reject -- offline, or a chunk that 404s after a deploy
 * replaced it. React unmounts the tree on a render error, so without a boundary
 * the page goes blank and says nothing. This says what happened and offers the
 * one action that can fix it.
 */
class ChunkBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { failed: false };
	}

	static getDerivedStateFromError() {
		return { failed: true };
	}

	render() {
		if ( ! this.state.failed ) {
			return this.props.children;
		}

		return (
			<div className="ax-route-error" role="alert">
				<p>This page could not be loaded. It is fetched on demand, so it needs a connection the first time.</p>
				<button onClick={ () => window.location.reload() } type="button">Try again</button>
			</div>
		);
	}
}

export function FrontendApp() {
	const route = getFrontendRoute();

	if ( 'stylebook' === route.name ) {
		return (
			<ChunkBoundary>
				<Suspense fallback={ <div className="ax-route-pending" role="status">Loading…</div> }>
					<StylebookPage component={ route.component } layout={ route.layout } style={ route.style } styles={ route.styles } />
				</Suspense>
			</ChunkBoundary>
		);
	}

	return (
		<Scaffold>
			{ 'object' === route.name ? <PublicObjectTemplate objectId={ route.objectId } /> : <HomeTemplate /> }
		</Scaffold>
	);
}
