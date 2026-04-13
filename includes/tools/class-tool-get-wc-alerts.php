<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Wc_Alerts extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_wc_alerts',
			'description' => 'WooCommerce alerts: low stock, out of stock, failed/cancelled orders.',
			'params'      => array(
				array( 'name' => 'peek', 'required' => false, 'desc' => 'true = view without marking as read' ),
			),
		);
	
	}
}
