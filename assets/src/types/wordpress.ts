export interface WPMediaAttachmentData {
	id: number;
	url?: string;
	title?: string;
	alt?: string;
	is_onemedia_sync?: boolean;
	[ key: string ]: unknown;
}

export interface WPMediaAttachmentModel {
	get: ( key: string ) => unknown;
	fetch: () => Promise< unknown >;
	toJSON: () => WPMediaAttachmentData;
}

export interface WPMediaSelection {
	first: () => { toJSON: () => WPMediaAttachmentData };
	add: ( attachment: WPMediaAttachmentModel ) => void;
	reset: () => void;
}

export interface WPMediaLibrary {
	observe: ( queue: unknown ) => void;
}

export interface WPMediaUploader {
	settings: {
		multipart_params: Record< string, unknown >;
	};
	setOption: ( key: string, value: unknown ) => void;
}

export interface WPMediaFrame {
	el?: HTMLElement;
	content: {
		mode: ( mode: string ) => void;
	};
	uploader: {
		uploader: {
			uploader: WPMediaUploader;
		};
	};
	state: () => {
		get: ( key: string ) => WPMediaSelection | WPMediaLibrary | undefined;
	};
	on: ( event: string, callback: ( ...args: unknown[] ) => void ) => void;
	once: ( event: string, callback: () => void ) => void;
	open: () => void;
}
