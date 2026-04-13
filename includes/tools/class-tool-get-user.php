<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_User extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_user',
			'description' => 'Get detailed user info by ID. Includes WooCommerce data for customers.',
			'params'      => array(
				array( 'name' => 'user_id', 'required' => true ),
			),
		);
	
	}
}
