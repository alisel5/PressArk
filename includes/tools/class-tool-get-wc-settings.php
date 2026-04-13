<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Wc_Settings extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_wc_settings',
			'description' => 'Get WooCommerce store settings: currency, address, measurements, checkout.',
			'params'      => array(
				array( 'name' => 'section', 'required' => false, 'desc' => 'general|products|accounts (default: general)' ),
			),
		);
	
	}
}
