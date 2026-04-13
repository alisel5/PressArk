<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Edit_Product extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'edit_product',
			'description' => 'Update a WooCommerce product. Supports 30+ fields via WC object model. Do not use plain "price"; choose regular_price, sale_price, or clear_sale. Use clear_sale as the canonical way to remove a sale.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'changes', 'required' => true, 'desc' => 'Fields: name, description, short_description, regular_price, sale_price, clear_sale, price_delta, price_adjust_pct, sku, stock_quantity (plain "stock" maps here), stock_adjust, status, category_ids, tag_ids, image_id, weight, dimensions, featured, virtual, and more. Use sale_price only to set a sale amount; use clear_sale to remove a sale while preserving regular_price. Do not use plain "price" for WooCommerce writes.' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
