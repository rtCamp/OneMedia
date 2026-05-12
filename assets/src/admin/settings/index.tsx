/**
 * WordPress dependencies
 */
/**
 * External dependencies
 */
import { createRoot } from 'react-dom/client';

/**
 * Internal dependencies
 */
import SettingsPage from './page';

// Render to Gutenberg admin page with ID: onemedia-settings-page
const target = document.getElementById( 'onemedia-settings-page' );
if ( target ) {
	const root = createRoot( target );
	root.render( <SettingsPage /> );
}
