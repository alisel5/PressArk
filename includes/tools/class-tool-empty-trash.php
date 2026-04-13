<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Empty_Trash extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'empty_trash',
			'description' => 'Permanently delete trashed posts/pages. Irreversible.',
			'params'      => array(
				array( 'name' => 'post_ids', 'required' => false, 'desc' => 'Specific IDs, or omit for all trash' ),
				array( 'name' => 'post_type', 'required' => false, 'desc' => 'post|page|product|any (default: any)' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
