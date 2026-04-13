<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Network_Overview extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'network_overview',
			'description' => 'Hint: If is_multisite=true, use for subsites, themes, plugins, content stats. Multisite network overview.',
			'params'      => array(
				array( 'name' => 'limit', 'required' => false, 'desc' => 'Default: 20' ),
				array( 'name' => 'offset', 'required' => false, 'desc' => 'Default: 0' ),
			),
		);
	
	}
}
