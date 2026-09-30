import { createElement, createRoot } from '@wordpress/element';
import { AdminApp } from './app';
import { SidebarNavigationProvider } from './sidebar/navigation';
import './styles/index.css';

const root = document.getElementById( 'axismundi-root' );

if ( root ) {
	createRoot( root ).render(
		createElement( SidebarNavigationProvider, null, createElement( AdminApp ) )
	);
}
