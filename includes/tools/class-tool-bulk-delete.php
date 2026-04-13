<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Bulk_Delete extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'bulk_delete',
			'description' => 'Move multiple posts/pages to trash at once. Restorable.',
			'params'      => array(
				array( 'name' => 'post_ids', 'required' => true, 'desc' => 'Array of IDs to trash' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
