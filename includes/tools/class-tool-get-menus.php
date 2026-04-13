<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Menus extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_menus',
			'description' => 'List navigation menus, items, and theme locations. Auto-detects FSE vs classic menus. Use mode=summary to get menu names and item counts without full item lists.',
			'params'      => array(
				array( 'name' => 'menu_id', 'required' => false, 'desc' => 'Specific menu ID for details (default: list all)' ),
				array( 'name' => 'mode', 'required' => false, 'desc' => 'summary|full — summary omits item details (default: full)' ),
			),
		);
	
	}
}
