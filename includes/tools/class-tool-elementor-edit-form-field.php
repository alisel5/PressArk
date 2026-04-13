<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Edit_Form_Field extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_edit_form_field',
			'description' => 'Edit a field in an Elementor Pro Form by index from elementor_read_form.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'widget_id', 'required' => true ),
				array( 'name' => 'field_index', 'required' => true, 'desc' => '0-based from elementor_read_form' ),
				array( 'name' => 'changes', 'required' => true, 'desc' => '{label, placeholder, required, type, options, width, id}' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
