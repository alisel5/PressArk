<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Product extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_product',
			'description' => 'Get full WooCommerce product data: permalink, descriptions, price, stock, categories, attributes, images, SKU. Use this when a lighter product read is not rich enough for grounded content or a valid CTA.',
			'params'      => array(
				array( 'name' => 'product_id', 'required' => true, 'desc' => 'WooCommerce product ID' ),
			),
		);
	
	}
}
