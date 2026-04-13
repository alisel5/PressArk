<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Edit_Template extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'edit_template',
			'description' => 'Edit a block within an FSE template. Creates user override if from theme. Block themes only.',
			'params'      => array(
				array( 'name' => 'slug', 'required' => true ),
				array( 'name' => 'type', 'required' => false, 'desc' => 'wp_template|wp_template_part (default: wp_template)' ),
				array( 'name' => 'block_index', 'required' => true, 'desc' => 'From get_templates, supports "2.1" for inner blocks' ),
				array( 'name' => 'updates', 'required' => true, 'desc' => 'Same format as edit_block' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'preview';
	}
}
