/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import {
	TextareaControl,
	Button,
	Card,
	Snackbar,
	Spinner,
	CardHeader,
	CardBody,
	TextControl,
	Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { fetchBrandSiteApiKey, regenerateBrandSiteApiKey, fetchGoverningSite, removeGoverningSite, fetchMultisiteType as fetchMultisiteTypeApi } from '../api';
import { getNoticeClass } from '../../js/utils';

const BrandSiteSettings = () => {
	const [ apiKey, setApiKey ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ notice, setNotice ] = useState( { type: 'success', message: '' } );
	const [ governingSite, setGoverningSite ] = useState( '' );
	const [ showDisconectionModal, setShowDisconectionModal ] = useState( false );
	const [ isSubdirectoryMultisite, setIsSubdirectoryMultisite ] = useState( false );

	const fetchApiKey = useCallback( async () => {
		setIsLoading( true );
		const key = await fetchBrandSiteApiKey();
		if ( key ) {
			setApiKey( key );
		} else {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to fetch api key. Please try again later.',
					'onemedia',
				),
			} );
		}
		setIsLoading( false );
	}, [] );

	// Regenerate API key using AJAX.
	const regenerateApiKey = useCallback( async () => {
		const key = await regenerateBrandSiteApiKey();
		if ( key ) {
			setApiKey( key );
			setNotice( {
				type: 'success',
				message: __( 'API key regenerated successfully.', 'onemedia' ),
			} );
		} else {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to regenerate API key. Please try after reloading the page.',
					'onemedia',
				),
			} );
		}
	}, [] );

	const fetchCurrentGoverningSite = useCallback( async () => {
		setIsLoading( true );
		const url = await fetchGoverningSite();
		if ( url ) {
			setGoverningSite( url || '' );
			setIsLoading( false );
		}
	}, [] );

	const deleteGoverningSiteConnection = useCallback( async () => {
		const success = await removeGoverningSite();
		if ( success ) {
			setGoverningSite( '' );
			setNotice( {
				type: 'success',
				message: __( 'Governing site disconnected successfully.', 'onemedia' ),
			} );
		} else {
			setNotice( {
				type: 'error',
				message: __( 'Failed to disconnect governing site. Please try again later.', 'onemedia' ),
			} );
		}
		setShowDisconectionModal( false );
	}, [] );

	const handleDisconnectGoverningSite = useCallback( async () => {
		setShowDisconectionModal( true );
	}, [] );

	const fetchMultisiteType = useCallback( async () => {
		const type = await fetchMultisiteTypeApi();
		if ( type && type.length > 0 ) {
			setIsSubdirectoryMultisite( 'subdirectory' === type );
		} else {
			setNotice( {
				type: 'error',
				message: __( 'Failed to fetch multisite type. Please try again later.', 'onemedia' ),
			} );
		}
	}, [] );

	useEffect( () => {
		fetchApiKey();
		fetchCurrentGoverningSite();
		fetchMultisiteType();
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	if ( isLoading ) {
		return <Spinner />;
	}

	return (
		<>
			<>
				{ notice?.message?.length > 0 &&
					<Snackbar
						status={ notice?.type ?? 'success' }
						isDismissible={ true }
						onRemove={ () => setNotice( null ) }
						className={ getNoticeClass( notice?.type ) }
					>
						{ notice?.message }
					</Snackbar>
				}
			</>
			<Card className="onemedia-brand-site-settings">
				<CardHeader>
					<h2>{ __( 'API Key', 'onemedia' ) }</h2>
					<div>
						{ /* Copy to clipboard button */ }
						<Button
							variant="primary"
							onClick={ () => {
								navigator?.clipboard?.writeText( apiKey )
									.then( () => {
										setNotice( {
											type: 'success',
											message: __( 'API key copied to clipboard.', 'onemedia' ),
										} );
									} )
									.catch( ( error ) => {
										setNotice( {
											type: 'error',
											message: __( 'Failed to copy api key. Please try again.', 'onemedia' ) + ' ' + error,
										} );
									} );
							} }
						>
							{ __( 'Copy API Key', 'onemedia' ) }
						</Button>
						{ /* Regenerate key button */ }
						<Button
							className="onemedia-regenerate-api-key"
							variant="secondary"
							onClick={ regenerateApiKey }
						>
							{ __( 'Regenerate API Key', 'onemedia' ) }
						</Button>
					</div>
				</CardHeader>
				<CardBody>
					<div>
						<TextareaControl
							value={ apiKey }
							disabled={ true }
							help={ __( 'This key is used for secure communication with the Governing site.', 'onemedia' ) }
						/>
					</div>
				</CardBody>
			</Card>

			<Card className="onemedia-governing-site-connection">
				<CardHeader>
					<div className="onemedia-governing-site-connection-header">
						<h2>{ __( 'Governing Site Connection', 'onemedia' ) }</h2>
						{ isSubdirectoryMultisite && (
							<p className="onemedia-subdomain-note-text">
								{ __( 'Note: For subdirectory multisite setups, the Brand site (subsite) can only be connected to a Governing site within the same multisite network.', 'onemedia' ) }
							</p>
						) }
					</div>
					<Button
						variant="secondary"
						isDestructive
						onClick={ handleDisconnectGoverningSite }
						disabled={ governingSite.trim().length === 0 || isSubdirectoryMultisite || isLoading }
					>
						{ __( 'Disconnect Governing Site', 'onemedia' ) }
					</Button>
				</CardHeader>
				<CardBody>
					<TextControl
						label={ __( 'Governing Site URL', 'onemedia' ) }
						value={ governingSite }
						disabled={ true }
						help={ __( 'This is the URL of the Governing site this Brand site is connected to.', 'onemedia' ) }
						__next40pxDefaultSize={ true }
						__nextHasNoMarginBottom={ true }
					/>
				</CardBody>
			</Card>

			{ showDisconectionModal && (
				<Modal
					title={ __( 'Disconnect Governing Site', 'onemedia' ) }
					onRequestClose={ () => setShowDisconectionModal( false ) }
					shouldCloseOnClickOutside={ true }
				>
					<p>{ __( 'Are you sure you want to disconnect from the governing site? This action cannot be undone.', 'onemedia' ) }</p>
					<div className="onemedia-modal-actions">
						<Button
							variant="secondary"
							onClick={ () => setShowDisconectionModal( false ) }
						>
							{ __( 'Cancel', 'onemedia' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ deleteGoverningSiteConnection }
						>
							{ __( 'Disconnect', 'onemedia' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};

export default BrandSiteSettings;
