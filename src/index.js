import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

import Dashboard from './components/Dashboard';
import './styles/tokens.css';
import './styles/dashboard.css';

domReady( () => {
	const container = document.getElementById( 'profitlens-root' );

	if ( ! container ) {
		return;
	}

	createRoot( container ).render( <Dashboard /> );
} );
