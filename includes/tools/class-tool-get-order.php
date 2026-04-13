<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Order extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_order',
			'description' => 'Get full details of a WooCommerce order.',
			'params'      => array(
				array( 'name' => 'order_id', 'required' => true ),
			),
		);
	
	}
}
