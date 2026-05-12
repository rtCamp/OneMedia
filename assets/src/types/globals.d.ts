import type {
	OneMediaMediaFrameConfig,
	OneMediaMediaSharingConfig,
	OneMediaMediaUploadConfig,
} from './media-sharing';
import type {
	OneMediaOnboardingConfig,
	OneMediaSettingsConfig,
} from './settings';
import type { OneMediaWordPressGlobal } from './wordpress';

declare global {
	interface Window {
		OneMediaSettings: OneMediaSettingsConfig;
		OneMediaOnboarding: OneMediaOnboardingConfig;
		OneMediaMediaFrame: OneMediaMediaFrameConfig;
		OneMediaMediaSharing: OneMediaMediaSharingConfig;
		OneMediaMediaUpload?: OneMediaMediaUploadConfig;
		wp: OneMediaWordPressGlobal;
	}
}

declare module '*.svg' {
	const source: string;
	export default source;
}

export {};
