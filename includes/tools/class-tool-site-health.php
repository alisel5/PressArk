<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Site_Health extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'site_health',
			'description' => 'Hint: Use include_debug=true for server config, PHP, memory, disk, plugin versions. WordPress Site Health status with optional debug data. Use checks param to run specific checks only.',
			'params'      => array(
				array( 'name' => 'section', 'required' => false, 'desc' => 'status|full (default: status)' ),
				array( 'name' => 'include_debug', 'required' => false, 'desc' => 'true = include server config, PHP version, memory, disk, plugins' ),
				array( 'name' => 'checks', 'required' => false, 'desc' => 'Array of specific test names to run (e.g. ["php_version","ssl_support"]). Default: all tests.' ),
				array( 'name' => 'category', 'required' => false, 'desc' => 'critical|recommended|good — filter results to only this status category' ),
			),
		);
	
	}
}
