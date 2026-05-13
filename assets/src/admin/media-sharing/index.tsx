/**
 * WordPress dependencies
 */
/**
 * External dependencies
 */
import { useState, useEffect, useCallback, useMemo } from 'react';
import { createRoot } from 'react-dom/client';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	Snackbar,
	Spinner,
	Tooltip,
	TabPanel,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, pencil } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import syncIcon from './components/syncIcon';
import versionIcon from './components/versionIcon';
import BrowserUploaderButton from './components/browser-uploader';
import ShareMediaModal from './components/ShareMediaModal';
import VersionModal from './components/VersionModal';
import {
	fetchSyncedSites as fetchSyncedSitesApi,
	fetchMediaItems as fetchMediaItemsApi,
	fetchBrandSites as fetchBrandSitesApi,
	shareMedia as shareMediaApi,
	uploadMedia,
} from '../../components/api';
import {
	getNoticeClass,
	trimTitle,
	debounce,
	getFrameProperty,
	restrictMediaFrameUploadTypes,
	getAllowedMimeTypeExtensions,
} from '../../js/utils';
import type { NoticeState } from '../../types/notice';
import type { BrandSite } from '../../types/settings';
import type {
	AttachmentVersion,
	MediaItem,
	MediaSharingAppProps,
	MimeTypeMap,
	SelectedMediaMap,
	SelectedSitesMap,
	ShareMediaPayload,
	SyncOption,
	SyncedSitesMap,
} from '../../types/media-sharing';
import type { WPMediaAttachmentModel } from '../../types/wordpress';

const fallbackImage = new URL(
	'../../images/fallback-image.svg',
	import.meta.url
).toString();

const MEDIA_PER_PAGE = 12;
const ONEMEDIA_PLUGIN_TAXONOMY_TERM = 'onemedia';
const UPLOAD_NONCE = window.OneMediaMediaSharing.uploadNonce || '';
const ONEMEDIA_MEDIA_SHARING = window.OneMediaMediaSharing;
const ALLOWED_MIME_TYPES_MAP: MimeTypeMap =
	window.OneMediaMediaFrame.allowedMimeTypesMap ?? {};

