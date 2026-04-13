<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Customers extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_customers',
			'description' => 'List WooCommerce customers with order history and total spent. Supports pagination via offset. Returns _pagination metadata.',
			'params'      => array(
				array( 'name' => 'search', 'required' => false, 'desc' => 'Search by name or email' ),
				array( 'name' => 'orderby', 'required' => false, 'desc' => 'date_registered|total_spent|order_count (default: total_spent)' ),
				array( 'name' => 'limit', 'required' => false, 'desc' => 'Max results (default: 20, max: 100)' ),
				array( 'name' => 'offset', 'required' => false, 'desc' => 'Skip first N results for pagination (default: 0)' ),
			),
		);
	
	}
}
