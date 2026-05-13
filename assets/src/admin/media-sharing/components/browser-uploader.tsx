/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import {
	uploadMedia,
	updateExistingAttachment,
	checkIfAllSitesConnected,
	isSyncAttachment as isSyncAttachmentApi,
} from '../../../components/api';

import {
	getAllowedMimeTypeExtensions,
	getFrameProperty,
	getAllowedMimeTypes,
	restrictMediaFrameUploadTypes,
	showSnackbarNotice,
} from '../../../js/utils';

import type {
	AddNotice,
	FailedSite,
	MediaItem,
	MimeTypeMap,
} from '../../../types/media-sharing';

interface BrowserUploaderButtonProps {
	onAddMediaSuccess?: () => void;
	isSyncMediaUpload?: boolean;
	attachmentId?: number | string;
	addedMedia?: MediaItem[];
	setNotice: AddNotice;
}

const UPLOAD_NONCE = window.OneMediaMediaFrame.uploadNonce || '';
const ALLOWED_MIME_TYPES_MAP: MimeTypeMap =
	window.OneMediaMediaFrame.allowedMimeTypesMap ?? {};

const BrowserUploaderButton = ( {
	onAddMediaSuccess,
	isSyncMediaUpload = false,
	attachmentId,
	addedMedia = [],
	setNotice,
}: BrowserUploaderButtonProps ) => {
	const [ isUploading, setIsUploading ] = useState( false );
	const fileInputRef = useRef< HTMLInputElement | null >( null );

	const isReplaceMedia = Boolean( attachmentId );

	let buttonText: string = __( 'Add Non Sync Media', 'onemedia' );

	if ( isSyncMediaUpload ) {
		buttonText = __( 'Add Sync Media', 'onemedia' );
	} else if ( isReplaceMedia ) {
		buttonText = __( 'Replace Media', 'onemedia' );
	}

	const failedSitesMessage = (
		initialMessage: string,
		failedSites: FailedSite[] = []
	) => (
		<div>
			<span>
				{ sprintf(
					/* translators: %s: initial message. */
					__(
						'%s Please check your connection for unreachable sites:',
						'onemedia'
					),
					initialMessage
				) }
			</span>
			{ failedSites.map( ( site, index ) => (
				<div key={ index }>
					<span>{ site.site_name }</span>
				</div>
			) ) }
		</div>
	);

	const handleButtonClick = async (): Promise< void > => {
		if ( ! getFrameProperty( 'wp.media' ) ) {
			setNotice( {
				type: 'error',
				message: __( 'Media library is not available.', 'onemedia' ),
			} );
			fileInputRef.current?.click();
			return;
		}

		if ( isReplaceMedia && attachmentId ) {
			const numericAttachmentId = Number( attachmentId );
			const response =
				await checkIfAllSitesConnected( numericAttachmentId );

			if ( ! response.success || response.failed_sites.length > 0 ) {
				showSnackbarNotice( {
					type: 'error',
					message: failedSitesMessage(
						__( 'Failed to replace media.', 'onemedia' ),
						response.failed_sites
					),
				} );
				return;
			}

			fileInputRef.current?.click();
			return;
		}

		if ( isReplaceMedia ) {
			return;
		}

		if ( isSyncMediaUpload ) {
			openSyncMediaFrame();
			return;
		}

		openNonSyncMediaFrame();
	};

	const openSyncMediaFrame = () => {
		const frame = window.wp.media( {
			title: __( 'Select Sync Media', 'onemedia' ),
			button: {
				text: __( 'Select', 'onemedia' ),
			},
			multiple: false,
			library: {
				type: getAllowedMimeTypes( ALLOWED_MIME_TYPES_MAP ),
				is_onemedia_sync: false,
			},
		} );

		restrictMediaFrameUploadTypes(
			frame,
			getAllowedMimeTypeExtensions( ALLOWED_MIME_TYPES_MAP ).join( ',' )
		);

		frame.on( 'open', () => {
			if ( frame.el ) {
				frame.el.classList.add( 'onemedia-select-sync-media-frame' );
			}
		} );

		frame.on( 'select', async () => {
			const selection = frame.state().get( 'selection' );
			if ( ! selection || ! ( 'first' in selection ) ) {
				return;
			}

			const attachment = selection.first().toJSON();

			if ( ! attachment.url ) {
				setNotice( {
					type: 'error',
					message: __( 'No image selected.', 'onemedia' ),
				} );
				return;
			}

			setIsUploading( true );

			try {
				const healthCheckResponse = await checkIfAllSitesConnected(
					attachment.id
				);

				if (
					! healthCheckResponse.success ||
					healthCheckResponse.failed_sites.length > 0
				) {
					throw new Error(
						sprintf(
							/* translators: %s: list of failed sites. */
							__(
								'Media conversion failed for some sites: %s',
								'onemedia'
							),
							healthCheckResponse.failed_sites
								.map( ( site ) => site.site_name )
								.filter( Boolean )
								.join( ', ' ) || ''
						)
					);
				}

				const response = await updateExistingAttachment(
					attachment.id,
					isSyncMediaUpload,
					setNotice
				);

				if ( ! response.success ) {
					setNotice( {
						type: 'warning',
						message:
							typeof response.message === 'string'
								? response.message
								: __(
										'Failed to update sync attachment.',
										'onemedia'
								  ),
					} );
					return;
				}

				onAddMediaSuccess?.();

				setNotice( {
					type: 'success',
					message:
						typeof response.message === 'string'
							? response.message
							: __(
									'Sync media added successfully!',
									'onemedia'
							  ),
				} );
			} catch ( error ) {
				setNotice( {
					type: 'error',
					message:
						error instanceof Error
							? error.message
							: __(
									'Failed to update sync attachment.',
									'onemedia'
							  ),
				} );
			} finally {
				setIsUploading( false );
			}
		} );

		frame.on( 'close', () => {
			onAddMediaSuccess?.();
		} );

		frame.open();
	};

	const openNonSyncMediaFrame = () => {
		const frame = window.wp.media( {
			title: __( 'Upload Non-Sync Media', 'onemedia' ),
			button: {
				text: __( 'Add', 'onemedia' ),
			},
			multiple: false,
			library: {
				type: getAllowedMimeTypes( ALLOWED_MIME_TYPES_MAP ),
				is_onemedia_sync: false,
			},
		} );

		restrictMediaFrameUploadTypes(
			frame,
			getAllowedMimeTypeExtensions( ALLOWED_MIME_TYPES_MAP ).join( ',' )
		);

		frame.on( 'open', () => {
			if ( frame.el ) {
				frame.el.classList.add(
					'onemedia-select-non-sync-media-frame'
				);
			}
		} );

		frame.on( 'select', async () => {
			const selection = frame.state().get( 'selection' );
			if ( ! selection || ! ( 'first' in selection ) ) {
				return;
			}

			const attachment = selection.first().toJSON();

			if ( ! attachment.url ) {
				setNotice( {
					type: 'error',
					message: __( 'No image selected.', 'onemedia' ),
				} );
				return;
			}

			const alreadyAdded = addedMedia.some(
				( media ) => media.id === attachment.id
			);
			if ( alreadyAdded ) {
				setNotice( {
					type: 'warning',
					message: __( 'Media has been added already.', 'onemedia' ),
				} );
				return;
			}

			const attachmentIsSync = await isSyncAttachmentApi(
				attachment.id,
				setNotice
			);
			if ( attachmentIsSync ) {
				setNotice( {
					type: 'warning',
					message: __(
						'Media is already added to "Sync Media" tab.',
						'onemedia'
					),
				} );
				return;
			}

			setNotice( {
				type: 'success',
				message: __( 'Media added successfully.', 'onemedia' ),
			} );
		} );

		frame.on( 'close', () => {
			onAddMediaSuccess?.();
		} );

		frame.open();
	};

	const uploadFile = async ( file: File ) => {
		const formData = new FormData();
		formData.append( 'file', file );
		formData.append( 'action', 'onemedia_replace_media' );

		if ( isReplaceMedia && attachmentId ) {
			formData.append( 'current_media_id', String( attachmentId ) );
		}

		if ( UPLOAD_NONCE ) {
			formData.append( '_ajax_nonce', UPLOAD_NONCE );
		}

		try {
			const data = await uploadMedia( formData, setNotice );
			if ( data?.success ) {
				onAddMediaSuccess?.();
				setNotice( {
					type: 'success',
					message: isReplaceMedia
						? __( 'Media replaced successfully!', 'onemedia' )
						: __( 'Media uploaded successfully!', 'onemedia' ),
				} );
			} else {
				throw new Error(
					data?.data?.message || __( 'Upload failed', 'onemedia' )
				);
			}
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message:
					error instanceof Error
						? error.message
						: __( 'An error occurred during upload.', 'onemedia' ),
			} );
		} finally {
			setIsUploading( false );
			if ( fileInputRef.current ) {
				fileInputRef.current.value = '';
			}
		}
	};

	const handleFileSelect = (
		event: React.ChangeEvent< HTMLInputElement >
	) => {
		const file = event.target.files?.[ 0 ];
		if ( ! file ) {
			return;
		}

		const mimeTypes = getAllowedMimeTypes( ALLOWED_MIME_TYPES_MAP );
		if ( mimeTypes.length === 0 || ! mimeTypes.includes( file.type ) ) {
			setNotice( {
				type: 'error',
				message: __( 'Please select a valid image file.', 'onemedia' ),
			} );
			return;
		}

		setIsUploading( true );
		void uploadFile( file );
	};

	return (
		<>
			{ ! isSyncMediaUpload && (
				<input
					className="onemedia-hidden-file-input"
					type="file"
					accept={ getAllowedMimeTypes( ALLOWED_MIME_TYPES_MAP ).join(
						','
					) }
					ref={ fileInputRef }
					onChange={ handleFileSelect }
				/>
			) }

			<Button
				variant="primary"
				onClick={ () => {
					void handleButtonClick();
				} }
				isBusy={ isUploading }
				disabled={ isUploading }
			>
				{ isUploading ? __( 'Adding…', 'onemedia' ) : buttonText }
			</Button>
		</>
	);
};

export default BrowserUploaderButton;
