<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Toggle_Plugin extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'toggle_plugin',
			'description' => 'Activate or deactivate a WordPress plugin.',
			'params'      => array(
				array( 'name' => 'plugin_file', 'required' => true, 'desc' => 'From list_plugins' ),
				array( 'name' => 'activate', 'required' => true, 'desc' => 'true|false' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
