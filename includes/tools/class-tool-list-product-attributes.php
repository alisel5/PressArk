<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Product_Attributes extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_product_attributes',
			'description' => 'List global WooCommerce product attributes and their terms.',
			'params'      => array(),
		);
	
	}
}
