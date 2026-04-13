<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Theme_Settings extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_theme_settings',
			'description' => 'Read current theme design settings. Block themes: global styles. Classic: customizer.',
			'params'      => array(),
		);
	
	}
}
