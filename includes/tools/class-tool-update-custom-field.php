<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Update_Custom_Field extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'update_custom_field',
			'description' => 'Hint: Use this (not update_meta) when ACF is active. Update a custom field value.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'key', 'required' => true, 'desc' => 'From get_custom_fields' ),
				array( 'name' => 'value', 'required' => true, 'desc' => 'String, number, or array' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
