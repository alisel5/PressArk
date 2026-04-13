<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Customer_Insights extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'customer_insights',
			'description' => 'Customer RFM segmentation: active, cooling, at-risk, churned with revenue data.',
			'params'      => array(),
		);
	
	}
}
