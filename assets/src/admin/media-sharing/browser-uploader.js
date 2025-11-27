/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useState, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { UPLOAD_NONCE, ALLOWED_MIME_TYPES } from '../../components/constants';
import { uploadMedia, updateExistingAttachment, checkIfAllSitesConnected, isSyncAttachment as isSyncAttachmentApi } from '../../components/api';
import { getFrameProperty, showSnackbarNotice } from '../../js/utils';

const BrowserUploaderButton = ( {
	onAddMediaSuccess,
	isSyncMediaUpload,
	attachmentId,
	addedMedia,
	setNotice,
} ) => {
	const [ isUploading, setIsUploading ] = useState( false );
	const fileInputRef = useRef( null );

	const isReplaceMedia = !! attachmentId;

	let buttonText;
	if ( isSyncMediaUpload ) {
		buttonText = __( 'Add Sync Media', 'onemedia' );
	} else if ( isReplaceMedia ) {
		buttonText = __( 'Replace Media', 'onemedia' );
	} else {
		buttonText = __( 'Add Non Sync Media', 'onemedia' );
	}

	const failedSitesMessage = ( initialMessage, failedSites ) => (
		<div>
			<span>{ sprintf(
				/* translators: %s: initial message. */
				__( '%s Please check your connection for unreachable sites:', 'onemedia' ),
				( initialMessage ),
			) }</span>
			{ ( failedSites || [] ).map( ( site, idx ) => (
				<div key={ idx }>
					<span>
						{ site?.site_name }
					</span>
				</div>
			) ) }
		</div>
	);

	const handleButtonClick = async () => {
		if ( ! getFrameProperty( 'wp.media' ) ) {
			setNotice( {
				type: 'error',
				message: __( 'Media library is not available.', 'onemedia' ),
			} );

			// Trigger the hidden file input for fallback.
			fileInputRef.current?.click();
			return;
		}

		if ( ! isReplaceMedia ) {
			// Show media library for non sync and sync media.
			if ( isSyncMediaUpload ) {
				// Open WP media library modal for selecting an image.
				const frame = window.wp.media( {
					title: __( 'Select Sync Media', 'onemedia' ),
					button: {
						text: __( 'Select', 'onemedia' ),
					},
					multiple: false,
					library: {
						type: ALLOWED_MIME_TYPES,
					},
				} );

				frame.on( 'select', async () => {
					const selection = frame.state().get( 'selection' );
					const attachment = selection.first().toJSON();
					if ( ! attachment || ! attachment.url ) {
						setNotice( {
							type: 'error',
							message: __( 'No image selected.', 'onemedia' ),
						} );
						return;
					}

					setIsUploading( true );
					try {
						// Check if all the sites for this attachment are connected.
						const healthCheckResponse = await checkIfAllSitesConnected( attachment.id );

						if ( ! healthCheckResponse || ! healthCheckResponse?.success || healthCheckResponse?.failed_sites?.length > 0 ) {
							const failedSites = healthCheckResponse?.failed_sites;

							// Trigger custom event onemediaNotice for showing notice in media frame.
							setNotice( {
								type: 'error',
								message: failedSitesMessage(
									__( 'Failed to convert media.', 'onemedia' ),
									failedSites,
								),
							} );
						} else if ( healthCheckResponse?.success ) {
							// If attachment is already uploaded, trigger update logic.
							const response = await updateExistingAttachment( attachment.id, isSyncMediaUpload, setNotice );
							if ( ! response || ! response.success ) {
								setNotice( {
									type: 'warning',
									message: response?.message || __( 'Failed to update sync attachment.', 'onemedia' ),
								} );
							} else {
								if ( onAddMediaSuccess ) {
									onAddMediaSuccess();
								}
								setNotice( {
									type: 'success',
									message: response?.message || __( 'Sync media added successfully!', 'onemedia' ),
								} );
							}
						}
					} catch ( error ) {
						setNotice( {
							type: 'error',
							message: error.message || __( 'Failed to update sync attachment.', 'onemedia' ),
						} );
					} finally {
						setIsUploading( false );
					}
				} );

				frame.on( 'close', () => {
					if ( onAddMediaSuccess ) {
						onAddMediaSuccess();
					}
				} );

				frame.open();
			} else {
				// Open WP media library modal for selecting an image.
				const frame = window.wp.media( {
					title: __( 'Upload Non-Sync Media', 'onemedia' ),
					button: {
						text: __( 'Add', 'onemedia' ),
					},
					multiple: false,
					library: {
						type: ALLOWED_MIME_TYPES,
					},
				} );

				frame.on( 'select', async () => {
					const selection = frame.state().get( 'selection' );
					const attachment = selection.first().toJSON();
					if ( ! attachment || ! attachment.url ) {
						setNotice( {
							type: 'error',
							message: __( 'No image selected.', 'onemedia' ),
						} );
					}

					// Check if selected media is already added.
					const alreadyAdded = addedMedia?.some( ( media ) => media.id === attachment.id );
					if ( alreadyAdded ) {
						setNotice( {
							type: 'warning',
							message: __( 'Media has been added already.', 'onemedia' ),
						} );
					} else {
						const isSyncAttachment = await isSyncAttachmentApi( attachment.id, setNotice );
						if ( isSyncAttachment ) {
							setNotice( {
								type: 'warning',
								message: __( 'Media is already added to "Sync Media" tab.', 'onemedia' ),
							} );
						} else {
							setNotice( {
								type: 'success',
								message: __( 'Media added successfully.', 'onemedia' ),
							} );
						}
					}
				} );

				frame.on( 'close', () => {
					if ( onAddMediaSuccess ) {
						onAddMediaSuccess();
					}
				} );

				frame.open();
			}
		} else if ( isReplaceMedia && attachmentId ) { // Trigger the hidden file input for replace media.
			// Check if all the sites for this attachment are connected.
			const response = await checkIfAllSitesConnected( attachmentId );

			if ( ! response || ! response?.success || response?.failed_sites?.length > 0 ) {
				const failedSites = response?.failed_sites;

				// Trigger custom event onemediaNotice for showing notice in media frame.
				showSnackbarNotice( {
					type: 'error',
					message: failedSitesMessage(
						__( 'Failed to replace media.', 'onemedia' ),
						failedSites,
					),
				} );
			} else if ( response?.success ) {
				// All sites are connected, proceed with file input.
				fileInputRef.current?.click();
			}
		}
	};

	const handleFileSelect = ( event ) => {
		const file = event.target.files[ 0 ];
		if ( ! file ) {
			return;
		}

		// Validate file type.
		if ( ! ALLOWED_MIME_TYPES.length > 0 || ! ALLOWED_MIME_TYPES.includes( file.type ) ) {
			setNotice( {
				type: 'error',
				message: __( 'Please select a valid image file.', 'onemedia' ),
			} );
			return;
		}

		// Start upload
		setIsUploading( true );
		uploadFile( file );
	};

	const uploadFile = async ( file ) => {
		// Create FormData for upload.
		const formData = new FormData();
		formData.append( 'file', file );
		formData.append(
			'action',
			isReplaceMedia ? 'onemedia_replace_media' : 'onemedia_sync_media_upload',
		);

		// Add current media ID for replacement.
		if ( isReplaceMedia && attachmentId ) {
			formData.append( 'current_media_id', attachmentId );
		}

		// Add WordPress nonce for security.
		if ( UPLOAD_NONCE ) {
			formData.append( '_ajax_nonce', UPLOAD_NONCE );
		}

		try {
			// Upload to WordPress AJAX URL.
			const data = await uploadMedia( formData, isSyncMediaUpload, setNotice );
			if ( data && data?.success ) {
				if ( onAddMediaSuccess ) {
					onAddMediaSuccess();
				}
				if ( isSyncMediaUpload ) {
					setNotice( {
						type: 'success',
						message: __(
							'Sync media uploaded successfully!',
							'onemedia',
						),
					} );
				} else if ( isReplaceMedia ) {
					setNotice( {
						type: 'success',
						message: __(
							'Media replaced successfully!',
							'onemedia',
						),
					} );
				} else {
					setNotice( {
						type: 'success',
						message: __(
							'Media uploaded successfully!',
							'onemedia',
						),
					} );
				}
			} else {
				throw new Error( data.data.message || __( 'Upload failed', 'onemedia' ) );
			}
		} catch ( error ) {
			if ( typeof setNotice === 'function' ) {
				setNotice( {
					type: 'error',
					message:
						error.message ||
						__( 'An error occurred during upload.', 'onemedia' ),
				} );
			}
		} finally {
			setIsUploading( false );

			// Reset file input.
			if ( fileInputRef.current ) {
				fileInputRef.current.value = '';
			}
		}
	};

	return (
		<>
			{ /* Hidden file input */ }
			{ ! isSyncMediaUpload && (
				<input
					className="onemedia-hidden-file-input"
					type="file"
					accept={ ALLOWED_MIME_TYPES.join( ',' ) }
					ref={ fileInputRef }
					onChange={ handleFileSelect }
				/>
			) }

			{ /* Upload button */ }
			<Button
				variant="primary"
				onClick={ handleButtonClick }
				isBusy={ isUploading }
				disabled={ isUploading }
			>
				{ isUploading ? __( 'Adding…', 'onemedia' ) : buttonText }
			</Button>
		</>
	);
};

export default BrowserUploaderButton;
