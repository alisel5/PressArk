<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Add_Container extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_add_container',
			'description' => 'LAST RESORT: Add Elementor container. Use only for legacy Elementor sites. Native block patterns are strongly preferred.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'layout', 'required' => false, 'desc' => 'boxed|full_width (default: boxed)' ),
				array( 'name' => 'direction', 'required' => false, 'desc' => 'column|row (default: column)' ),
				array( 'name' => 'position', 'required' => false, 'desc' => '-1 = end (default), 0 = top' ),
				array( 'name' => 'parent_id', 'required' => false, 'desc' => 'Nest inside another container' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'preview';
	}

	protected function prompt_weight(): int {
		return -10;
	}
}
