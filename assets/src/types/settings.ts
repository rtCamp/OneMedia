export type SiteType = 'brand-site' | 'governing-site' | '';

export interface BrandSite {
	id?: string;
	name: string;
	url: string;
	api_key: string;
}

export type EditingIndex = number | null;
