/**
 * Internal dependencies
 */
import type { NoticeState } from './notice';
import type { BrandSite, SiteType } from './settings';

export type AddNotice = ( notice: NoticeState | null ) => void;

export type SyncOption = 'sync' | 'no_sync';

export type SelectedMediaMap = Record< number, boolean >;
export type SelectedSitesMap = Record< string, boolean >;
export type MimeTypeMap = Record< string, string >;
export type SyncedSitesMap = Record< number, Record< string, string > >;

export interface AttachmentVersionDimensions {
	width?: number;
	height?: number;
}

export interface AttachmentVersionFile {
	attachment_id?: number;
	path?: string;
	url?: string;
	guid?: string;
	name?: string;
	type?: string;
	alt?: string;
	caption?: string;
	size?: number;
	metadata?: Record< string, unknown >;
	dimensions?: AttachmentVersionDimensions;
	checksum?: string;
	[ key: string ]: unknown;
}

export interface AttachmentVersion {
	last_used?: number;
	file?: AttachmentVersionFile;
}

export interface MediaItem {
	id: number;
	url: string;
	title: string;
	mime_type?: string | null;
	revision: AttachmentVersion[];
}

export interface FailedSite {
	site_name?: string;
	site_url?: string;
	message?: string;
	is_mime_type_error?: boolean;
	[ key: string ]: unknown;
}

export interface ApiBaseResponse {
	success: boolean;
	status?: number;
	message?: string;
	data?: unknown;
}

export interface ApiErrorResponse extends ApiBaseResponse {
	data?: {
		success?: boolean;
		message?: string;
		failed_sites?: FailedSite[];
	};
}

export interface ShareMediaResponse extends ApiBaseResponse {
	data?: {
		failed_sites: FailedSite[];
	};
}

export interface UploadMediaResponse extends ApiBaseResponse {
	data?: {
		message?: string;
	};
}

export interface MediaItemsResponse extends ApiBaseResponse {
	page: number;
	per_page: number;
	total: number;
	total_pages: number;
	media_files: MediaItem[];
	status: number;
	success: boolean;
}

export interface SharedSitesResponse extends ApiBaseResponse {
	shared_sites?: BrandSite[];
}

export interface SiteTypeResponse extends ApiBaseResponse {
	site_type?: SiteType;
}

export interface MultisiteTypeResponse extends ApiBaseResponse {
	multisite_type?: string;
}

export interface SecretKeyResponse extends ApiBaseResponse {
	secret_key?: string | null;
}

export interface GoverningSiteResponse extends ApiBaseResponse {
	governing_site_url?: string | null;
}

export interface SyncedSitesResponse extends ApiBaseResponse {
	data?: SyncedSitesMap;
}

export interface AttachmentHealthResponse extends ApiBaseResponse {
	failed_sites: FailedSite[];
}

export interface SyncAttachmentStatusResponse extends ApiBaseResponse {
	is_sync?: boolean;
}

export interface SyncAttachmentVersionsResponse extends ApiBaseResponse {
	attachment_id: number;
	versions: AttachmentVersion[];
}

export interface ApiFetchOptions< TBody = unknown > {
	baseurl?: string | undefined;
	endpoint: string;
	method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
	nonce?: string | undefined;
	apiKey?: string | undefined;
	body?: TBody | undefined;
	addNotice?: AddNotice | undefined;
	errorMsg?: string | undefined;
	params?: Record< string, string | number > | undefined;
}

export interface FetchMediaItemsOptions {
	search?: string;
	page?: number;
	perPage?: number;
	imageType?: string;
	addNotice?: AddNotice;
}

export interface ShareMediaPayload {
	sync_option: SyncOption;
	brand_sites: string[];
	media_details: MediaItem[];
}

export interface MediaSharingAppProps {
	imageType?: string;
	cardTitle?: string;
	cardDescription?: string;
}

export interface ShareMediaModalProps {
	setIsShareMediaModalOpen: ( isOpen: boolean ) => void;
	getSelectedCount: () => number;
	syncOption: SyncOption;
	brandSites: BrandSite[];
	selectedSites: SelectedSitesMap;
	handleSiteSelect: (
		siteUrlOrMap: string | SelectedSitesMap,
		isBulk?: boolean
	) => void;
	handleShareMedia: () => Promise< void >;
	getSelectedSitesCount: () => number;
	loading: boolean;
	setNotice: AddNotice;
}

export interface VersionModalProps {
	setIsVersionModalOpen: ( isOpen: boolean ) => void;
	attachmentVersions?: AttachmentVersion[];
	handleVersionSelect: (
		version: AttachmentVersion
	) => Promise< void > | void;
	loading: boolean;
}
