<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Generate_Bulk_Meta extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'generate_bulk_meta',
			'description' => 'Generate SEO meta for multiple pages. Apply results via fix_seo.',
			'params'      => array(
				array( 'name' => 'post_ids', 'required' => false, 'desc' => 'Omit for all pages missing meta' ),
				array( 'name' => 'style', 'required' => false, 'desc' => 'descriptive|action-oriented|question-based|benefit-focused (default: descriptive)' ),
			),
		);
	
	}
}
