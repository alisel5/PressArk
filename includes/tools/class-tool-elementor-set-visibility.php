<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Set_Visibility extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_set_visibility',
			'description' => 'Control Elementor element visibility by device or condition.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'element_id', 'required' => true, 'desc' => 'From elementor_read_page tree' ),
				array( 'name' => 'action', 'required' => false, 'desc' => 'show|hide|show_all (default: show)' ),
				array( 'name' => 'hide_on', 'required' => false, 'desc' => 'Array: ["desktop"], ["tablet"], ["mobile"]' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
