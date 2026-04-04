<?php
/**
 * PressArk Tool Loader
 *
 * Centralized loading strategy that decides which tools to send for each
 * AI request. v2.3.1: Replaces keyword-based intent matching with
 * conversation-scoped state — starts with base tools, expands via
 * discover_tools + load_tools meta-tools.
 *
 * v3.8.0: Universal high-ROI tools always loaded. Capability map replaces
 * per-tool descriptor dumping in the hot prompt. Native tool-search path
 * for GPT-5.4-class models. Stable canonical ordering for cache reuse.
 *
 * v5.4.0: Adds provider-aware token-budget planning for adaptive preload
 * hydration. Sticky/user-loaded groups remain authoritative; heuristic
 * candidate groups are admitted only when the remaining prompt budget can
 * afford their schema cost plus response headroom.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Loader {

	private PressArk_Tool_Catalog $catalog;

	/**
	 * Minimal groups loaded for every native-tool agent request.
	 *
	 * Keep discovery/meta-tools available, but avoid shipping content write
	 * schemas unless the planner or heuristics explicitly preload them.
	 *
	 * @since 4.3.3
	 */
	const BASE_GROUPS = array(
		'discovery',
	);

	/**
	 * Universal high-ROI tools always sent as schemas.
	 *
	 * The registry is now the primary source for future always-load tools, but
	 * this constant preserves the historic baseline behavior for the three core
	 * content reads that should remain available even when no extra groups fit.
	 *
	 * @since 3.8.0
	 */
	const UNIVERSAL_TOOLS = array(
		'read_content',
		'search_content',
		'list_posts',
	);

	public function __construct( ?PressArk_Tool_Catalog $catalog = null ) {
		$this->catalog = $catalog ?? PressArk_Tool_Catalog::instance();
	}

	/**
	 * Resolve which tools to load for this request.
	 *
	 * @param string   $message       The user's current message (kept for compat).
	 * @param array    $conversation  Conversation history (kept for compat).
	 * @param string   $tier          User's tier (reserved for future limits).
	 * @param string[] $loaded_groups Groups already loaded in this conversation.
	 * @param array    $options       Optional adaptive hydration options.
	 * @return array
	 */
	public function resolve(
		string $message,
		array $conversation,
		string $tier,
		array $loaded_groups = array(),
		array $options = array()
	): array {
		unset( $message, $conversation, $tier );

		$required_groups = $this->normalize_groups(
			array_merge( self::BASE_GROUPS, $loaded_groups )
		);
		$candidate_groups = array_values( array_diff(
			$this->normalize_groups( (array) ( $options['candidate_groups'] ?? array() ) ),
			$required_groups
		) );

		$tool_set = $this->build_tool_set( $required_groups, 'filtered' );

		$budget_manager = $options['budget_manager'] ?? null;
		if ( $budget_manager instanceof PressArk_Token_Budget_Manager && ! empty( $candidate_groups ) ) {
			$group_costs = array();
			foreach ( $candidate_groups as $group ) {
				$group_costs[ $group ] = $this->estimate_group_schema_cost(
					$group,
					$tool_set['tool_names'],
					$budget_manager
				);
			}

			$base_ledger = $budget_manager->build_request_ledger( array(
				'dynamic_prompt'      => (string) ( $options['dynamic_prompt'] ?? '' ),
				'loaded_tool_schemas' => $tool_set['schemas'],
				'conversation'        => (array) ( $options['conversation_messages'] ?? array() ),
				'tool_results'        => (array) ( $options['tool_results'] ?? array() ),
				'deferred_candidates' => array(),
			) );
			$hydration  = $budget_manager->plan_group_hydration(
				$required_groups,
				$candidate_groups,
				$group_costs,
				$base_ledger
			);
			$tool_set   = $this->build_tool_set(
				array_merge( $required_groups, (array) ( $hydration['selected_groups'] ?? array() ) ),
				'filtered'
			);
			$tool_set['group_costs']     = $group_costs;
			$tool_set['hydration_plan']  = $hydration;
			$tool_set['deferred_groups'] = (array) ( $hydration['deferred_groups'] ?? array() );
		}

		return $this->finalize_tool_set_budget( $tool_set, $options );
	}

	/**
	 * Resolve for models with native tool search (GPT-5.4-class).
	 *
	 * Skips local discovery scaffolding and sends all tool schemas directly.
	 *
	 * @since 3.8.0
	 *
	 * @param string $tier User's tier.
	 * @return array Same shape as resolve().
	 */
	public function resolve_native_search( string $tier ): array {
		unset( $tier );

		$has_woo       = class_exists( 'WooCommerce' );
		$has_elementor = class_exists( '\\Elementor\\Plugin' );
		$all_tools     = PressArk_Tools::get_all( $has_woo, $has_elementor );

		$schemas = array();
		foreach ( $all_tools as $tool ) {
			$schemas[] = PressArk_Tools::tool_to_schema( $tool );
		}

		usort( $schemas, function ( $a, $b ) {
			return strcmp( $a['function']['name'] ?? '', $b['function']['name'] ?? '' );
		} );

		$tool_names = array();
		foreach ( $schemas as $schema ) {
			$tool_names[] = $schema['function']['name'] ?? '';
		}

		$groups = PressArk_Operation_Registry::group_names();

		return array(
			'schemas'               => $schemas,
			'descriptors'           => '',
			'capability_map'        => PressArk_Capability_Bridge::get_context_resources( $groups, 'full' ),
			'capability_maps'       => array(
				'full'    => PressArk_Capability_Bridge::get_context_resources( $groups, 'full' ),
				'compact' => PressArk_Capability_Bridge::get_context_resources( $groups, 'compact' ),
				'minimal' => PressArk_Capability_Bridge::get_context_resources( $groups, 'minimal' ),
			),
			'capability_map_variant' => 'full',
			'groups'                => $groups,
			'strategy'              => 'native_search',
			'tool_count'            => count( $schemas ),
			'tool_names'            => $tool_names,
			'deferred_groups'       => array(),
			'hydration_plan'        => array(),
			'budget'                => array(),
		);
	}

	/**
	 * Expand the current tool set with an additional group.
	 *
	 * Explicit load requests stay authoritative and bypass adaptive trimming.
	 *
	 * @param array  $current Current result from resolve() or previous expand().
	 * @param string $group   Group name to add.
	 * @return array Updated result.
	 */
	public function expand( array $current, string $group ): array {
		$groups = $this->normalize_groups( (array) ( $current['groups'] ?? array() ) );

		if ( in_array( $group, $groups, true ) || ! PressArk_Operation_Registry::is_valid_group( $group ) ) {
			return $current;
		}

		$groups[]  = $group;
		$tool_set  = $this->build_tool_set( $groups, 'filtered' );
		$deferred  = array_values( array_filter(
			(array) ( $current['deferred_groups'] ?? array() ),
			static function ( $candidate ) use ( $group ): bool {
				return $group !== (string) ( $candidate['group'] ?? '' );
			}
		) );
		$tool_set['deferred_groups'] = $deferred;

		return $tool_set;
	}

	/**
	 * Expand the current tool set by loading specific tools (by name).
	 * Resolves each tool to its parent group and loads the full group.
	 *
	 * @since 2.3.1
	 *
	 * @param array    $current    Current result from resolve() or previous expand().
	 * @param string[] $tool_names Specific tool names to add.
	 * @return array Updated result.
	 */
	public function expand_tools( array $current, array $tool_names ): array {
		$groups          = $this->normalize_groups( (array) ( $current['groups'] ?? array() ) );
		$has_woo         = class_exists( 'WooCommerce' );
		$has_elementor   = class_exists( '\\Elementor\\Plugin' );
		$changed         = false;

		foreach ( $tool_names as $name ) {
			$group = $this->catalog->find_group_for_tool( $name );
			if ( ! $group || in_array( $group, $groups, true ) ) {
				continue;
			}
			if ( 'woocommerce' === $group && ! $has_woo ) {
				continue;
			}
			if ( 'elementor' === $group && ! $has_elementor ) {
				continue;
			}
			$groups[] = $group;
			$changed  = true;
		}

		if ( ! $changed ) {
			return $current;
		}

		$tool_set = $this->build_tool_set( $groups, 'filtered' );
		$tool_set['deferred_groups'] = array_values( array_filter(
			(array) ( $current['deferred_groups'] ?? array() ),
			static function ( $candidate ) use ( $groups ): bool {
				return ! in_array( (string) ( $candidate['group'] ?? '' ), $groups, true );
			}
		) );

		return $tool_set;
	}

	/**
	 * Load all tools (bypass filtering).
	 *
	 * @return array Same shape as resolve() but with all tools loaded.
	 */
	public function resolve_full(): array {
		$schemas = ( new PressArk_Tools() )->get_all_tools();

		foreach ( $this->catalog->get_meta_tools_schemas() as $meta_schema ) {
			$schemas[] = $meta_schema;
		}

		usort( $schemas, function ( $a, $b ) {
			return strcmp( $a['function']['name'] ?? '', $b['function']['name'] ?? '' );
		} );

		$tool_names = array();
		foreach ( $schemas as $schema ) {
			$tool_names[] = $schema['function']['name'] ?? '';
		}

		return array(
			'schemas'                => $schemas,
			'descriptors'            => '',
			'capability_map'         => '',
			'capability_maps'        => array(),
			'capability_map_variant' => '',
			'groups'                 => PressArk_Operation_Registry::group_names(),
			'strategy'               => 'full',
			'tool_count'             => count( $schemas ),
			'tool_names'             => $tool_names,
			'deferred_groups'        => array(),
			'hydration_plan'         => array(),
			'budget'                 => array(),
		);
	}

	/**
	 * Build a canonical tool set for a resolved group list.
	 *
	 * @param string[] $groups   Loaded groups.
	 * @param string   $strategy Strategy label.
	 * @return array
	 */
	private function build_tool_set( array $groups, string $strategy ): array {
		$groups     = $this->normalize_groups( $groups );
		$tool_names = array_values( array_unique( array_merge(
			$this->catalog->get_tool_names_for_groups( $groups ),
			$this->get_always_load_tool_names()
		) ) );
		$schemas    = $this->catalog->get_schemas( $tool_names );
		$maps       = $this->catalog->get_capability_maps( $groups );

		return array(
			'schemas'                => $schemas,
			'descriptors'            => '',
			'capability_map'         => $maps['full'] ?? '',
			'capability_maps'        => $maps,
			'capability_map_variant' => 'full',
			'groups'                 => $groups,
			'strategy'               => $strategy,
			'tool_count'             => count( $schemas ),
			'tool_names'             => $tool_names,
			'deferred_groups'        => array(),
			'hydration_plan'         => array(),
			'budget'                 => array(),
		);
	}

	/**
	 * Apply budget-aware capability support selection when available.
	 *
	 * @param array $tool_set Tool set built by build_tool_set().
	 * @param array $options  Optional context passed from resolve().
	 * @return array
	 */
	private function finalize_tool_set_budget( array $tool_set, array $options = array() ): array {
		$budget_manager = $options['budget_manager'] ?? null;
		if ( ! $budget_manager instanceof PressArk_Token_Budget_Manager ) {
			return $tool_set;
		}

		$capability_maps = (array) ( $tool_set['capability_maps'] ?? array() );
		$base_ledger     = $budget_manager->build_request_ledger( array(
			'dynamic_prompt'      => (string) ( $options['dynamic_prompt'] ?? '' ),
			'loaded_tool_schemas' => (array) ( $tool_set['schemas'] ?? array() ),
			'conversation'        => (array) ( $options['conversation_messages'] ?? array() ),
			'tool_results'        => (array) ( $options['tool_results'] ?? array() ),
			'deferred_candidates' => (array) ( $tool_set['deferred_groups'] ?? array() ),
		) );
		$variant         = $budget_manager->choose_support_variant( $capability_maps, $base_ledger );
		$capability_map  = '' !== $variant ? (string) ( $capability_maps[ $variant ] ?? '' ) : '';
		$dynamic_prompt  = (string) ( $options['dynamic_prompt'] ?? '' );
		if ( '' !== $capability_map ) {
			$dynamic_prompt = '' !== trim( $dynamic_prompt )
				? trim( $dynamic_prompt ) . "\n\n" . $capability_map
				: $capability_map;
		}

		$tool_set['capability_map_variant'] = $variant;
		$tool_set['capability_map']         = $capability_map;
		$tool_set['budget']                 = $budget_manager->build_request_ledger( array(
			'dynamic_prompt'      => $dynamic_prompt,
			'loaded_tool_schemas' => (array) ( $tool_set['schemas'] ?? array() ),
			'conversation'        => (array) ( $options['conversation_messages'] ?? array() ),
			'tool_results'        => (array) ( $options['tool_results'] ?? array() ),
			'deferred_candidates' => (array) ( $tool_set['deferred_groups'] ?? array() ),
		) );

		return $tool_set;
	}

	/**
	 * Estimate the incremental schema cost of loading a candidate group.
	 *
	 * @param string                        $group              Candidate group name.
	 * @param string[]                      $current_tool_names Tool names already loaded.
	 * @param PressArk_Token_Budget_Manager $budget_manager     Budget estimator.
	 * @return int
	 */
	private function estimate_group_schema_cost(
		string $group,
		array $current_tool_names,
		PressArk_Token_Budget_Manager $budget_manager
	): int {
		$group_tool_names = array_values( array_diff(
			$this->catalog->get_tool_names_for_groups( array( $group ) ),
			$current_tool_names
		) );

		if ( empty( $group_tool_names ) ) {
			return 0;
		}

		return $budget_manager->estimate_schema_tokens(
			$this->catalog->get_schemas( $group_tool_names )
		);
	}

	/**
	 * Get always-load tool names using the registry as the extension point.
	 *
	 * @return string[]
	 */
	private function get_always_load_tool_names(): array {
		$always = self::UNIVERSAL_TOOLS;

		foreach ( PressArk_Operation_Registry::all() as $op ) {
			if ( ! $op->is_always_load() ) {
				continue;
			}
			if ( in_array( $op->name, array( 'discover_tools', 'load_tools', 'load_tool_group' ), true ) ) {
				continue;
			}
			$always[] = $op->name;
		}

		return array_values( array_unique( $always ) );
	}

	/**
	 * Normalize and plugin-filter a group list.
	 *
	 * @param string[] $groups Group names.
	 * @return string[]
	 */
	private function normalize_groups( array $groups ): array {
		$normalized = array();
		foreach ( $groups as $group ) {
			$group = sanitize_key( (string) $group );
			if ( '' === $group || ! PressArk_Operation_Registry::is_valid_group( $group ) ) {
				continue;
			}
			if ( 'woocommerce' === $group && ! class_exists( 'WooCommerce' ) ) {
				continue;
			}
			if ( 'elementor' === $group && ! class_exists( '\\Elementor\\Plugin' ) ) {
				continue;
			}
			$normalized[] = $group;
		}

		return array_values( array_unique( $normalized ) );
	}
}
