<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Global_Styles extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_global_styles',
			'description' => 'Read Elementor kit settings, or apply minimal patch when forced. Writes will return a notice recommending native WordPress global styles instead.',
			'params'      => array(
				array(
					'name'     => 'updates',
					'required' => false,
					'desc'     => 'Omit to read. Format: {colors: {primary: "#FF0000"}, typography: {primary: {font_family, font_weight}}, theme_style: {h1_color, body_color, link_color, button_background_color, ...}, layout: {content_width, container_width}}',
				),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}

	protected function prompt_weight(): int {
		return -5;
	}
}
