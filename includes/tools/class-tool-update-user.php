<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Update_User extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'update_user',
			'description' => 'Update a WordPress user profile. Cannot change passwords.',
			'params'      => array(
				array( 'name' => 'user_id', 'required' => true ),
				array( 'name' => 'display_name', 'required' => false ),
				array( 'name' => 'role', 'required' => false ),
				array( 'name' => 'first_name', 'required' => false ),
				array( 'name' => 'last_name', 'required' => false ),
				array( 'name' => 'description', 'required' => false, 'desc' => 'Bio' ),
				array( 'name' => 'url', 'required' => false, 'desc' => 'Website URL' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
