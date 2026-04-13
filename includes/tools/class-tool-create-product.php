<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Create_Product extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'create_product',
			'description' => 'Create a WooCommerce product. Use explicit pricing fields like regular_price or sale_price; do not use plain "price".',
			'params'      => array(
				array( 'name' => 'name', 'required' => true ),
				array( 'name' => 'type', 'required' => false, 'desc' => 'simple|variable|grouped|external (default: simple)' ),
				array( 'name' => 'status', 'required' => false, 'desc' => 'draft|publish|pending|private (default: draft)' ),
				array( 'name' => 'regular_price', 'required' => false, 'desc' => 'Regular/base price. Use this explicitly instead of plain "price".' ),
				array( 'name' => 'sale_price', 'required' => false, 'desc' => 'Optional sale price for the new product.' ),
				array( 'name' => 'description', 'required' => false ),
				array( 'name' => 'short_description', 'required' => false ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
