<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Variations extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_variations',
			'description' => 'List all variations for a variable product: attributes, price, stock, status.',
			'params'      => array(
				array( 'name' => 'product_id', 'required' => true ),
			),
		);
	
	}
}
