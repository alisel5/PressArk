<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Tax_Settings extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_tax_settings',
			'description' => 'Get WooCommerce tax configuration: rates, calculation, display options.',
			'params'      => array(),
		);
	
	}
}
