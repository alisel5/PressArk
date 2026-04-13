<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Category_Report extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'category_report',
			'description' => 'Sales breakdown by product category: items sold, revenue, orders.',
			'params'      => array(
				array( 'name' => 'days', 'required' => false, 'desc' => 'Default: 30, max: 365' ),
			),
		);
	
	}
}
