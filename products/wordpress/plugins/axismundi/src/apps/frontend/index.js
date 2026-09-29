import { createElement, createRoot } from '@wordpress/element';
import { FrontendApp } from './app';
import './styles/index.css';

const root = document.getElementById( 'axismundi-root' );

if ( root ) {
	createRoot( root ).render( createElement( FrontendApp ) );
}
