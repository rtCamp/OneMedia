/**
 * WordPress dependencies
 */
import { useState, useEffect, createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	Notice,
	Button,
	SelectControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import {
	saveSiteType,
	fetchSiteType as fetchSiteTypeApi,
} from '../../components/api';
import { SETUP_URL, ONEMEDIA_PLUGIN_GOVERNING_SITE, ONEMEDIA_PLUGIN_BRAND_SITE } from '../../components/constants';

const SiteTypeSelector = ( { value, setSiteType } ) => (
	<SelectControl
		label={ __( 'Site Type', 'onemedia' ) }
		value={ value }
		help={ __(
			'Choose your site\'s primary purpose. This setting cannot be changed later and affects available features and configurations.',
			'onemedia',
		) }
		onChange={ ( v ) => {
			setSiteType( v );
		} }
		options={ [
			{ label: __( 'Select…', 'onemedia' ), value: '' },
			{ label: __( 'Brand Site', 'onemedia' ), value: ONEMEDIA_PLUGIN_BRAND_SITE },
			{ label: __( 'Governing Site', 'onemedia' ), value: ONEMEDIA_PLUGIN_GOVERNING_SITE },
		] }
	/>
);

const OneMediaPluginSettingsPage = () => {
	const [ siteType, setSiteType ] = useState( '' );
	const [ notice, setNotice ] = useState( { type: 'success', message: '' } );
	const [ isSaving, setIsSaving ] = useState( false );

	// Fetch site type on mount.
	useEffect( () => {
		const fetchSiteType = async () => {
			const currentType = await fetchSiteTypeApi( setNotice );
			setSiteType( currentType );
		};
		fetchSiteType();
	}, [] );

	const handleSiteTypeChange = async ( value ) => {
		if ( ! value ) {
			return;
		}
		setIsSaving( true );

		const success = await saveSiteType( value );
		if ( success ) {
			setSiteType( value );
		} else {
			setNotice( {
				type: 'error',
				message: __( 'Error saving site type.', 'onemedia' ),
			} );
		}
		setIsSaving( false );
		window.location.href = SETUP_URL;
	};

	return (
		<>
			<Card>
				<CardHeader>
					<h2>{ __( 'OneMedia', 'onemedia' ) }</h2>
				</CardHeader>
				<CardBody>
					<>
						{ notice?.message?.length > 0 &&
							<Notice
								status={ notice?.type ?? 'success' }
								isDismissible={ true }
								onRemove={ () => setNotice( null ) }
							>
								{ notice?.message }
							</Notice>
						}
					</>
					<SiteTypeSelector
						value={ siteType }
						setSiteType={ setSiteType }
					/>
					<Button
						variant="primary"
						onClick={ () => handleSiteTypeChange( siteType ) }
						disabled={ isSaving || ! siteType }
						className={ 'onemedia-site-type-button' + ( isSaving ? ' is-busy' : '' ) }
					>
						{ __( 'Select Current Site Type', 'onemedia' ) }
					</Button>
				</CardBody>
			</Card>
		</>
	);
};

const target = document.getElementById( 'onemedia-site-selection-modal' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OneMediaPluginSettingsPage /> );
}
