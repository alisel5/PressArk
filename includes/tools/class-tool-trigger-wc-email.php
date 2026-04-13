<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Trigger_Wc_Email extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'trigger_wc_email',
			'description' => 'Trigger a WooCommerce email using WC templates. Omit email_type to list all types.',
			'params'      => array(
				array( 'name' => 'email_type', 'required' => false, 'desc' => 'WC email class name. Omit to list all.' ),
				array( 'name' => 'order_id', 'required' => false, 'desc' => 'Required for order-related emails' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
