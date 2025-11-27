/**
 * WordPress dependencies
 */
import {
	useState,
	useEffect,
	createRoot,
	useCallback,
	useMemo,
} from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	Snackbar,
	Spinner,
	Tooltip,
	Icon,
	TabPanel,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import syncIcon from './syncIcon';
import versionIcon from './versionIcon';
import BrowserUploaderButton from './browser-uploader';
import { ONEMEDIA_PLUGIN_TAXONOMY_TERM, ONEMEDIA_MEDIA_SHARING, MEDIA_PER_PAGE, UPLOAD_NONCE } from '../../components/constants';
import ShareMediaModal from '../../components/governing-settings/ShareMediaModal';
import VersionModal from '../../components/governing-settings/VersionModal';
import { fetchSyncedSites as fetchSyncedSitesApi, fetchMediaItems as fetchMediaItemsApi, fetchBrandSites as fetchBrandSitesApi, shareMedia as shareMediaApi, uploadMedia } from '../../components/api';
import { getNoticeClass, trimTitle, debounce, getFrameProperty } from '../../js/utils';
import fallbackImage from '../../images/fallback-image.svg';

const MediaSharingApp = ( {
	imageType = '',
	cardTitle = '',
	cardDescription = '',
} ) => {
	// Media state.
	const [ mediaItems, setMediaItems ] = useState( [] );
	const [ selectedMedia, setSelectedMedia ] = useState( {} );
	const [ currentPage, setCurrentPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ loading, setLoading ] = useState( false );
	const [ initLoading, setInitLoading ] = useState( false );
	const [ syncedSites, setSyncedSites ] = useState( [] );
	const [ selectedVersionId, setSelectedVersionId ] = useState( 0 );
	const [ currentRevision, setCurrentRevision ] = useState( [] );

	// Brand sites state.
	const [ brandSites, setBrandSites ] = useState( [] );
	const [ selectedSites, setSelectedSites ] = useState( {} );

	// UI state.
	const [ searchTerm, setSearchTerm ] = useState( '' );
	const [ debouncedSearchTerm, setDebouncedSearchTerm ] = useState( '' );
	const [ notice, setNotice ] = useState( { type: 'success', message: '' } );
	const [ isShareMediaModalOpen, setIsShareMediaModalOpen ] = useState( false );
	const [ isVersionModalOpen, setIsVersionModalOpen ] = useState( false );
	const [ syncOption, setSyncOption ] = useState( 'sync' );

	const perPage = MEDIA_PER_PAGE; // Show more items in grid.

	const localizationError = () => {
		throw new Error(
			__(
				'oneMediaMediaSharing object not found. Make sure it\'s properly localized.',
				'onemedia',
			),
		);
	};

	const handleDebouncedSearch = useMemo(
		() =>
			debounce( ( value ) => {
				setDebouncedSearchTerm( value );
				setSelectedMedia( {} );
				setCurrentPage( 1 );
			}, 1000 ),
		[],
	);

	const fetchSyncedSites = useCallback( async () => {
		const sites = await fetchSyncedSitesApi( setNotice );

		setSyncedSites( sites );
	}, [] );

	const fetchMediaItems = useCallback(
		async ( page, search = '' ) => {
			setLoading( true );
			setInitLoading( true );
			setNotice( null );

			if ( ! ONEMEDIA_MEDIA_SHARING ) {
				localizationError();
			}

			const data = await fetchMediaItemsApi( {
				search,
				page,
				perPage,
				imageType,
				setNotice,
			} );

			setTotalPages( data?.total_pages || 1 );
			setMediaItems( data?.media_files || [] );

			setLoading( false );
			setInitLoading( false );
		},
		[ perPage, imageType ],
	);

	const fetchBrandSites = useCallback( async () => {
		if ( ! ONEMEDIA_MEDIA_SHARING ) {
			localizationError();
		}

		const sitesData = await fetchBrandSitesApi( setNotice );

		setBrandSites( sitesData || [] );

		// Initialize selected sites state.
		const initialSelectedSites = {};
		sitesData.forEach( ( site ) => {
			initialSelectedSites[ site.siteUrl ] = false;
		} );
		setSelectedSites( initialSelectedSites );
	}, [] );

	// Initialize data fetching.
	useEffect( () => {
		fetchBrandSites();
		fetchSyncedSites();
	}, [ fetchBrandSites, fetchSyncedSites ] );

	// Fetch media items when currentPage changes.
	useEffect( () => {
		fetchMediaItems( currentPage, debouncedSearchTerm );
	}, [ currentPage, debouncedSearchTerm, fetchMediaItems ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Refresh media items when media is replaced.
	useEffect( () => {
		const handleMediaReplaced = ( event ) => {
			const { attachmentId } = event.detail;
			if ( attachmentId ) {
				// Refresh media items to reflect the replaced media, respecting imageType.
				fetchMediaItems( currentPage );
			}
		};

		document.addEventListener( 'mediaReplaced', handleMediaReplaced );

		// Cleanup event listener on component unmount.
		return () => {
			document.removeEventListener( 'mediaReplaced', handleMediaReplaced );
		};
	}, [ currentPage, imageType, fetchMediaItems ] );

	// Refresh media items when imageType changes. Set sync option based on imageType.
	useEffect( () => {
		if ( ONEMEDIA_PLUGIN_TAXONOMY_TERM === imageType ) {
			setSyncOption( 'sync' );
		} else {
			setSyncOption( 'no_sync' );
		}
		setCurrentPage( 1 );
		setSelectedMedia( {} );
		setSearchTerm( '' );
		setDebouncedSearchTerm( '' );
	}, [ imageType ] );

	const handleMediaSelect = ( mediaId ) => {
		setSelectedMedia( ( prev ) => ( {
			...prev,
			[ mediaId ]: ! prev[ mediaId ],
		} ) );
	};

	const handleSiteSelect = ( siteUrlOrObj, isBulk = false ) => {
		if ( isBulk ) {
			setSelectedSites( siteUrlOrObj );
		} else {
			setSelectedSites( ( prev ) => ( {
				...prev,
				[ siteUrlOrObj ]: ! prev[ siteUrlOrObj ],
			} ) );
		}
	};

	const handleEditMedia = ( mediaId, currentImageType ) => {
		if ( getFrameProperty( 'wp.media' ) ) {
			// Create edit media frame.
			const editFrame = window.wp.media( {
				title: __( 'Edit Media', 'onemedia' ),
				button: {
					text: __( 'Update', 'onemedia' ),
				},
				multiple: false,
				library: {
					type: 'image',
					onemedia_sync_media_filter:
						ONEMEDIA_PLUGIN_TAXONOMY_TERM === currentImageType ? true : false,
				},
			} );

			editFrame.on( 'open', function() {
				// Reset the selection state.
				editFrame.state().get( 'selection' ).reset();
				// Get the attachment model.
				const attachment = window.wp.media.attachment( mediaId );
				// Fetch the attachment data and add to selection.
				attachment
					.fetch()
					.then( () => {
						editFrame.state().get( 'selection' ).add( attachment );
						// Force the frame to update its view.
						editFrame.content.mode( 'browse' );
					} )
					.catch( () => {
						// Fallback: add without fetch.
						editFrame.state().get( 'selection' ).add( attachment );
					} );
			} );

			editFrame.on( 'close', () => {
				// Refresh media items after edit.
				fetchMediaItems( currentPage, debouncedSearchTerm );
				setSelectedMedia( {} );
				fetchSyncedSites();
			} );

			// Open the frame.
			editFrame.open();
		}
	};

	const openShareMediaModal = () => {
		// If there are no brand sites, show a warning.
		if ( 0 === brandSites.length ) {
			setNotice( {
				type: 'warning',
				message: __(
					'Please add at least one brand site from OneMedia Settings page',
					'onemedia',
				),
			} );
			return;
		}

		// Check if any media is selected.
		const hasSelectedMedia = Object.values( selectedMedia ).some(
			( selected ) => selected,
		);

		if ( ! hasSelectedMedia ) {
			setNotice( {
				type: 'warning',
				message: __(
					'Please select at least one media item.',
					'onemedia',
				),
			} );
			return;
		}

		setIsShareMediaModalOpen( true );
	};

	const checkMediaRevisionExists = ( mediaId ) => {
		const media = mediaItems.filter( ( item ) => item.id === mediaId )?.[ 0 ] || null;
		return media && media.revision && media.revision.length > 1;
	};

	const openVersionModal = async ( mediaId ) => {
		const version = Object.values( mediaItems ).filter( ( item ) => item.id === mediaId )?.[ 0 ]?.revision || [];

		if ( ! mediaId || version.length === 0 ) {
			return;
		}

		setSelectedVersionId( mediaId );
		setCurrentRevision( version );
		setIsVersionModalOpen( true );
	};

	const handleShareMedia = async () => {
		if ( ! ONEMEDIA_MEDIA_SHARING ) {
			localizationError();
		}

		// Get selected brand sites.
		const selectedBrandSites = Object.entries( selectedSites )
			.filter( ( [ , isSelected ] ) => isSelected )
			.map( ( [ siteUrl ] ) => siteUrl );

		if ( 0 === selectedBrandSites.length ) {
			setNotice( {
				type: 'warning',
				message: __(
					'Please select at least one brand site.',
					'onemedia',
				),
			} );
			return;
		}

		// Get selected media IDs.
		const selectedMediaIds = Object.entries( selectedMedia )
			.filter( ( [ , isSelected ] ) => isSelected )
			.map( ( [ mediaId ] ) => parseInt( mediaId, 10 ) );

		// Get full media details for selected media.
		const selectedMediaDetails = mediaItems.filter( ( media ) =>
			selectedMediaIds.includes( media.id ),
		);

		const payload = {
			sync_option: syncOption,
			brand_sites: selectedBrandSites,
			media_details: selectedMediaDetails,
		};

		setLoading( true );
		const data = await shareMediaApi( payload, setNotice );
		if ( data && 200 === data?.status ) {
			// If imageType is 'onemedia' then message will be media synced successfully else media shared successfully.
			if ( ONEMEDIA_PLUGIN_TAXONOMY_TERM === imageType ) {
				setNotice( {
					type: 'success',
					message: __( 'Media synced successfully!', 'onemedia' ),
				} );
			} else {
				setNotice( {
					type: 'success',
					message: __( 'Media shared successfully!', 'onemedia' ),
				} );
			}
			fetchSyncedSites();
		} else {
			const failedSites = data?.data?.failed_sites || [];
			if ( failedSites?.length > 0 ) {
				setNotice( {
					type: 'warning',
					message: (
						<div>
							<span>{ __( 'Failed to sync media files to some brand sites:', 'onemedia' ) }</span>
							{ ( failedSites || [] ).map( ( site, idx ) => (
								<div key={ idx }>
									{ site?.is_mime_type_error ? (
										<span>
											{ site?.message }
										</span>
									) : (
										<span>
											{ site?.site_name }
										</span>
									) }
								</div>
							) ) }
						</div>
					),
				} );
			}
			fetchSyncedSites();
		}

		// Reset selections.
		setSelectedMedia( {} );

		// Reset modal states.
		setIsShareMediaModalOpen( false );

		// Reset site selections.
		const resetSites = {};
		Object.keys( selectedSites ).forEach( ( site ) => {
			resetSites[ site ] = false;
		} );
		setSelectedSites( resetSites );
		setLoading( false );
	};

	const handleVersionSelect = async ( version ) => {
		if ( ! version || ! version?.file ) {
			setNotice( {
				type: 'error',
				message: __( 'Invalid version selected.', 'onemedia' ),
			} );
		}

		// Upload the selected version as a new media item.
		setLoading( true );
		try {
			const formData = new FormData();
			formData.append( 'file', JSON.stringify( version.file ) );
			formData.append( 'action', 'onemedia_replace_media' );

			// Add current media ID for replacement.
			formData.append( 'current_media_id', String( selectedVersionId ) );

			// Add WordPress nonce for security.
			if ( UPLOAD_NONCE ) {
				formData.append( '_ajax_nonce', UPLOAD_NONCE );
			}

			formData.append( 'is_version_restore', true );

			const response = await uploadMedia( formData, false, setNotice );

			if ( response && response?.success ) {
				// Refresh media items to reflect the restored version.
				setIsVersionModalOpen( false );
				fetchMediaItems( currentPage, debouncedSearchTerm );
				fetchSyncedSites();

				setNotice( {
					type: 'success',
					message: __( 'Media version restored successfully!', 'onemedia' ),
				} );
			}
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'An error occurred while restoring media version.', 'onemedia' ),
			} );
		} finally {
			setLoading( false );
		}
	};

	const getSelectedCount = () => {
		return Object.values( selectedMedia ).filter( Boolean ).length;
	};

	const getSelectedSitesCount = () => {
		return Object.values( selectedSites ).filter( Boolean ).length;
	};

	// Render the media grid.
	const renderMediaGrid = () => {
		if ( 0 === mediaItems.length ) {
			return (
				<div className="onemedia-no-media">
					{ __( 'No media items found.', 'onemedia' ) }
				</div>
			);
		}

		return (
			<div className="onemedia-media-grid">
				{ mediaItems.map( ( media ) => (
					<div
						key={ media.id }
						className={ `onemedia-media-item ${ selectedMedia[ media.id ] ? 'selected' : ''
						}` }
					>
						<div
							className="onemedia-media-thumbnail"
							onClick={ () => handleMediaSelect( media.id ) }
							onKeyDown={ ( e ) => {
								const triggerKeys = [ 'Enter', 'Space' ];
								if ( triggerKeys.includes( e.code ) ) {
									e.preventDefault();
									handleMediaSelect( media.id );
								}
							} }
							role="button"
							tabIndex={ 0 }
						>
							<div className="onemedia-media-container">
								<img
									data-id={ media.id }
									src={ media.url }
									alt={ media.title }
									loading="lazy"
									onError={ ( e ) => {
										e.target.onerror = null; // Prevent infinite loop.
										e.target.src = fallbackImage;
										e.target.style.width = '60%';
										e.target.style.padding = '0px 20%';
									} }
								/>
								<div className="onemedia-media-checkbox">
									<CheckboxControl
										checked={ !! selectedMedia[ media.id ] }
										onChange={ () =>
											handleMediaSelect( media.id )
										}
										label=""
										__nextHasNoMarginBottom={ true }
									/>
								</div>
								{ ONEMEDIA_PLUGIN_TAXONOMY_TERM === imageType && (
									<>
										<div className="onemedia-media-edit-button">
											<Button
												isSmall
												variant="secondary"
												icon="edit"
												onClick={ ( e ) => {
													e.stopPropagation();
													handleEditMedia(
														media.id,
														imageType,
													);
												} }
												title={ __( 'Edit Media', 'onemedia' ) }
											>
												{ __( 'Edit', 'onemedia' ) }
											</Button>
										</div>
										{ checkMediaRevisionExists( media.id ) && (
											<Tooltip
												text={ sprintf(
													/* translators: %d: number of previous versions */
													__( '%d previous version(s) available', 'onemedia' ),
													media.revision.length - 1,
												) }
												placement="bottom"
											>
												<Button
													className="onemedia-media-version-button"
													onClick={ ( e ) => {
														e.stopPropagation();
														openVersionModal( media.id );
													} }
												>
													<Icon
														icon={ versionIcon }
														size={ 28 }
														fill="#fff"
													/>
												</Button>
											</Tooltip>
										) }
									</>
								) }
								{ syncedSites[ media.id ] && (
									<Tooltip
										text={
											<span>
												{ Object.values( syncedSites[ media.id ] ).map( ( site, idx ) => (
													<span key={ idx }>
														{ site }
														<br />
													</span>
												) ) }
											</span>
										}
										placement="bottom"
									>
										<div className="onemedia-media-synced-indicator">
											<Icon
												icon={ syncIcon }
												size={ 28 }
												fill="#fff"
											/>
										</div>
									</Tooltip>
								) }
							</div>
							<div className="onemedia-media-title">
								{ trimTitle( media.title ) }
							</div>
						</div>
					</div>
				) ) }
			</div>
		);
	};

	return (
		<>
			<>
				{ /* Error notice */ }
				{ notice?.message &&
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
			<div className="onemedia-media-sharing-app">
				<Card>
					<CardHeader>
						<div className="onemedia-card-header">
							<div className="onemedia-card-header-content">
								<h2 className="onemedia-card-title">{ cardTitle }</h2>
								<p className="onemedia-card-description">
									{ cardDescription }
								</p>
							</div>
							<div className="onemedia-card-header-actions-container">
								<TextControl
									className="onemedia-search-control"
									placeholder={ __( 'Search media…', 'onemedia' ) }
									value={ searchTerm }
									onChange={ ( value ) => {
										setSearchTerm( value );
										handleDebouncedSearch( value );
									} }
									__next40pxDefaultSize={ true }
									__nextHasNoMarginBottom={ true }
								/>
								<div className="onemedia-card-header-actions">
									{ ONEMEDIA_PLUGIN_TAXONOMY_TERM === imageType ? (
										<BrowserUploaderButton
											onAddMediaSuccess={ () => {
												// Refresh media items after upload.
												setSearchTerm( '' );
												setDebouncedSearchTerm( '' );
												fetchMediaItems( currentPage );
												fetchSyncedSites();
											} }
											isSyncMediaUpload={ true }
											addedMedia={ mediaItems }
											setNotice={ setNotice }
										/>
									) : (
										<BrowserUploaderButton
											onAddMediaSuccess={ () => {
												// Refresh media items after upload.
												setSearchTerm( '' );
												setDebouncedSearchTerm( '' );
												fetchMediaItems( currentPage );
											} }
											isSyncMediaUpload={ false }
											addedMedia={ mediaItems }
											setNotice={ setNotice }
										/>
									) }
									<div className="onemedia-selection-count">
										{ getSelectedCount() > 0 &&
											/* translators: %d: number of selected items */
											sprintf( __( '%d items selected', 'onemedia' ), getSelectedCount() )
										}
									</div>
								</div>
							</div>
						</div>
					</CardHeader>
					<CardBody>
						{ initLoading ? (
							<div className="onemedia-loading">
								<Spinner />
							</div>
						) : (
							<>
								{ renderMediaGrid() }

								{ /* Pagination Controls */ }
								<div className="onemedia-pagination">
									<Button
										isSecondary
										disabled={ 1 === currentPage }
										onClick={ () =>
											setCurrentPage( ( prev ) => prev - 1 )
										}
									>
										{ __( 'Previous', 'onemedia' ) }
									</Button>
									<span className="onemedia-page-indicator">
										{ sprintf(
											/* translators: %1$d: current page number, %2$d: total pages */
											__( '%1$d of %2$d', 'onemedia' ),
											currentPage,
											totalPages,
										) }
									</span>
									<Button
										isSecondary
										disabled={ currentPage >= totalPages }
										onClick={ () =>
											setCurrentPage( ( prev ) => prev + 1 )
										}
									>
										{ __( 'Next', 'onemedia' ) }
									</Button>
								</div>

								{ /* Share Button */ }
								<div className="onemedia-actions">
									<Button
										variant="primary"
										onClick={ openShareMediaModal }
										disabled={ 0 === getSelectedCount() }
									>
										{ __( 'Share Selected Media', 'onemedia' ) }
									</Button>
								</div>
							</>
						) }
					</CardBody>
				</Card>

				{ /* Brand Sites Modal */ }
				{ isShareMediaModalOpen && (
					<ShareMediaModal
						setIsShareMediaModalOpen={ setIsShareMediaModalOpen }
						getSelectedCount={ getSelectedCount }
						syncOption={ syncOption }
						brandSites={ brandSites }
						selectedSites={ selectedSites }
						handleSiteSelect={ handleSiteSelect }
						handleShareMedia={ handleShareMedia }
						getSelectedSitesCount={ getSelectedSitesCount }
						loading={ loading }
						setNotice={ setNotice }
					/>
				) }

				{ /* Version Modal */ }
				{ isVersionModalOpen && (
					<VersionModal
						setIsVersionModalOpen={ setIsVersionModalOpen }
						attachmentVersions={ currentRevision }
						handleVersionSelect={ handleVersionSelect }
						loading={ loading }
					/>
				) }
			</div>
		</>
	);
};

