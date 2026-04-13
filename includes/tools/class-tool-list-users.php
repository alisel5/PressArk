<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Users extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_users',
			'description' => 'List WordPress users with roles, email, registration date. Includes security notices.',
			'params'      => array(
				array( 'name' => 'role', 'required' => false, 'desc' => 'Single role filter' ),
				array( 'name' => 'roles', 'required' => false, 'desc' => 'Array of roles (overrides role)' ),
				array( 'name' => 'exclude_roles', 'required' => false ),
				array( 'name' => 'capability', 'required' => false, 'desc' => 'Filter by WP capability' ),
				array( 'name' => 'has_published_posts', 'required' => false, 'desc' => 'true = only authors with posts' ),
				array( 'name' => 'registered_after', 'required' => false ),
				array( 'name' => 'registered_before', 'required' => false ),
				array( 'name' => 'search', 'required' => false ),
				array( 'name' => 'limit', 'required' => false, 'desc' => 'Default: 20' ),
			),
		);
	
	}
}
