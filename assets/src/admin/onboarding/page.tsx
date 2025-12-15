import { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	Notice,
	Button,
	SelectControl,
} from '@wordpress/components';

const BRAND_SITE = 'brand-site';
const GOVERNING_SITE = 'governing-site';

export type SiteType = typeof BRAND_SITE | typeof GOVERNING_SITE;

interface NoticeState {
	type: 'success' | 'error' | 'warning' | 'info';
	message: string;
}

const SiteTypeSelector = ( { value, setSiteType }: {
	value: SiteType | '';
	setSiteType: ( v: SiteType | '' ) => void;
} ) => (
	<SelectControl
		label={ __( 'Site Type', 'onemedia' ) }
		value={ value }
		help={ __(
			"Choose your site's primary purpose. This setting cannot be changed later and affects available features and configurations.",
			'onemedia',
		) }
		onChange={ ( v ) => {
			setSiteType( v );
		} }
		options={ [
			{ label: __( 'Select…', 'onemedia' ), value: '' },
			{ label: __( 'Brand Site', 'onemedia' ), value: BRAND_SITE },
			{ label: __( 'Governing site', 'onemedia' ), value: GOVERNING_SITE },
		] }
	/>
);

const OnboardingScreen = () => {
	// WordPress provides snake_case keys here. Using them intentionally.
	// eslint-disable-next-line camelcase
	const { restNonce, settingsLink, site_type } = window.OneMediaSettings;

	const [ siteType, setSiteType ] = useState<SiteType | ''>( site_type || '' );
	const [ notice, setNotice ] = useState<NoticeState | null>( null );
	const [ isSaving, setIsSaving ] = useState<boolean>( false );

	useEffect( () => {
		apiFetch.use( apiFetch.createNonceMiddleware( restNonce ) );
		apiFetch<{ onemedia_site_type?: SiteType }>( { path: '/wp/v2/settings' } )
			.then( ( settings ) => {
				if ( settings?.onemedia_site_type ) {
					setSiteType( settings.onemedia_site_type );
				}
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __( 'Error fetching site type.', 'onemedia' ),
				} );
			} );
	} );

	const handleSiteTypeChange = async ( value: SiteType | '' ) => {
		// Optimistically set site type.
		setSiteType( value );
		setIsSaving( true );

		try {
			await apiFetch<{ onemedia_site_type?: SiteType }>( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: { onemedia_site_type: value },
			} ).then( ( settings ) => {
				if ( ! settings?.onemedia_site_type ) {
					throw new Error( __( 'No site type in response', 'onemedia' ) );
				}

				setSiteType( settings.onemedia_site_type );

				// Redirect user to setup page.
				if ( settingsLink ) {
					window.location.href = settingsLink;
				}
			} );
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Error setting site type.', 'onemedia' ),
			} );
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<Card>
			{ !! notice?.message && (
				<Notice
					status={ notice?.type ?? 'success' }
					isDismissible={ true }
					onRemove={ () => setNotice( null ) }
				>
					{ notice?.message }
				</Notice>
			) }

			<CardHeader>
				<h2>{ __( 'OneMedia', 'onemedia' ) }</h2>
			</CardHeader>

			<CardBody className="onemedia-onboarding-page">
				<SiteTypeSelector
					value={ siteType }
					setSiteType={ setSiteType }
				/>
				<Button
					variant="primary"
					onClick={ () => handleSiteTypeChange( siteType ) }
					disabled={ isSaving || ! siteType }
					style={ { marginTop: '1.5rem' } }
					className={ isSaving ? 'is-busy' : '' }
				>
					{ __( 'Select Current Site Type', 'onemedia' ) }
				</Button>
			</CardBody>
		</Card>
	);
};

export default OnboardingScreen;
