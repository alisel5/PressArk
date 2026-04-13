<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Check_Crawlability extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'check_crawlability',
			'description' => 'Check search engine crawlability: robots.txt, visibility, SSL, sitemap.',
			'type'        => 'read',
			'params'      => array(),
		);
	
	}
}
