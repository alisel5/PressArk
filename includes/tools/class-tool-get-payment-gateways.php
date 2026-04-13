<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Payment_Gateways extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_payment_gateways',
			'description' => 'List WooCommerce payment gateways with availability and test mode flags.',
			'params'      => array(
				array( 'name' => 'enabled_only', 'required' => false, 'desc' => 'true = only enabled gateways (default: false)' ),
			),
		);
	
	}
}
