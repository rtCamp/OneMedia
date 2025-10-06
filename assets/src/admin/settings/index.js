/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { Spinner, Snackbar } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SiteModal from '../../components/governing-settings/SiteModal';
import SiteTable from '../../components/governing-settings/SiteTable';
import BrandSiteSettings from '../../components/brand-settings/BrandSiteSettings';
import {
	fetchSiteType as fetchSiteTypeApi,
	fetchBrandSites as fetchBrandSitesApi,
	saveBrandSites,
} from '../../components/api';
import { removeTrailingSlash, getNoticeClass } from '../../js/utils';
import { INITIAL_FORM_STATE, ONEMEDIA_PLUGIN_GOVERNING_SITE, ONEMEDIA_PLUGIN_BRAND_SITE } from '../../components/constants';

const OneMediaSettingsPage = () => {
	const [ siteType, setSiteType ] = useState( '' );
	const [ showModal, setShowModal ] = useState( false );
	const [ editingIndex, setEditingIndex ] = useState( null );
	const [ sites, setSites ] = useState( [] );
	const [ formData, setFormData ] = useState( INITIAL_FORM_STATE );
	const [ notice, setNotice ] = useState( { type: 'success', message: '' } );
	const [ loading, setLoading ] = useState( true );

	// Fetch site type on mount.
	useEffect( () => {
		const fetchSiteType = async () => {
			setLoading( true );
			const currentType = await fetchSiteTypeApi( setNotice );
			setSiteType( currentType );
			setLoading( false );
		};
		fetchSiteType();
	}, [] );

	// Fetch brand sites if governing.
	useEffect( () => {
		if ( ONEMEDIA_PLUGIN_GOVERNING_SITE === siteType ) {
			const fetchBrandSites = async () => {
				setLoading( true );
				const sitesData = await fetchBrandSitesApi( setNotice );
				setSites( sitesData || [] );
				setLoading( false );
			};
			fetchBrandSites();
		}
	}, [ siteType ] );

	const handleFormSubmit = async () => {
		const updated =
			null !== editingIndex
				? sites.map( ( item, i ) => ( i === editingIndex ? formData : item ) )
				: [ ...sites, formData ];

		updated.forEach( ( item ) => {
			item.siteUrl = removeTrailingSlash( item.siteUrl );
		} );

		const result = await saveBrandSites( updated );

		if ( 0 === sites.length ) {
			window?.location?.reload();
		}

		if ( result && result?.success ) {
			setSites( updated );
			setNotice( {
				type: 'success',
				message: __( 'Brand Site saved successfully.', 'onemedia' ),
			} );
			setFormData( INITIAL_FORM_STATE );
			setShowModal( false );
			setEditingIndex( null );
		}
		return result;
	};

	const handleDelete = async ( index ) => {
		const updated = sites.filter( ( _, i ) => i !== index );
		const result = await saveBrandSites( updated, setNotice );
		if ( result?.success ) {
			setSites( updated );
			setNotice( {
				type: 'success',
				message: __( 'Brand Site deleted successfully.', 'onemedia' ),
			} );
		}
	};

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

			{ loading ? (
				<Spinner />
			) : (
				<>
					{ siteType && ONEMEDIA_PLUGIN_BRAND_SITE === siteType && <BrandSiteSettings /> }

					{ siteType && ONEMEDIA_PLUGIN_GOVERNING_SITE === siteType && (
						<SiteTable
							sites={ sites }
							onEdit={ setEditingIndex }
							onDelete={ handleDelete }
							setFormData={ setFormData }
							setShowModal={ setShowModal }
						/>
					) }

					{ siteType && showModal && (
						<SiteModal
							formData={ formData }
							setFormData={ setFormData }
							onSubmit={ handleFormSubmit }
							onClose={ () => {
								setShowModal( false );
								setEditingIndex( null );
								setFormData( INITIAL_FORM_STATE );
							} }
							editing={ null !== editingIndex }
							originalData={ sites[ editingIndex ] }
						/>
					) }
				</>
			) }
		</>
	);
};

const rootElement = document.getElementById( 'onemedia-settings' );
if ( rootElement ) {
	const root = createRoot( rootElement );
	root.render( <OneMediaSettingsPage /> );
}
