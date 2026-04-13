<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Read_Form extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_read_form',
			'description' => 'Read an Elementor Pro Form: fields, email settings, success message, redirect.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'widget_id', 'required' => true, 'desc' => 'Form widget ID from elementor_read_page' ),
			),
		);
	
	}
}
