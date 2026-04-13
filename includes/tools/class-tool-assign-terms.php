<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Assign_Terms extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'assign_terms',
			'description' => 'Assign taxonomy terms to a post. Accepts names or IDs.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'taxonomy', 'required' => true, 'desc' => 'Taxonomy slug' ),
				array( 'name' => 'terms', 'required' => true, 'desc' => 'Array of term names or IDs' ),
				array( 'name' => 'append', 'required' => false, 'desc' => 'true = add to existing, false = replace (default: false)' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
