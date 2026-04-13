<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Store_Health extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'store_health',
			'description' => 'WooCommerce store health: orders, revenue, stuck orders, stock issues, health score.',
			'params'      => array(),
		);
	
	}
}
