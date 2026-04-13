<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Diagnose_Cache extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'diagnose_cache',
			'description' => 'Hint: Detects Redis/Memcached with specific recommendation. Diagnose object cache setup.',
			'type'        => 'read',
			'params'      => array(),
		);
	
	}
}
