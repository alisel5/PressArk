<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Wc_Status extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_wc_status',
			'description' => 'Full WooCommerce system status: environment, DB health, plugins, template overrides, HPOS.',
			'params'      => array(),
		);
	
	}
}
