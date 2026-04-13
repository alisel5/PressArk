<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Create_From_Template extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_create_from_template',
			'description' => 'Create a new page from an Elementor template as draft.',
			'params'      => array(
				array( 'name' => 'template_id', 'required' => true, 'desc' => 'From elementor_list_templates' ),
				array( 'name' => 'title', 'required' => true ),
				array( 'name' => 'post_type', 'required' => false, 'desc' => 'page|post (default: page)' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
