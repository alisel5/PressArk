<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Bulk_Delete_Media extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'bulk_delete_media',
			'description' => 'Permanently delete multiple media attachments at once.',
			'params'      => array(
				array( 'name' => 'attachment_ids', 'required' => true, 'desc' => 'Array of attachment IDs' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
