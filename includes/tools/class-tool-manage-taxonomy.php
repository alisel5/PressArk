<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Manage_Taxonomy extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'manage_taxonomy',
			'description' => 'Create, edit, or delete taxonomy terms.',
			'params'      => array(
				array( 'name' => 'operation', 'required' => true, 'desc' => 'create|edit|delete' ),
				array( 'name' => 'taxonomy', 'required' => true, 'desc' => 'Taxonomy slug' ),
				array( 'name' => 'term_id', 'required' => false, 'desc' => 'Required for edit/delete' ),
				array( 'name' => 'name', 'required' => false, 'desc' => 'Required for create' ),
				array( 'name' => 'slug', 'required' => false ),
				array( 'name' => 'description', 'required' => false ),
				array( 'name' => 'parent', 'required' => false, 'desc' => 'Parent term ID for hierarchical taxonomies' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
