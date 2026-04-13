<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Revenue_Report extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'revenue_report',
			'description' => 'Revenue report with period-over-period comparison via WC Analytics.',
			'params'      => array(
				array( 'name' => 'days', 'required' => false, 'desc' => 'Default: 30, max: 365' ),
				array( 'name' => 'interval', 'required' => false, 'desc' => 'day|week|month (default: day)' ),
				array( 'name' => 'compare', 'required' => false, 'desc' => 'false to skip comparison (default: true)' ),
			),
		);
	
	}
}
