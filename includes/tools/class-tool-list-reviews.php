<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Reviews extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_reviews',
			'description' => 'List WooCommerce product reviews with filters.',
			'params'      => array(
				array( 'name' => 'product_id', 'required' => false ),
				array( 'name' => 'status', 'required' => false, 'desc' => 'approved|pending|spam|trash|all (default: all)' ),
				array( 'name' => 'rating', 'required' => false, 'desc' => '1-5' ),
				array( 'name' => 'limit', 'required' => false, 'desc' => 'Default: 20' ),
			),
		);
	
	}
}
