<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Sales_Summary extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'sales_summary',
			'description' => 'Sales summary: revenue, order count, average order value for a date range.',
			'params'      => array(
				array( 'name' => 'period', 'required' => false, 'desc' => 'today|week|month|year|custom (default: month)' ),
				array( 'name' => 'date_from', 'required' => false, 'desc' => 'Y-m-d for custom period' ),
				array( 'name' => 'date_to', 'required' => false, 'desc' => 'Y-m-d for custom period' ),
			),
		);
	
	}
}
