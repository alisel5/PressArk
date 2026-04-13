<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Create_Refund extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'create_refund',
			'description' => 'Issue a full or partial refund for a WooCommerce order.',
			'params'      => array(
				array( 'name' => 'order_id', 'required' => true ),
				array( 'name' => 'amount', 'required' => false, 'desc' => 'Omit for full refund' ),
				array( 'name' => 'reason', 'required' => false ),
				array( 'name' => 'process_payment', 'required' => false, 'desc' => 'true = refund via gateway (default: false, WC record only)' ),
				array( 'name' => 'restock', 'required' => false, 'desc' => 'Default: true' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
