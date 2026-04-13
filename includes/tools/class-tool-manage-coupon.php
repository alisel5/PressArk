<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Manage_Coupon extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'manage_coupon',
			'description' => 'Get, list, create, edit, or delete WooCommerce coupons.',
			'params'      => array(
				array( 'name' => 'operation', 'required' => true, 'desc' => 'get|list|create|edit|delete' ),
				array( 'name' => 'coupon_id', 'required' => false, 'desc' => 'Required for get/edit/delete' ),
				array( 'name' => 'code', 'required' => false, 'desc' => 'Required for create' ),
				array( 'name' => 'discount_type', 'required' => false, 'desc' => 'percent|fixed_cart|fixed_product (default: percent)' ),
				array( 'name' => 'amount', 'required' => false ),
				array( 'name' => 'usage_limit', 'required' => false, 'desc' => '0 = unlimited' ),
				array( 'name' => 'expiry_date', 'required' => false, 'desc' => 'Y-m-d' ),
				array( 'name' => 'minimum_amount', 'required' => false ),
				array( 'name' => 'individual_use', 'required' => false, 'desc' => 'true = cannot combine with other coupons' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
