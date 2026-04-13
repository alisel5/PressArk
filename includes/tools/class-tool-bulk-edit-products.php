<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Bulk_Edit_Products extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'bulk_edit_products',
			'description' => 'Update multiple WooCommerce products at once. Bulk results report per-product types and flag variable/grouped/external pricing risks. Do not use plain "price"; choose regular_price, sale_price, or clear_sale. Parent-level edits on variable products are not equivalent to updating all variations.',
			'params'      => array(
				array( 'name' => 'products', 'required' => false, 'desc' => 'Array of objects, each with post_id (int) and a changes object: [{post_id: 10, changes: {description: "New text"}}, {post_id: 11, changes: {short_description: "...", regular_price: "19.99"}}]. Use this when each product gets different values. In WooCommerce writes, do not use plain "price" inside changes. If any target is a variable parent, inspect the returned type/warning fields instead of assuming child variation prices changed.' ),
				array( 'name' => 'scope', 'required' => false, 'desc' => 'Use "all" or "matching" to target many products without enumerating them one by one' ),
				array( 'name' => 'changes', 'required' => false, 'desc' => 'Shared changes for scope-based bulk updates. Use regular_price, sale_price, or clear_sale for explicit price writes, and price_delta or price_adjust_pct for relative price changes. Use sale_price only to set a sale amount; use clear_sale to remove a sale while preserving regular_price. Do not use plain "price". If matching results include variable parents, the bulk result will warn that variation pricing may still need bulk_edit_variations.' ),
				array( 'name' => 'status', 'required' => false, 'desc' => 'Optional product status filter for scope-based bulk updates. Default: publish' ),
				array( 'name' => 'search', 'required' => false, 'desc' => 'Optional search filter for scope-based bulk updates' ),
				array( 'name' => 'limit', 'required' => false, 'desc' => 'Optional batch size cap for scope-based bulk updates. Max: 50' ),
				array( 'name' => 'offset', 'required' => false, 'desc' => 'Optional offset for paginating large scope-based bulk updates' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
