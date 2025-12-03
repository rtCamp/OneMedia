/**
 * WordPress dependencies
 */
import { useState, useCallback } from '@wordpress/element';
import {
	Button,
	Modal,
	Spinner,
	CheckboxControl,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { trimTitle } from '../../js/utils';
import fallbackImage from '../../images/fallback-image.svg';

/**
 * Format a Unix timestamp (in seconds) into a human-readable date and time.
 *
 * @param {number} timestamp - The Unix timestamp (in seconds).
 * @return {string} Formatted string like "12:45 PM on 15 Oct 2025".
 */
const formatLastUsedDate = ( timestamp ) => {
	if ( ! timestamp ) {
		return '';
	}

	const date = new Date( timestamp * 1000 );
	const timePart = date.toLocaleTimeString( 'en-US', {
		hour: 'numeric',
		minute: '2-digit',
		hour12: true,
	} );

	const datePart = date.toLocaleDateString( 'en-GB', {
		day: '2-digit',
		month: 'short',
		year: 'numeric',
	} );
	return `${ timePart } on ${ datePart }`;
};

const VersionModal = ( {
	setIsVersionModalOpen,
	attachmentVersions = [],
	handleVersionSelect,
	loading,
} ) => {
	const [ selectedVersion, setSelectedVersion ] = useState( null );

	const toggleSelect = useCallback(
		( idx ) => setSelectedVersion( ( prev ) => ( prev === idx ? null : idx ) ),
		[],
	);

	const renderMediaGrid = useCallback( () => {
		if ( 0 === attachmentVersions.length ) {
			return <p>{ __( 'No versions available.', 'onemedia' ) }</p>;
		}

		return (
			<div className="onemedia-media-grid">
				{ attachmentVersions.map( ( version, index ) => {
					const isCurrent = index === 0;
					const isSelected = selectedVersion === index;

					const itemProps = isCurrent
						? {
							className: 'onemedia-media-item in-use',
							role: 'group',
							tabIndex: -1,
							'aria-disabled': true,
						}
						: {
							className: `onemedia-media-item ${ isSelected ? 'selected' : '' }`,
							role: 'button',
							tabIndex: 0,
							onKeyDown: ( e ) => {
								if ( e.key === 'Enter' || e.key === ' ' ) {
									e.preventDefault();
									toggleSelect( index );
								}
							},
							'aria-pressed': isSelected,
							onClick: () => toggleSelect( index ),
						};

					const lastUsedText = version?.last_used
						? sprintf(
							/* translators: %s: date */
							__( 'Last used: %s', 'onemedia' ),
							formatLastUsedDate( version?.last_used ),
						)
						: __( 'No usage data', 'onemedia' );

					const media = (
						<div className="onemedia-media-container">
							<div className="onemedia-version-thumbnail">
								<img
									data-id={ version?.file?.attachment_id }
									src={ version?.file?.url }
									alt={ version?.file?.alt }
									loading="lazy"
									onError={ ( e ) => {
										e.target.onerror = null; // Prevent infinite loop.
										e.target.src = fallbackImage;
										e.target.style.padding = '0px 20%';
									} }
								/>
								{ isCurrent && (
									<div className="onemedia-in-use-overlay">
										<span>{ __( 'In Use', 'onemedia' ) }</span>
									</div>
								) }
							</div>

							{ ! isCurrent && (
								<div className="onemedia-version-checkbox">
									<CheckboxControl
										checked={ isSelected }
										onChange={ () => toggleSelect( index ) }
										label=""
										__nextHasNoMarginBottom={ true }
									/>
								</div>
							) }

							<div className="onemedia-version-last-used">
								{ lastUsedText }
							</div>
						</div>
					);

					return (
						<div key={ index } { ...itemProps }>
							{ media }
							<div className="onemedia-media-title">
								{ trimTitle( version?.file?.name ) }
							</div>
						</div>
					);
				} ) }
			</div>
		);
	}, [ attachmentVersions, selectedVersion ] ); // eslint-disable-line react-hooks/exhaustive-deps

	return (
		<Modal
			title={ __( 'Attachment Version', 'onemedia' ) }
			onRequestClose={ () => setIsVersionModalOpen( false ) }
			shouldCloseOnClickOutside={ true }
			size="medium"
			className="onemedia-version-modal"
		>
			<VStack spacing="4">
				<div className="onemedia-selected-media">
					<h3 className="onemedia-selected-media-heading">
						{ __( 'Select a version to restore', 'onemedia' ) }
					</h3>
					<p className="onemedia-selected-media-description">
						{ __( 'Choose from the list of available versions below.', 'onemedia' ) }
					</p>
				</div>

				{ renderMediaGrid() }

				<HStack justify="flex-end" spacing="3">
					<Button
						variant="secondary"
						onClick={ () => setIsVersionModalOpen( false ) }
					>
						{ __( 'Cancel', 'onemedia' ) }
					</Button>
					<Button
						variant="primary"
						onClick={ () => selectedVersion !== null && handleVersionSelect( attachmentVersions[ selectedVersion ] ) }
						isBusy={ loading }
						disabled={ null === selectedVersion || loading }
					>
						{ loading ? <Spinner /> : __( 'Restore', 'onemedia' ) }
					</Button>
				</HStack>
			</VStack>
		</Modal>
	);
};

export default VersionModal;
