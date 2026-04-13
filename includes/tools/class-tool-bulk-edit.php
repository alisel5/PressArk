<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Bulk_Edit extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'bulk_edit',
			'description' => 'Apply the same change to multiple posts/pages. Individually logged and undoable.',
			'params'      => array(
				array( 'name' => 'post_ids', 'required' => true, 'desc' => 'Array of IDs' ),
				array( 'name' => 'changes', 'required' => true, 'desc' => '{status, categories, tags, author}' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
