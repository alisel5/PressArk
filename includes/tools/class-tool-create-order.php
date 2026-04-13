<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Create_Order extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'create_order',
			'description' => 'Create a manual WooCommerce order. Supports variations, billing/shipping, coupons.',
			'params'      => array(
				array( 'name' => 'products', 'required' => true, 'desc' => 'Array of {product_id, quantity, variation_id?}' ),
				array( 'name' => 'billing', 'required' => false, 'desc' => '{first_name, last_name, email, phone, address_1, city, state, postcode, country}' ),
				array( 'name' => 'shipping', 'required' => false, 'desc' => '{first_name, last_name, address_1, city, state, postcode, country}' ),
				array( 'name' => 'customer_email', 'required' => false ),
				array( 'name' => 'customer_id', 'required' => false ),
				array( 'name' => 'payment_method', 'required' => false, 'desc' => 'Gateway ID' ),
				array( 'name' => 'coupon_code', 'required' => false ),
				array( 'name' => 'customer_note', 'required' => false ),
				array( 'name' => 'status', 'required' => false, 'desc' => 'Default: pending' ),
				array( 'name' => 'note', 'required' => false, 'desc' => 'Admin note' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
