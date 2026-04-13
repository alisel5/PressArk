<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Taxonomies extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_taxonomies',
			'description' => 'List all registered taxonomies and their terms.',
			'params'      => array(
				array( 'name' => 'taxonomy', 'required' => false, 'desc' => 'Specific slug (default: list all)' ),
				array( 'name' => 'hide_empty', 'required' => false, 'desc' => 'Hide terms with no posts (default: false)' ),
			),
		);
	
	}
}
