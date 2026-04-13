<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Edit_Variation extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'edit_variation',
			'description' => 'Edit a product variation: pricing, stock, status. Do not use plain "price"; choose regular_price, sale_price, or clear_sale. Use clear_sale as the canonical way to remove a sale.',
			'params'      => array(
				array( 'name' => 'variation_id', 'required' => true ),
				array( 'name' => 'regular_price', 'required' => false, 'desc' => 'Variation regular/base price. Use this explicitly instead of plain "price".' ),
				array( 'name' => 'sale_price', 'required' => false, 'desc' => 'Variation sale price amount. Do not use this field to remove a sale.' ),
				array( 'name' => 'clear_sale', 'required' => false, 'desc' => 'Canonical sale-removal path. True clears the sale price and sale schedule while preserving the regular price.' ),
				array( 'name' => 'stock_quantity', 'required' => false, 'desc' => 'Absolute stock quantity; plain "stock" also maps here' ),
				array( 'name' => 'stock_status', 'required' => false, 'desc' => 'instock|outofstock|onbackorder' ),
				array( 'name' => 'status', 'required' => false, 'desc' => 'publish|private' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