const MediaLibraryTabs = () => {
	const tabs = [
		{
			name: 'media',
			title: __( 'Non-Sync Media', 'onemedia' ),
			imageType: '',
			cardTitle: __( 'Non-Sync Media', 'onemedia' ),
			cardDescription: __(
				'This media will be shared in non synced mode.',
				'onemedia',
			),
		},
		{
			name: ONEMEDIA_PLUGIN_TAXONOMY_TERM,
			title: __( 'Sync Media', 'onemedia' ),
			imageType: ONEMEDIA_PLUGIN_TAXONOMY_TERM,
			cardTitle: __( 'Sync Media', 'onemedia' ),
			cardDescription: __(
				'This media will be shared in synced mode.',
				'onemedia',
			),
		},
	];

	return (
		<TabPanel
			className="onemedia-media-library-tabs"
			activeClass="is-active"
			tabs={ tabs }
			initialTabName="media"
		>
			{ ( tab ) => {
				return (
					<MediaSharingApp
						imageType={ tab.imageType }
						cardTitle={ tab.cardTitle }
						cardDescription={ tab.cardDescription }
					/>
				);
			} }
		</TabPanel>
	);
};

const root = document.getElementById( 'onemedia-media-sharing' );
if ( root ) {
	const mediaSharingApp = createRoot( root );
	mediaSharingApp.render( <MediaLibraryTabs /> );
}
