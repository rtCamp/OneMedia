/**
 * WordPress dependencies
 */
import { useState } from 'react';
import { Button, Card, CardHeader, CardBody, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * SiteTable component to display and manage brand sites.
 *
 * @param {Object}   props              - Component properties.
 * @param {Array}    props.sites        - List of brand sites.
 * @param {Function} props.onEdit       - Function to handle editing a site.
 * @param {Function} props.onDelete     - Function to handle deleting a site.
 * @param {Function} props.setFormData  - Function to set form data for editing.
 * @param {Function} props.setShowModal - Function to show/hide the modal for adding/editing a site.
 *
 * @return {JSX.Element} Rendered component.
 */
const SiteTable = ( { sites, onEdit, onDelete, setFormData, setShowModal } ) => {
	const [ showDeleteModal, setShowDeleteModal ] = useState( false );
	const [ deleteIndex, setDeleteIndex ] = useState( null );

	const handleDeleteClick = ( index ) => {
		setDeleteIndex( index );
		setShowDeleteModal( true );
	};

	const handleDeleteConfirm = () => {
		onDelete( deleteIndex );
		setShowDeleteModal( false );
		setDeleteIndex( null );
	};

	const handleDeleteCancel = () => {
		setShowDeleteModal( false );
		setDeleteIndex( null );
	};

	return (
		<Card style={ { marginTop: '30px' } }>
			<CardHeader>
				<h3>{ __( 'Brand Sites', 'onemedia' ) }</h3>
				<Button
					style={ { width: 'fit-content' } }
					variant="primary"
					onClick={ () => setShowModal( true ) }
				>
					{ __( 'Add Brand Site', 'onemedia' ) }
				</Button>
			</CardHeader>
			<CardBody>
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>{ __( 'Site Name', 'onemedia' ) }</th>
							<th>{ __( 'Site URL', 'onemedia' ) }</th>
							<th>{ __( 'API Key', 'onemedia' ) }</th>
							<th>{ __( 'Actions', 'onemedia' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ sites.length === 0 && (
							<tr>
								<td colSpan="4" style={ { textAlign: 'center' } }>
									{ __( 'No Brand Sites found.', 'onemedia' ) }
								</td>
							</tr>
						) }
						{ sites?.map( ( site, index ) => (
							<tr key={ index }>
								<td>{ site?.name }</td>
								<td>{ site?.url }</td>
								<td><code>{ site?.api_key.substring( 0, 10 ) }...</code></td>
								<td>
									<Button
										variant="secondary"
										onClick={ () => {
											setFormData( site );
											onEdit( index );
											setShowModal( true );
										} }
										disabled={ site?.is_editable === false }
										style={ { marginRight: '8px' } }
									>
										{ __( 'Edit', 'onemedia' ) }
									</Button>
									<Button
										variant="secondary"
										isDestructive
										onClick={ () => handleDeleteClick( index ) }
									>
										{ __( 'Delete', 'onemedia' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</CardBody>
			{ showDeleteModal && (
				<DeleteConfirmationModal
					onConfirm={ handleDeleteConfirm }
					onCancel={ handleDeleteCancel }
				/>
			) }
		</Card>
	);
};

/**
 * DeleteConfirmationModal component for confirming site deletion.
 *
 * @param {Object}   props           - Component properties.
 * @param {Function} props.onConfirm - Function to call on confirmation.
 * @param {Function} props.onCancel  - Function to call on cancellation.
 * @return {JSX.Element} Rendered component.
 */
const DeleteConfirmationModal = ( { onConfirm, onCancel } ) => (
	<Modal
		title={ __( 'Delete Brand Site', 'onemedia' ) }
		onRequestClose={ onCancel }
		isDismissible={ true }
	>
		<p>{ __( 'Are you sure you want to delete this Brand Site? This action cannot be undone.', 'onemedia' ) }</p>
		<div style={ { display: 'flex', justifyContent: 'flex-end', marginTop: '20px', gap: '16px' } }>
			<Button
				variant="secondary"
				onClick={ onCancel }
			>
				{ __( 'Cancel', 'onemedia' ) }
			</Button>
			<Button
				variant="primary"
				isDestructive
				onClick={ onConfirm }
			>
				{ __( 'Delete', 'onemedia' ) }
			</Button>
		</div>
	</Modal>
);

export default SiteTable;
