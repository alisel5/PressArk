<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Customer extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_customer',
			'description' => 'Get full customer profile: addresses, order history, total spent.',
			'params'      => array(
				array( 'name' => 'customer_id', 'required' => true ),
			),
		);
	
	}
}
