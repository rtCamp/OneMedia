import { createRoot } from 'react-dom/client';
import OnboardingScreen, { type SiteType } from './page';

interface OneMediaSettings {
	restNonce: string;
	site_type: SiteType | '';
	settingsLink: string;
}

declare global {
	interface Window {
		OneMediaSettings: OneMediaSettings;
	}
}

// Render to the target element.
const target = document.getElementById( 'onemedia-site-selection-modal' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OnboardingScreen /> );
}
