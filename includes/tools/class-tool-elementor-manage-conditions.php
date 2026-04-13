<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Manage_Conditions extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_manage_conditions',
			'description' => 'Read display conditions on an Elementor Pro theme builder template.',
			'params'      => array(
				array( 'name' => 'template_id', 'required' => true, 'desc' => 'From elementor_list_templates' ),
			),
		);
	
	}
}
