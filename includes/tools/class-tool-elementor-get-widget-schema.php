<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Get_Widget_Schema extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_get_widget_schema',
			'description' => 'Discover fields for any Elementor widget type including third-party. Omit type for summary; use mode=detail for the full schema of one widget.',
			'params'      => array(
				array( 'name' => 'widget_type', 'required' => false, 'desc' => 'Omit for all widgets summary' ),
				array( 'name' => 'mode', 'required' => false, 'desc' => 'summary|detail (default: summary without widget_type, detail with widget_type)' ),
			),
		);
	
	}
}
