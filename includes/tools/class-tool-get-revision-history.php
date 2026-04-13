<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Revision_History extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_revision_history',
			'description' => 'Post edit history. Pass compare_to for field-level diff against a revision.',
			'type'        => 'read',
			'params'      => array(
				array( 'name' => 'post_id', 'type' => 'integer', 'required' => true ),
				array( 'name' => 'limit', 'type' => 'integer', 'required' => false, 'desc' => 'Default: 10, max: 20' ),
				array( 'name' => 'compare_to', 'type' => 'integer', 'required' => false, 'desc' => 'Revision ID to diff against current' ),
			),
		);
	
	}
}
