const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );
const { createRequire } = require( 'node:module' );
const { JSDOM } = require( 'jsdom' );
const React = require( 'react' );
const { createRoot } = require( 'react-dom/client' );
const babel = require( '@babel/core' );

const file = path.resolve( __dirname, '../loading-indicator.js' );
const localRequire = createRequire( file );
const code = babel.transformSync( fs.readFileSync( file, 'utf8' ), {
	configFile: false, babelrc: false,
	plugins: [ [ require.resolve( '@babel/plugin-transform-react-jsx' ), { runtime: 'automatic' } ], require.resolve( '@babel/plugin-transform-modules-commonjs' ) ],
} ).code;

function harness( reduced = false, supported = true ) {
	const dom = new JSDOM( '<div id="root"></div>', { pretendToBeVisual: true } );
	global.window = dom.window;
	global.document = dom.window.document;
	global.IS_REACT_ACT_ENVIRONMENT = true;
	const listeners = new Set();
	const media = { matches: reduced, addEventListener: ( _, f ) => listeners.add( f ), removeEventListener: ( _, f ) => listeners.delete( f ) };
	window.matchMedia = () => media;
	window.CSS = { supports: () => supported };
	let calls = 0;
	let cancelled = 0;
	let disconnected = 0;
	let intersection;
	dom.window.Element.prototype.animate = () => {
		++calls;
		return { finished: new Promise( () => {} ), cancel: () => { ++cancelled; } };
	};
	const module = { exports: {} };
	vm.runInNewContext( code, {
		module, exports: module.exports,
		require: ( id ) => id === '@wordpress/element' ? React : id === '@wordpress/warning' ? () => {} : localRequire( id ),
		window, document,
		IntersectionObserver: class {
			constructor( cb ) { intersection = cb; }
			observe() {}
			disconnect() { ++disconnected; }
		},
		getComputedStyle: () => ( { getPropertyValue: ( name ) => name.endsWith( 'duration' ) ? '650ms' : 'cubic-bezier(0.39, 1.29, 0.35, 0.98)' } ),
	} );
	const root = createRoot( document.getElementById( 'root' ) );
	return {
		async render( props = {} ) { await React.act( async () => root.render( React.createElement( module.exports.LoadingIndicator, props ) ) ); },
		async reduce( value ) { await React.act( async () => { media.matches = value; listeners.forEach( ( f ) => f() ); } ); },
		async intersect( value ) { await React.act( async () => intersection( [ { isIntersecting: value } ] ) ); },
		async hide( value ) { await React.act( async () => {
			Object.defineProperty( document, 'hidden', { value, configurable: true } );
			document.dispatchEvent( new dom.window.Event( 'visibilitychange' ) );
		} ); },
		async close() { await React.act( async () => root.unmount() ); dom.window.close(); },
		state: () => ( { calls, cancelled, disconnected, listeners: listeners.size } ),
		element: () => document.querySelector( '.ax-loading-indicator' ),
	};
}

test( 'reduced-motion initial mount is static and remains indeterminate', async () => {
	const h = harness( true );
	try {
		await h.render( { label: 'Loading articles' } );
		assert.equal( h.state().calls, 0 );
		assert.equal( h.element().getAttribute( 'role' ), 'progressbar' );
		assert.equal( h.element().getAttribute( 'aria-label' ), 'Loading articles' );
		assert.equal( h.element().getAttribute( 'aria-valuenow' ), null );
		assert.ok( h.element().querySelector( 'path' ).getAttribute( 'd' ).length > 100 );
	} finally { await h.close(); }
} );

test( 'motion preference changes cancel and restart; offscreen and unmount clean up', async () => {
	const h = harness();
	await h.render();
	assert.equal( h.state().calls, 2 );
	await h.reduce( true );
	assert.equal( h.state().cancelled, 2 );
	assert.equal( h.state().calls, 2 );
	await h.reduce( false );
	assert.equal( h.state().calls, 4 );
	await h.intersect( false );
	assert.equal( h.state().cancelled, 4 );
	await h.intersect( true );
	assert.equal( h.state().calls, 6 );
	await h.close();
	assert.deepEqual( h.state(), { calls: 6, cancelled: 6, disconnected: 1, listeners: 0 } );
} );

test( 'unsupported morph retains static geometry and a name', async () => {
	const h = harness( false, false );
	try {
		await h.render( { label: 'Refreshing feed', contained: true } );
		assert.equal( h.state().calls, 0 );
		assert.equal( h.element().getAttribute( 'aria-label' ), 'Refreshing feed' );
		assert.equal( h.element().getAttribute( 'data-contained' ), '' );
	} finally { await h.close(); }
} );

test( 'hidden documents stop work and visible documents resume', async () => {
	const h = harness();
	try {
		await h.render();
		assert.equal( h.state().calls, 2 );
		await h.hide( true );
		assert.equal( h.state().cancelled, 2 );
		assert.equal( h.state().calls, 2 );
		await h.hide( false );
		assert.equal( h.state().calls, 4 );
	} finally { await h.close(); }
} );

test( 'explicit static and decorative rendering do not fabricate progress values', async () => {
	const h = harness();
	try {
		await h.render( { animated: false, decorative: true, size: 24 } );
		assert.equal( h.state().calls, 0 );
		assert.equal( h.element().getAttribute( 'aria-hidden' ), 'true' );
		assert.equal( h.element().getAttribute( 'role' ), null );
		assert.equal( h.element().getAttribute( 'aria-valuenow' ), null );
	} finally { await h.close(); }
} );
