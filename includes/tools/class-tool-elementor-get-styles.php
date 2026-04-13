<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Get_Styles extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_get_styles',
			'description' => 'Get Elementor global styles: colors, typography, container width, spacing.',
			'params'      => array(),
		);
	
	}
}
