<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Wc_Emails extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_wc_emails',
			'description' => 'List all WooCommerce email notifications with enabled/disabled status.',
			'params'      => array(),
		);
	
	}
}
