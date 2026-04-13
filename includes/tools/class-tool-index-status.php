<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Index_Status extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'index_status',
			'description' => 'Check content index status: pages indexed, last sync, total words.',
			'params'      => array(),
		);
	
	}
}
