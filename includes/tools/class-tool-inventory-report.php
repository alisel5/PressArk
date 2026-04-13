<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Inventory_Report extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'inventory_report',
			'description' => 'Get inventory status: low stock, out of stock, stock levels.',
			'params'      => array(
				array( 'name' => 'threshold', 'required' => false, 'desc' => 'Low stock threshold (default: 5)' ),
			),
		);
	
	}
}
