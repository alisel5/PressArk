<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Site_Settings extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_site_settings',
			'description' => 'Read WordPress site settings. Pass discover=true to list all registered options by page. Use section to filter discover results to a specific settings group.',
			'params'      => array(
				array( 'name' => 'keys', 'required' => false, 'desc' => 'Array of option names (default: common settings)' ),
				array( 'name' => 'discover', 'type' => 'boolean', 'required' => false, 'desc' => 'true = list all registered settings grouped by page' ),
				array( 'name' => 'section', 'required' => false, 'desc' => 'Filter discover results to a specific group (e.g. general, reading, writing, discussion, media, permalink)' ),
			),
		);
	
	}
}
