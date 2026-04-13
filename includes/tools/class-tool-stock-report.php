<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Stock_Report extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'stock_report',
			'description' => 'Inventory overview grouped by stock status with valuation. HPOS-safe.',
			'params'      => array(
				array( 'name' => 'status', 'required' => false, 'desc' => 'outofstock|lowstock|instock|all (default: all)' ),
				array( 'name' => 'limit', 'required' => false, 'desc' => 'Default: 30, max: 100' ),
			),
		);
	
	}
}
