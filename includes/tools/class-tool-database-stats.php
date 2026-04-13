<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Database_Stats extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'database_stats',
			'description' => 'Database statistics: total size, table sizes, row counts, large tables.',
			'params'      => array(),
		);
	
	}
}
