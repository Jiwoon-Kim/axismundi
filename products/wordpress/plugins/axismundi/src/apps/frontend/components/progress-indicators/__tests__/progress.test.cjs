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
const file = path.resolve( __dirname, '../progress-indicator.js' );
const localRequire = createRequire( file );
const code = babel.transformSync( fs.readFileSync( file, 'utf8' ), {
	configFile: false, babelrc: false,
	plugins: [ [ require.resolve( '@babel/plugin-transform-react-jsx' ), { runtime: 'automatic' } ], require.resolve( '@babel/plugin-transform-modules-commonjs' ) ],
} ).code;
function harness() {
	const dom = new JSDOM( '<div id="root"></div>', { pretendToBeVisual: true } );
	global.window = dom.window; global.document = dom.window.document;
	global.IS_REACT_ACT_ENVIRONMENT = true;
	let resize, intersection, disconnected = 0;
	const module = { exports: {} };
	vm.runInNewContext( code, { module, exports: module.exports, window, document,
		require: ( id ) => id === '@wordpress/element' ? React : id === '@wordpress/warning' ? () => {} : localRequire( id ),
		ResizeObserver: class { constructor( cb ) { resize = cb; } observe() {} disconnect() { ++disconnected; } },
		IntersectionObserver: class { constructor( cb ) { intersection = cb; } observe() {} disconnect() { ++disconnected; } },
	} );
	const root = createRoot( document.getElementById( 'root' ) );
	return {
		async render( props = {} ) { await React.act( async () => root.render( React.createElement( module.exports.ProgressIndicator, props ) ) ); },
		async width( width ) { await React.act( async () => resize( [ { contentRect: { width } } ] ) ); },
		async visible( value ) { await React.act( async () => intersection( [ { isIntersecting: value } ] ) ); },
		async hidden( value ) { await React.act( async () => { Object.defineProperty( document, 'hidden', { value, configurable: true } ); document.dispatchEvent( new window.Event( 'visibilitychange' ) ); } ); },
		get: () => document.querySelector( '.ax-progress' ),
		async close() { await React.act( async () => root.unmount() ); dom.window.close(); return disconnected; },
	};
}
test( 'four modes have names and truthful progress semantics', async () => {
	for ( const circular of [ false, true ] ) {
		const h = harness();
		try {
			await h.render( { circular, label: 'Uploading', value: 25, max: 100 } );
			assert.equal( h.get().getAttribute( 'aria-valuenow' ), '25' );
			assert.equal( h.get().getAttribute( 'aria-valuemax' ), '100' );
			assert.equal( h.get().getAttribute( 'aria-label' ), 'Uploading' );
			await h.render( { circular, label: 'Preparing' } );
			assert.equal( h.get().getAttribute( 'aria-valuenow' ), null );
			assert.ok( h.get().hasAttribute( 'data-indeterminate' ) );
			assert.equal( h.get().querySelector( '.ax-progress__stop' ), null );
		} finally { await h.close(); }
	}
} );
test( 'clamps values; invalid values do not invent determinate progress', async () => {
	const h = harness();
	try {
		for ( const [ value, expected ] of [ [ -5, '0' ], [ 5, '1' ], [ NaN, null ], [ Infinity, null ] ] ) {
			await h.render( { value } ); assert.equal( h.get().getAttribute( 'aria-valuenow' ), expected );
		}
		await h.render( { value: .5, max: 0 } ); assert.equal( h.get().getAttribute( 'aria-valuemax' ), '1' );
	} finally { await h.close(); }
} );
test( 'linear width tracks its container and preserves physical gap and stop size', async () => {
	const h = harness();
	try {
		await h.render( { value: .5 } ); await h.width( 320 );
		assert.equal( h.get().querySelector( '.ax-progress__active' ).getAttribute( 'width' ), '160' );
		assert.equal( h.get().querySelector( '.ax-progress__track' ).getAttribute( 'x' ), '164' );
		assert.equal( h.get().querySelector( '.ax-progress__stop' ).getAttribute( 'r' ), '2' );
		await h.render( { value: 1 } );
		assert.equal( h.get().querySelector( '.ax-progress__track' ), null );
		assert.equal( h.get().querySelector( '.ax-progress__stop' ), null );
		await h.render( { value: 0 } ); assert.equal( h.get().querySelector( '.ax-progress__active' ), null );
	} finally { await h.close(); }
} );
test( 'linear indeterminate has two active segments and matching two cutouts', async () => {
	const h = harness();
	try {
		await h.render();
		assert.equal( h.get().querySelectorAll( '.ax-progress__active .ax-progress__line' ).length, 2 );
		assert.equal( h.get().querySelectorAll( 'mask .ax-progress__line[data-cutout]' ).length, 2 );
		assert.equal( h.get().querySelector( '.ax-progress__travel' ), null );
	} finally { await h.close(); }
} );
test( 'circular endpoints and masks remain finite at zero, tiny, near full, and full', async () => {
	const h = harness();
	try {
		for ( const value of [ 0, .001, .4, .999, 1 ] ) {
			await h.render( { circular: true, value } );
			assert.doesNotMatch( h.get().outerHTML, /NaN|Infinity/ );
			if ( value === 0 ) assert.equal( h.get().querySelector( '.ax-progress__active' ), null );
			if ( value === 1 ) assert.equal( h.get().querySelector( '.ax-progress__active' ).getAttribute( 'stroke-dasharray' ), null );
		}
		await h.render( { circular: true } );
		const id = h.get().querySelector( 'mask' ).id;
		assert.equal( h.get().querySelector( '.ax-progress__track' ).getAttribute( 'mask' ), `url(#${ id })` );
	} finally { await h.close(); }
} );
test( 'visibility pauses work, resumes, and observers disconnect on unmount', async () => {
	const h = harness(); await h.render();
	await h.visible( false ); assert.ok( h.get().hasAttribute( 'data-paused' ) );
	await h.visible( true ); assert.ok( ! h.get().hasAttribute( 'data-paused' ) );
	await h.hidden( true ); assert.ok( h.get().hasAttribute( 'data-paused' ) );
	await h.hidden( false ); assert.ok( ! h.get().hasAttribute( 'data-paused' ) );
	assert.equal( await h.close(), 2 );
} );
test( 'all Figma values and thickness/shape variants preserve progress and endpoints', async () => {
	const h = harness();
	try {
		for ( const circular of [ false, true ] ) for ( const wavy of [ false, true ] ) for ( const thick of [ false, true ] ) {
			for ( const value of [ 0, 10, 20, 50, 80, 100 ] ) {
				await h.render( { circular, wavy, thick, value, max: 100 } );
				assert.equal( h.get().getAttribute( 'aria-valuenow' ), String( value ) );
				assert.doesNotMatch( h.get().outerHTML, /NaN|Infinity/ );
				if ( value === 0 ) assert.equal( h.get().querySelector( '.ax-progress__active' ), null );
				if ( value === 100 ) assert.equal( h.get().querySelector( '.ax-progress__track' ), null );
				assert.equal( h.get().style.getPropertyValue( '--ax-progress-stroke' ), thick ? '8px' : '4px' );
			}
			await h.render( { circular, wavy, thick } );
			assert.equal( h.get().getAttribute( 'aria-valuenow' ), null );
		}
	} finally { await h.close(); }
} );
