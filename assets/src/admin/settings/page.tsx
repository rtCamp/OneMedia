/**
 * WordPress dependencies
 */
import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Snackbar } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import SiteTable from '../../components/SiteTable';
import SiteModal from '../../components/SiteModal';
import SiteSettings from '../../components/SiteSettings';
import type { SiteType } from '../onboarding/page';

export interface NoticeType {
	type: 'success' | 'error' | 'warning' | 'info';
	message: string;
}

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

const NONCE = window.OneMediaSettings.restNonce;
const SITE_TYPE = window.OneMediaSettings.siteType as SiteType || '';
const SHARED_SITES_ENDPOINT = '/onemedia/v1/shared-sites';

/**
 * Create NONCE middleware for apiFetch
 */
apiFetch.use( apiFetch.createNonceMiddleware( NONCE ) );

const SettingsPage = () => {
	const [ showModal, setShowModal ] = useState( false );
	const [ editingIndex, setEditingIndex ] = useState< EditingIndex >( null );
	const [ sites, setSites ] = useState< BrandSite[] >( [] );
	const [ formData, setFormData ] = useState< BrandSite >( defaultBrandSite );
	const [ notice, setNotice ] = useState< NoticeType | null >( null );

	useEffect( () => {
		apiFetch<{ shared_sites?: BrandSite[] }>( {
			path: SHARED_SITES_ENDPOINT,
		} )
			.then( ( data ) => {
				if ( data?.shared_sites ) {
					setSites( data?.shared_sites );
				}
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __( 'Error fetching settings data.', 'onemedia' ),
				} );
			} );
	}, [] ); // Empty dependency array to run only once on mount

	useEffect( () => {
		if ( SITE_TYPE === 'governing-site' && sites.length > 0 ) {
			document.body.classList.remove( 'onemedia-missing-brand-sites' );
		}
	}, [ sites ] );

	const handleFormSubmit = async () : Promise< boolean > => {
		const updated : BrandSite[] = editingIndex !== null
			? sites.map( ( item, i ) => ( i === editingIndex ? formData : item ) )
			: [ ...sites, formData ];

		return apiFetch<{ shared_sites?: BrandSite[] }>( {
			path: SHARED_SITES_ENDPOINT,
			method: 'POST',
			data: { shared_sites: updated },
		} ).then( ( data ) => {
			if ( ! data?.shared_sites ) {
				throw new Error( 'No shared sites in response' );
			}

			if ( data.shared_sites.length === 1 && sites.length === 0 ) {
				// Reloading causes the menus etc to reflect the missing sites.
				window.location.reload();
			}

			setSites( data.shared_sites );

			setNotice( {
				type: 'success',
				message: __( 'Brand Site saved successfully.', 'onemedia' ),
			} );
			return true;
		} ).catch( () => {
			setNotice( {
				type: 'error',
				message: __( 'Failed to update shared sites', 'onemedia' ),
			} );
			return false;
		} ).finally( () => {
			setFormData( defaultBrandSite );
			setShowModal( false );
			setEditingIndex( null );
		} );
	};

	const handleDelete = async ( index : number|null ) : Promise<void> => {
		const updated : BrandSite[] = sites.filter( ( _, i ) => i !== index );

		apiFetch<{ shared_sites?: BrandSite[] }>( {
			path: SHARED_SITES_ENDPOINT,
			method: 'POST',
			data: { shared_sites: updated },
		} ).then( ( data ) => {
			if ( ! data?.shared_sites ) {
				throw new Error( 'No shared sites in response' );
			}
			setSites( data.shared_sites );

			if ( data.shared_sites.length === 0 ) {
				// Reloading causes the menus etc to reflect the missing sites.
				window.location.reload();
			} else {
				document.body.classList.remove( 'onemedia-missing-brand-sites' );
			}
		} ).catch( () => {
			throw new Error( 'Failed to update shared sites' );
		} );
	};

	return (
		<>
			{ !! notice && notice?.message?.length > 0 &&
				<Snackbar
					explicitDismiss={ false }
					onRemove={ () => setNotice( null ) }
					className={ notice?.type === 'error' ? 'onemedia-error-notice' : 'onemedia-success-notice' }
				>
					{ notice?.message }
				</Snackbar>
			}

			{ SITE_TYPE === 'brand-site' && (
				<SiteSettings />
			) }

			{ SITE_TYPE === 'governing-site' && (
				<>
					<SiteTable sites={ sites } onEdit={ setEditingIndex } onDelete={ handleDelete } setFormData={ setFormData } setShowModal={ setShowModal } />
				</>
			) }

			{ showModal && (
				<SiteModal
					formData={ formData }
					setFormData={ setFormData }
					onSubmit={ handleFormSubmit }
					onClose={ () => {
						setShowModal( false );
						setEditingIndex( null );
						setFormData( defaultBrandSite );
					} }
					editing={ editingIndex !== null }
					sites={ sites }
					originalData={ editingIndex !== null ? sites[ editingIndex ] : undefined }
				/>
			) }
		</>
	);
};

export default SettingsPage;
