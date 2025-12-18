/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardHeader,
	CardBody,
	Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

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
		<Card className="onemedia-site-table">
			<CardHeader>
				<h3>{ __( 'Brand Sites', 'onemedia' ) }</h3>
				<Button
					className="onemedia-add-brand-site-button"
					variant="primary"
					onClick={ () => setShowModal( true ) }
				>
					{ __( 'Add Brand Site', 'onemedia' ) }
				</Button>
			</CardHeader>
			<CardBody className="onemedia-site-table-body">
				<table className="wp-list-table widefat striped">
					<thead>
						<tr>
							<th>{ __( 'Site Name', 'onemedia' ) }</th>
							<th>{ __( 'Site URL', 'onemedia' ) }</th>
							<th>{ __( 'API Key', 'onemedia' ) }</th>
							<th>{ __( 'Actions', 'onemedia' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ 0 === sites.length && (
							<tr>
								<td className="onemedia-site-table-empty" colSpan="5">
									{ __( 'No Brand Sites found.', 'onemedia' ) }
								</td>
							</tr>
						) }
						{ sites?.map( ( site, index ) => (
							<tr key={ index }>
								<td>{ site?.name }</td>
								<td>{ site?.url }</td>
								<td>
									<code>
										{ site.apiKey.substring( 0, 10 ) }...
									</code>
								</td>
								<td>
									<Button
										className="onemedia-edit-site-button"
										variant="secondary"
										onClick={ () => {
											setFormData( site );
											onEdit( index );
											setShowModal( true );
										} }
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

const DeleteConfirmationModal = ( { onConfirm, onCancel } ) => (
	<Modal
		title={ __( 'Delete Brand Site', 'onemedia' ) }
		onRequestClose={ onCancel }
		isDismissible={ true }
	>
		<p>
			{ __(
				'Are you sure you want to delete this Brand Site? This action cannot be undone.',
				'onemedia',
			) }
		</p>
		<Button variant="secondary" isDestructive onClick={ onConfirm }>
			{ __( 'Delete', 'onemedia' ) }
		</Button>
		<Button
			className="onemedia-cancel-delete-button"
			variant="secondary"
			isSecondary
			onClick={ onCancel }
		>
			{ __( 'Cancel', 'onemedia' ) }
		</Button>
	</Modal>
);

export default SiteTable;
