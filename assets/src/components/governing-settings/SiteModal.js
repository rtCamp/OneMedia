/**
 * WordPress dependencies
 */
import { useState, useMemo } from '@wordpress/element';
import {
	Modal,
	TextControl,
	TextareaControl,
	Button,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { isValidUrl } from '../../js/utils';
import { checkBrandSiteHealth } from '../api';

const SiteModal = ( { formData, setFormData, onSubmit, onClose, editing, originalData } ) => {
	const [ errors, setErrors ] = useState( {
		name: '',
		url: '',
		apiKey: '',
		message: '',
	} );
	const [ showNotice, setShowNotice ] = useState( false );
	const [ isProcessing, setIsProcessing ] = useState( false ); // New state for processing.

	// Check if form data has changed from original data (only for editing mode).
	const hasChanges = useMemo( () => {
		if ( ! editing ) {
			return true;
		} // Always allow submission for new sites.

		return (
			formData.name !== originalData.name ||
			formData.url !== originalData.url ||
			formData.apiKey !== originalData.apiKey
		);
	}, [ editing, formData, originalData ] );

	const handleSubmit = async () => {
		// Validate inputs.
		let siteUrlError = '';
		if ( ! formData.url.trim() ) {
			siteUrlError = __( 'Site URL is required.', 'onemedia' );
		} else if ( ! isValidUrl( formData.url ) ) {
			siteUrlError = __(
				'Enter a valid URL (must start with http or https).',
				'onemedia',
			);
		}

		const newErrors = {
			name: ! formData.name.trim() ? __( 'Site Name is required.', 'onemedia' ) : '',
			url: siteUrlError,
			apiKey: ! formData.apiKey.trim() ? __( 'API Key is required.', 'onemedia' ) : '',
			message: '',
		};

		// Make sure site name is under 20 characters.
		if ( formData.name.length > 20 ) {
			newErrors.name = __( 'Site Name must be under 20 characters.', 'onemedia' );
		}

		setErrors( newErrors );
		const hasErrors = Object.values( newErrors ).some( ( err ) => err );

		if ( hasErrors ) {
			setShowNotice( true );
			return;
		}

		// Start processing
		setIsProcessing( true );
		setShowNotice( false );

		const healthCheckData = await checkBrandSiteHealth( formData.url, formData.apiKey );

		if ( ! healthCheckData?.success ) {
			setErrors( {
				...newErrors,
				message: healthCheckData?.message,
			} );
			setShowNotice( true );
			setIsProcessing( false );
			return;
		}

		setShowNotice( false );

		const saveBrandSiteData = await onSubmit();

		if ( ! saveBrandSiteData?.success ) {
			setErrors( {
				...newErrors,
				message: saveBrandSiteData?.message,
			} );
			setShowNotice( true );
			setIsProcessing( false );
			return;
		}

		setIsProcessing( false );
	};

	// Button should be disabled if:
	// 1. Currently processing, OR
	// 2. Required fields are empty, OR
	// 3. In editing mode and no changes have been made
	const isButtonDisabled = isProcessing ||
		! formData.name ||
		! formData.url ||
		! formData.apiKey ||
		( editing && ! hasChanges );

	return (
		<Modal
			title={
				editing
					? __( 'Edit Brand Site', 'onemedia' )
					: __( 'Add Brand Site', 'onemedia' )
			}
			onRequestClose={ onClose }
			size="medium"
		>
			{ showNotice && (
				<Notice
					status="error"
					isDismissible={ true }
					onRemove={ () => setShowNotice( false ) }
				>
					{ errors.message || errors.name || errors.url || errors.apiKey }
				</Notice>
			) }

			<TextControl
				label={ __( 'Site Name*', 'onemedia' ) }
				value={ formData.name }
				onChange={ ( value ) =>
					setFormData( { ...formData, name: value } )
				}
				error={ errors.name }
				help={ __(
					'This is the name of the site that will be registered.',
					'onemedia',
				) }
				__next40pxDefaultSize={ true }
				__nextHasNoMarginBottom={ true }
			/>
			<TextControl
				label={ __( 'Site URL*', 'onemedia' ) }
				value={ formData.url }
				onChange={ ( value ) =>
					setFormData( { ...formData, url: value } )
				}
				error={ errors.url }
				help={ __(
					'It must start with http or https and end with /, like: https://onemedia.com/',
					'onemedia',
				) }
				__next40pxDefaultSize={ true }
				__nextHasNoMarginBottom={ true }
			/>
			<TextareaControl
				label={ __( 'API Key*', 'onemedia' ) }
				value={ formData.apiKey }
				onChange={ ( value ) =>
					setFormData( { ...formData, apiKey: value } )
				}
				error={ errors.apiKey }
				help={ __(
					'This is the API key that will be used to authenticate the site for onemedia.',
					'onemedia',
				) }
				__nextHasNoMarginBottom={ true }
			/>

			<Button
				variant="primary"
				onClick={ handleSubmit }
				className={ 'onemedia-add-site-button' + ( isProcessing ? ' is-busy' : '' ) }
				disabled={ isButtonDisabled }
			>
				{ editing
					? __( 'Update Site', 'onemedia' )
					: __( 'Add Site', 'onemedia' ) }
			</Button>
		</Modal>
	);
};

export default SiteModal;
