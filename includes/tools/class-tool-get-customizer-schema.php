<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Customizer_Schema extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_customizer_schema',
			'description' => 'Discover all Customizer settings with labels, types, choices. Classic themes only.',
			'params'      => array(
				array( 'name' => 'refresh', 'required' => false, 'desc' => 'true = bypass cache' ),
			),
		);
	
	}
}
