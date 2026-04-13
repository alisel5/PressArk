<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Top_Sellers extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_top_sellers',
			'description' => 'Top selling products by revenue or quantity for a period.',
			'params'      => array(
				array( 'name' => 'days', 'required' => false, 'desc' => 'Default: 30' ),
				array( 'name' => 'limit', 'required' => false, 'desc' => 'Default: 10' ),
			),
		);
	
	}
}
