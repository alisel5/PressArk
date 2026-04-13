<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Order_Statuses extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_order_statuses',
			'description' => 'List all registered WooCommerce order statuses including custom ones.',
			'params'      => array(),
		);
	
	}
}
