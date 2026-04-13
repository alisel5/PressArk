<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Export_Report extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'export_report',
			'description' => 'Generate an exportable HTML report. Returns download link.',
			'params'      => array(
				array( 'name' => 'report_type', 'required' => true, 'desc' => 'seo|security|site_overview|woocommerce' ),
				array( 'name' => 'include_recommendations', 'required' => false, 'desc' => 'Default: true' ),
			),
		);
	
	}
}
