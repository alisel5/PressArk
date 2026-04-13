<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Analyze_Store extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'analyze_store',
			'description' => 'WooCommerce health check: missing descriptions, images, categories, prices, low stock.',
			'params'      => array(),
		);
	
	}
}
