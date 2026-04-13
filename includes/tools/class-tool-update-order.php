<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Update_Order extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'update_order',
			'description' => 'Update order status or add a note.',
			'params'      => array(
				array( 'name' => 'order_id', 'required' => true ),
				array( 'name' => 'status', 'required' => false, 'desc' => 'New order status' ),
				array( 'name' => 'note', 'required' => false ),
				array( 'name' => 'customer_note', 'required' => false, 'desc' => 'true = note visible to customer (default: false)' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
