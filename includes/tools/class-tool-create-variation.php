<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Create_Variation extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'create_variation',
			'description' => 'Create a variation on a variable product. Auto-syncs parent price range. Use explicit pricing fields like regular_price or sale_price; do not use plain "price".',
			'params'      => array(
				array( 'name' => 'product_id', 'required' => true, 'desc' => 'Parent variable product ID' ),
				array( 'name' => 'attributes', 'required' => true, 'desc' => '{attribute: value} pairs' ),
				array( 'name' => 'regular_price', 'required' => false, 'desc' => 'Variation regular/base price. Use this explicitly instead of plain "price".' ),
				array( 'name' => 'sale_price', 'required' => false, 'desc' => 'Optional variation sale price.' ),
				array( 'name' => 'sku', 'required' => false ),
				array( 'name' => 'stock_quantity', 'required' => false ),
				array( 'name' => 'manage_stock', 'required' => false ),
				array( 'name' => 'weight', 'required' => false ),
				array( 'name' => 'status', 'required' => false, 'desc' => 'Default: publish' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
