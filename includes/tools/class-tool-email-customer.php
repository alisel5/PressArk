<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Email_Customer extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'email_customer',
			'description' => 'Send a personalized email to a WooCommerce customer.',
			'params'      => array(
				array( 'name' => 'customer_id', 'required' => true ),
				array( 'name' => 'subject', 'required' => true ),
				array( 'name' => 'body', 'required' => true, 'desc' => 'HTML supported' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
