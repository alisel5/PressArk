<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Profile_Queries extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'profile_queries',
			'description' => 'Profile DB queries: slowest, duplicates, total stats. Requires SAVEQUERIES.',
			'type'        => 'read',
			'params'      => array(
				array( 'name' => 'url', 'type' => 'string', 'required' => false ),
			),
		);
	
	}
}
