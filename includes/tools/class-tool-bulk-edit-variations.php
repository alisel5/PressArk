<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Bulk_Edit_Variations extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'bulk_edit_variations',
			'description' => 'Bulk edit all variations of a variable product. Do not use plain "price"; choose regular_price, sale_price, or clear_sale. Use clear_sale as the canonical way to remove a sale.',
			'params'      => array(
				array( 'name' => 'product_id', 'required' => true ),
				array( 'name' => 'changes', 'required' => true, 'desc' => '{price_adjust_pct, regular_price, sale_price, clear_sale, sale_from, sale_to, stock_status, manage_stock, status}. Use sale_price only to set a sale amount; use clear_sale to remove a sale while preserving regular_price and clearing sale schedule fields. Do not use plain "price"; choose regular_price, sale_price, or clear_sale.' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
