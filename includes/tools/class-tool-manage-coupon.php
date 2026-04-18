<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Manage_Coupon extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'manage_coupon',
			'description' => 'Get, list, create, edit, or delete WooCommerce coupons with explicit stacking, shipping, product, category, email, and per-user restriction fields.',
			'params'      => array(
				array( 'name' => 'operation', 'required' => true, 'desc' => 'get|list|create|edit|delete' ),
				array( 'name' => 'coupon_id', 'required' => false, 'desc' => 'Required for edit/delete. Optional for get when looking up by ID.' ),
				array( 'name' => 'code', 'required' => false, 'desc' => 'Required for create. Optional for get lookup or edit rename.' ),
				array( 'name' => 'discount_type', 'required' => false, 'desc' => 'percent|fixed_cart|fixed_product (default: percent)' ),
				array( 'name' => 'amount', 'required' => false, 'desc' => 'Coupon amount. Percent coupons use a percentage number; fixed coupons use store currency.' ),
				array( 'name' => 'usage_limit', 'required' => false, 'desc' => '0 = unlimited' ),
				array( 'name' => 'usage_limit_per_user', 'required' => false, 'desc' => '0 = unlimited per customer' ),
				array( 'name' => 'expiry_date', 'required' => false, 'desc' => 'Y-m-d' ),
				array( 'name' => 'minimum_amount', 'required' => false, 'desc' => 'Minimum cart subtotal required before the coupon applies.' ),
				array( 'name' => 'maximum_amount', 'required' => false, 'desc' => 'Maximum cart subtotal allowed for the coupon to apply.' ),
				array( 'name' => 'individual_use', 'required' => false, 'desc' => 'true = cannot combine with other coupons' ),
				array( 'name' => 'free_shipping', 'required' => false, 'desc' => 'true = coupon also grants free shipping' ),
				array( 'name' => 'exclude_sale_items', 'required' => false, 'desc' => 'true = do not discount sale items' ),
				array( 'name' => 'product_ids', 'required' => false, 'desc' => 'Array of allowed product IDs. Use [] to clear on edit.' ),
				array( 'name' => 'excluded_product_ids', 'required' => false, 'desc' => 'Array of excluded product IDs. Use [] to clear on edit.' ),
				array( 'name' => 'product_categories', 'required' => false, 'desc' => 'Array of allowed product_cat term IDs. Use [] to clear on edit.' ),
				array( 'name' => 'excluded_product_categories', 'required' => false, 'desc' => 'Array of excluded product_cat term IDs. Use [] to clear on edit.' ),
				array( 'name' => 'email_restrictions', 'required' => false, 'desc' => 'Array of allowed billing email restriction strings. Use [] to clear on edit.' ),
				array( 'name' => 'limit', 'required' => false, 'desc' => 'List only. Default: 20, max: 50' ),
				array( 'name' => 'orderby', 'required' => false, 'desc' => 'List only. Sort key such as date or title.' ),
			),
		);
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
