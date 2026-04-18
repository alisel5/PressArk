<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Products_On_Sale extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_products_on_sale',
			'description' => 'Products on sale with raw pricing fields, discount values, and end dates. Sorted by soonest expiring.',
			'params'      => array(
				array( 'name' => 'limit', 'required' => false, 'desc' => 'Default: 20, max: 100' ),
			),
		);
	
	}
}
