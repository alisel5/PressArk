<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Update_Theme_Setting extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'update_theme_setting',
			'description' => 'Update a theme customizer setting by key.',
			'params'      => array(
				array( 'name' => 'setting_name', 'required' => true, 'desc' => 'Theme mod key from get_theme_settings' ),
				array( 'name' => 'value', 'required' => true ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'preview';
	}
}
