<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Orders extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_orders',
			'description' => 'List WooCommerce orders with filters. Supports pagination via offset. Returns _pagination metadata.',
			'params'      => array(
				array( 'name' => 'status', 'required' => false, 'desc' => 'pending|processing|on-hold|completed|cancelled|refunded|failed|any (default: any)' ),
				array( 'name' => 'count', 'required' => false, 'desc' => 'Max results (default: 20, max: 50)' ),
				array( 'name' => 'offset', 'required' => false, 'desc' => 'Skip first N results for pagination (default: 0)' ),
				array( 'name' => 'date_after', 'required' => false, 'desc' => 'Y-m-d' ),
				array( 'name' => 'date_before', 'required' => false, 'desc' => 'Y-m-d' ),
				array( 'name' => 'search', 'required' => false ),
				array( 'name' => 'customer_id', 'required' => false ),
				array( 'name' => 'customer_email', 'required' => false ),
				array( 'name' => 'payment_method', 'required' => false, 'desc' => 'Gateway ID' ),
			),
		);
	
	}
}
