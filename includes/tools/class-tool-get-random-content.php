<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Random_Content extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_random_content',
			'description' => 'Pick one random post, page, or product for writing, auditing, or structure analysis. Modes: light (compact summary), structured (headings + section summaries). For product-led content, use post_type="product"; if the result is not rich enough for grounded details, follow with get_product.',
			'params'      => array(
				array( 'name' => 'post_type', 'required' => false, 'desc' => 'post|page|product|any (default: any)' ),
				array( 'name' => 'status', 'required' => false, 'desc' => 'publish|draft|private|any (default: publish)' ),
				array( 'name' => 'mode', 'required' => false, 'desc' => 'light|structured (default: light)' ),
				array( 'name' => 'exclude_ids', 'required' => false, 'desc' => 'Array of post IDs to skip' ),
			),
		);
	
	}
}
