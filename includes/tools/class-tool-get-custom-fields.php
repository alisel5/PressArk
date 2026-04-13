<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Custom_Fields extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_custom_fields',
			'description' => 'Hint: Call to discover field names/types before updating -- never guess ACF keys. Summary mode is default; use detail to include current values.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'mode', 'required' => false, 'desc' => 'summary|detail (default: summary)' ),
			),
		);
	
	}
}