const MediaSharingApp = ( {
	imageType = '',
	cardTitle = '',
	cardDescription = '',
}: MediaSharingAppProps ) => {
	const [ mediaItems, setMediaItems ] = useState< MediaItem[] >( [] );
	const [ selectedMedia, setSelectedMedia ] = useState< SelectedMediaMap >(
		{}
	);
	const [ currentPage, setCurrentPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ loading, setLoading ] = useState( false );
	const [ initLoading, setInitLoading ] = useState( false );
	const [ syncedSites, setSyncedSites ] = useState< SyncedSitesMap >( {} );
	const [ selectedVersionId, setSelectedVersionId ] = useState<
		number | null
	>( null );
	const [ currentRevision, setCurrentRevision ] = useState<
		AttachmentVersion[]
	>( [] );
	const [ brandSites, setBrandSites ] = useState< BrandSite[] >( [] );
	const [ selectedSites, setSelectedSites ] = useState< SelectedSitesMap >(
		{}
	);
	const [ searchTerm, setSearchTerm ] = useState( '' );
	const [ debouncedSearchTerm, setDebouncedSearchTerm ] = useState( '' );
	const [ notice, setNotice ] = useState< NoticeState | null >( null );
	const [ isShareMediaModalOpen, setIsShareMediaModalOpen ] =
		useState( false );
	const [ isVersionModalOpen, setIsVersionModalOpen ] = useState( false );
	const [ syncOption, setSyncOption ] = useState< SyncOption >( 'sync' );

	const perPage = MEDIA_PER_PAGE;

	const localizationError = (): never => {
		throw new Error(
			__(
				"OneMediaMediaSharing object not found. Make sure it's properly localized.",
				'onemedia'
			)
		);
	};

	const handleDebouncedSearch = useMemo(
		() =>
			debounce( ( value: string ) => {
				setDebouncedSearchTerm( value );
				setSelectedMedia( {} );
				setCurrentPage( 1 );
			}, 1000 ),
		[]
	);

	const fetchSyncedSites = useCallback( async () => {
		const sites = await fetchSyncedSitesApi( setNotice );
		setSyncedSites( sites );
	}, [] );

	const fetchMediaItems = useCallback(
		async ( page: number, search: string = '' ) => {
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
				addNotice: setNotice,
			} );

			setTotalPages( data.total_pages || 1 );
			setMediaItems( data.media_files || [] );
			setLoading( false );
			setInitLoading( false );
		},
		[ imageType, perPage ]
	);

	const fetchBrandSites = useCallback( async () => {
		if ( ! ONEMEDIA_MEDIA_SHARING ) {
			localizationError();
		}

		const sitesData = await fetchBrandSitesApi( setNotice );
		setBrandSites( sitesData );

		const initialSelectedSites = sitesData.reduce< SelectedSitesMap >(
			( accumulator, site ) => {
				accumulator[ site.url ] = false;
				return accumulator;
			},
			{}
		);
		setSelectedSites( initialSelectedSites );
	}, [] );

	useEffect( () => {
		void fetchBrandSites();
		void fetchSyncedSites();
	}, [ fetchBrandSites, fetchSyncedSites ] );

	useEffect( () => {
		void fetchMediaItems( currentPage, debouncedSearchTerm );
	}, [ currentPage, debouncedSearchTerm, fetchMediaItems ] );

	useEffect( () => {
		const handleMediaReplaced = ( event: Event ) => {
			const customEvent = event as CustomEvent< {
				attachmentId?: number;
			} >;
			if ( customEvent.detail?.attachmentId ) {
				void fetchMediaItems( currentPage );
			}
		};

		document.addEventListener( 'mediaReplaced', handleMediaReplaced );

		return () => {
			document.removeEventListener(
				'mediaReplaced',
				handleMediaReplaced
			);
		};
	}, [ currentPage, fetchMediaItems, imageType ] );

	useEffect( () => {
		setSyncOption(
			imageType === ONEMEDIA_PLUGIN_TAXONOMY_TERM ? 'sync' : 'no_sync'
		);
		setCurrentPage( 1 );
		setSelectedMedia( {} );
		setSearchTerm( '' );
		setDebouncedSearchTerm( '' );
	}, [ imageType ] );

	const handleMediaSelect = ( mediaId: number ) => {
		setSelectedMedia( ( previous ) => ( {
			...previous,
			[ mediaId ]: ! previous[ mediaId ],
		} ) );
	};

	const handleSiteSelect = (
		siteUrlOrSelection: string | SelectedSitesMap,
		isBulk: boolean = false
	) => {
		if ( isBulk ) {
			setSelectedSites( siteUrlOrSelection as SelectedSitesMap );
			return;
		}

		const siteUrl = siteUrlOrSelection as string;
		setSelectedSites( ( previous ) => ( {
			...previous,
			[ siteUrl ]: ! previous[ siteUrl ],
		} ) );
	};

	const handleEditMedia = ( mediaId: number ) => {
		if ( ! getFrameProperty( 'wp.media' ) ) {
			return;
		}

		const editFrame = window.wp.media( {
			title: __( 'Edit Media', 'onemedia' ),
			button: {
				text: __( 'Update', 'onemedia' ),
			},
			multiple: false,
			library: {
				type: 'image',
				is_onemedia_sync: true,
			},
		} );

		restrictMediaFrameUploadTypes(
			editFrame,
			getAllowedMimeTypeExtensions( ALLOWED_MIME_TYPES_MAP ).join( ',' ),
			true
		);

		editFrame.on( 'open', () => {
			const selection = editFrame.state().get( 'selection' );
			if ( selection && 'reset' in selection ) {
				selection.reset();
			}

			const attachment = window.wp.media.attachment(
				mediaId
			) as WPMediaAttachmentModel;
			attachment
				.fetch()
				.then( () => {
					if ( selection && 'add' in selection ) {
						selection.add( attachment );
					}
					editFrame.content.mode( 'browse' );
				} )
				.catch( () => {
					if ( selection && 'add' in selection ) {
						selection.add( attachment );
					}
				} );

			if ( editFrame.el ) {
				editFrame.el.classList.add( 'onemedia-edit-media-frame' );
			}
		} );

		editFrame.on( 'close', () => {
			void fetchMediaItems( currentPage, debouncedSearchTerm );
			setSelectedMedia( {} );
			void fetchSyncedSites();
		} );

		editFrame.open();
	};

	const openShareMediaModal = () => {
		if ( brandSites.length === 0 ) {
			setNotice( {
				type: 'warning',
				message: __(
					'Please add at least one brand site from OneMedia Settings page',
					'onemedia'
				),
			} );
			return;
		}

		const hasSelectedMedia = Object.values( selectedMedia ).some( Boolean );
		if ( ! hasSelectedMedia ) {
			setNotice( {
				type: 'warning',
				message: __(
					'Please select at least one media item.',
					'onemedia'
				),
			} );
			return;
		}

		setIsShareMediaModalOpen( true );
	};

	const checkMediaRevisionExists = ( mediaId: number ): boolean => {
		const media =
			mediaItems.find( ( item ) => item.id === mediaId ) || null;
		return Boolean( media && media.revision && media.revision.length > 1 );
	};

	const openVersionModal = async ( mediaId: number ) => {
		const version =
			mediaItems.find( ( item ) => item.id === mediaId )?.revision || [];

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

		const selectedBrandSites = Object.entries( selectedSites )
			.filter( ( [ , isSelected ] ) => isSelected )
			.map( ( [ url ] ) => url );

		if ( selectedBrandSites.length === 0 ) {
			setNotice( {
				type: 'warning',
				message: __(
					'Please select at least one brand site.',
					'onemedia'
				),
			} );
			return;
		}

		const selectedMediaIds = Object.entries( selectedMedia )
			.filter( ( [ , isSelected ] ) => isSelected )
			.map( ( [ mediaId ] ) => parseInt( mediaId, 10 ) );

		const selectedMediaDetails = mediaItems.filter( ( media ) =>
			selectedMediaIds.includes( media.id )
		);

		const payload: ShareMediaPayload = {
			sync_option: syncOption,
			brand_sites: selectedBrandSites,
			media_details: selectedMediaDetails,
		};

		setLoading( true );
		const data = await shareMediaApi( payload, setNotice );

		if ( data.status === 200 ) {
			setNotice( {
				type: 'success',
				message:
					imageType === ONEMEDIA_PLUGIN_TAXONOMY_TERM
						? __( 'Media synced successfully!', 'onemedia' )
						: __( 'Media shared successfully!', 'onemedia' ),
			} );
			void fetchSyncedSites();
		} else {
			const failedSites = data?.data?.failed_sites || [];
			if ( failedSites.length > 0 ) {
				setNotice( {
					type: 'warning',
					message: sprintf(
						/* translators: %s: failed site names. */
						__(
							'Failed to sync media files to some brand sites: %s',
							'onemedia'
						),
						failedSites
							.map( ( site ) =>
								site.is_mime_type_error
									? site.message
									: site.site_name
							)
							.filter( Boolean )
							.join( ', ' )
					),
				} );
			}
			void fetchSyncedSites();
		}

		setSelectedMedia( {} );
		setIsShareMediaModalOpen( false );
		setSelectedSites(
			Object.keys( selectedSites ).reduce< SelectedSitesMap >(
				( accumulator, site ) => {
					accumulator[ site ] = false;
					return accumulator;
				},
				{}
			)
		);
		setLoading( false );
	};

	const handleVersionSelect = async ( version: AttachmentVersion ) => {
		if ( ! version.file || selectedVersionId === null ) {
			setNotice( {
				type: 'error',
				message: __( 'Invalid version selected.', 'onemedia' ),
			} );
			return;
		}

		setLoading( true );
		try {
			const formData = new FormData();
			formData.append( 'file', JSON.stringify( version.file ) );
			formData.append( 'action', 'onemedia_replace_media' );
			formData.append( 'current_media_id', String( selectedVersionId ) );

			if ( UPLOAD_NONCE ) {
				formData.append( '_ajax_nonce', UPLOAD_NONCE );
			}

			formData.append( 'is_version_restore', 'true' );

			const response = await uploadMedia( formData, setNotice );
			if ( response?.success ) {
				setIsVersionModalOpen( false );
				void fetchMediaItems( currentPage, debouncedSearchTerm );
				void fetchSyncedSites();
				setNotice( {
					type: 'success',
					message: __(
						'Media version restored successfully!',
						'onemedia'
					),
				} );
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'An error occurred while restoring media version.',
					'onemedia'
				),
			} );
		} finally {
			setLoading( false );
		}
	};

	const getSelectedCount = (): number => {
		return Object.values( selectedMedia ).filter( Boolean ).length;
	};

	const getSelectedSitesCount = (): number => {
		return Object.values( selectedSites ).filter( Boolean ).length;
	};

	const renderMediaGrid = () => {
		if ( mediaItems.length === 0 ) {
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
						className={ `onemedia-media-item ${
							selectedMedia[ media.id ] ? 'selected' : ''
						}` }
					>
						<div
							className="onemedia-media-thumbnail"
							onClick={ () => handleMediaSelect( media.id ) }
							onKeyDown={ (
								event: React.KeyboardEvent< HTMLDivElement >
							) => {
								if (
									[ 'Enter', 'Space' ].includes( event.code )
								) {
									event.preventDefault();
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
									onError={ (
										event: React.SyntheticEvent< HTMLImageElement >
									) => {
										event.currentTarget.onerror = null;
										event.currentTarget.src = fallbackImage;
										event.currentTarget.style.width = '60%';
										event.currentTarget.style.padding =
											'0px 20%';
									} }
								/>
								<div className="onemedia-media-checkbox">
									<CheckboxControl
										checked={ !! selectedMedia[ media.id ] }
										onChange={ () =>
											handleMediaSelect( media.id )
										}
										label=""
										__nextHasNoMarginBottom
									/>
								</div>
								{ imageType ===
									ONEMEDIA_PLUGIN_TAXONOMY_TERM && (
									<>
										<div className="onemedia-media-edit-button">
											<Button
												size="small"
												variant="secondary"
												icon={ pencil }
												onClick={ (
													event: React.MouseEvent< HTMLButtonElement >
												) => {
													event.stopPropagation();
													handleEditMedia( media.id );
												} }
												title={ __(
													'Edit Media',
													'onemedia'
												) }
											>
												{ __( 'Edit', 'onemedia' ) }
											</Button>
										</div>
										{ checkMediaRevisionExists(
											media.id
										) && (
											<Tooltip
												text={ sprintf(
													/* translators: %d: number of previous versions */
													__(
														'%d previous version(s) available',
														'onemedia'
													),
													( media.revision?.length ||
														1 ) - 1
												) }
												placement="bottom"
											>
												<Button
													className="onemedia-media-version-button"
													onClick={ (
														event: React.MouseEvent< HTMLButtonElement >
													) => {
														event.stopPropagation();
														void openVersionModal(
															media.id
														);
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
										text={ Object.values(
											syncedSites[ media.id ] || {}
										).join( ', ' ) }
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

	const selectedCount = getSelectedCount();
	const selectedCountLabel =
		selectedCount > 0
			? sprintf(
					/* translators: %d: number of selected items */
					__( '%d items selected', 'onemedia' ),
					selectedCount
			  )
			: null;

	return (
		<>
			{ notice?.message && (
				<Snackbar
					explicitDismiss={ false }
					onRemove={ () => setNotice( null ) }
					className={ getNoticeClass( notice.type ) }
				>
					{ notice.message }
				</Snackbar>
			) }
			<div className="onemedia-media-sharing-app">
				<Card>
					<CardHeader>
						<div className="onemedia-card-header">
							<div className="onemedia-card-header-content">
								<h2 className="onemedia-card-title">
									{ cardTitle }
								</h2>
								<p className="onemedia-card-description">
									{ cardDescription }
								</p>
							</div>
							<div className="onemedia-card-header-actions-container">
								<TextControl
									className="onemedia-search-control"
									placeholder={ __(
										'Search media…',
										'onemedia'
									) }
									value={ searchTerm }
									onChange={ ( value ) => {
										setSearchTerm( value );
										handleDebouncedSearch( value );
									} }
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<div className="onemedia-card-header-actions">
									{ imageType ===
									ONEMEDIA_PLUGIN_TAXONOMY_TERM ? (
										<BrowserUploaderButton
											onAddMediaSuccess={ () => {
												setSearchTerm( '' );
												setDebouncedSearchTerm( '' );
												void fetchMediaItems(
													currentPage
												);
												void fetchSyncedSites();
											} }
											isSyncMediaUpload
											addedMedia={ mediaItems }
											setNotice={ setNotice }
										/>
									) : (
										<BrowserUploaderButton
											onAddMediaSuccess={ () => {
												setSearchTerm( '' );
												setDebouncedSearchTerm( '' );
												void fetchMediaItems(
													currentPage
												);
											} }
											isSyncMediaUpload={ false }
											addedMedia={ mediaItems }
											setNotice={ setNotice }
										/>
									) }
									<div className="onemedia-selection-count">
										{ selectedCountLabel }
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
								<div className="onemedia-pagination">
									<Button
										isSecondary
										disabled={ currentPage === 1 }
										onClick={ () =>
											setCurrentPage(
												( previous ) => previous - 1
											)
										}
									>
										{ __( 'Previous', 'onemedia' ) }
									</Button>
									<span className="onemedia-page-indicator">
										{ sprintf(
											/* translators: %1$d: current page number, %2$d: total pages */
											__( '%1$d of %2$d', 'onemedia' ),
											currentPage,
											totalPages
										) }
									</span>
									<Button
										isSecondary
										disabled={ currentPage >= totalPages }
										onClick={ () =>
											setCurrentPage(
												( previous ) => previous + 1
											)
										}
									>
										{ __( 'Next', 'onemedia' ) }
									</Button>
								</div>
								<div className="onemedia-actions">
									<Button
										variant="primary"
										onClick={ openShareMediaModal }
										disabled={ getSelectedCount() === 0 }
									>
										{ __(
											'Share Selected Media',
											'onemedia'
										) }
									</Button>
								</div>
							</>
						) }
					</CardBody>
				</Card>

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

interface MediaLibraryTab {
	name: string;
	title: string;
	imageType: string;
	cardTitle: string;
	cardDescription: string;
}

const MediaLibraryTabs = () => {
	const tabs: MediaLibraryTab[] = [
		{
			name: 'media',
			title: __( 'Non-Sync Media', 'onemedia' ),
			imageType: '',
			cardTitle: __( 'Non-Sync Media', 'onemedia' ),
			cardDescription: __(
				'This media will be shared in non synced mode.',
				'onemedia'
			),
		},
		{
			name: ONEMEDIA_PLUGIN_TAXONOMY_TERM,
			title: __( 'Sync Media', 'onemedia' ),
			imageType: ONEMEDIA_PLUGIN_TAXONOMY_TERM,
			cardTitle: __( 'Sync Media', 'onemedia' ),
			cardDescription: __(
				'This media will be shared in synced mode.',
				'onemedia'
			),
		},
	];

	return (
		<TabPanel
			className="onemedia-media-library-tabs"
			activeClass="is-active"
			tabs={ tabs.map( ( { name, title } ) => ( { name, title } ) ) }
			initialTabName="media"
		>
			{ ( tab ) => {
				const activeTab =
					tabs.find( ( item ) => item.name === tab.name ) ||
					tabs[ 0 ];
				if ( ! activeTab ) {
					return null;
				}

				return (
					<MediaSharingApp
						imageType={ activeTab.imageType }
						cardTitle={ activeTab.cardTitle }
						cardDescription={ activeTab.cardDescription }
					/>
				);
			} }
		</TabPanel>
	);
};

const target = document.getElementById( 'onemedia-media-sharing' );
if ( target ) {
	const mediaSharingApp = createRoot( target );
	mediaSharingApp.render( <MediaLibraryTabs /> );
}
