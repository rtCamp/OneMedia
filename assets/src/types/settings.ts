export const BRAND_SITE = 'brand-site';
export const GOVERNING_SITE = 'governing-site';

export type SiteType = typeof BRAND_SITE | typeof GOVERNING_SITE | '';

export interface BrandSite {
	id?: string;
	name: string;
	url: string;
	api_key: string;
}

export const defaultBrandSite: BrandSite = {
	name: '',
	url: '',
	api_key: '',
};

export type EditingIndex = number | null;

export interface OneMediaSettingsConfig {
	restUrl: string;
	restNonce: string;
	api_key: string;
	settingsLink: string;
	siteType: SiteType;
	restNamespace?: string;
	nonce?: string;
	currentSiteUrl?: string;
}

export interface OneMediaOnboardingConfig {
	nonce: string;
	site_type: SiteType;
	setup_url: string;
}
