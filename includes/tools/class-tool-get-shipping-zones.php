<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Shipping_Zones extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_shipping_zones',
			'description' => 'List WooCommerce shipping zones with methods, costs, and free shipping thresholds.',
			'params'      => array(),
		);
	
	}
}
