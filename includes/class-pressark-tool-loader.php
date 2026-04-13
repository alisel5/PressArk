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
		unset( $message, $conversation );
		return $this->resolve_group_scoped( $tier, $loaded_groups, $options, 'filtered' );
	}

	/**
	 * Resolve for models with native tool search (GPT-5.4-class).
	 *
	 * Keeps the provider-facing schema set tight while preserving a richer
	 * internal distinction between visible, searchable, discovered, loaded,
	 * and blocked tools for operator and future chat-side run details.
	 *
	 * @since 3.8.0
	 *
	 * @param string $tier User's tier.
	 * @return array Same shape as resolve().
	 */
	public function resolve_native_search( string $tier, array $options = array() ): array {
		$loaded_groups = (array) ( $options['loaded_groups'] ?? array() );
		unset( $options['loaded_groups'] );

		return $this->resolve_group_scoped( $tier, $loaded_groups, $options, 'native_search' );
	}

	/**
	 * Resolve a request-scoped tool set while preserving the richer capability
	 * state needed for deferred loading and later run inspection.
	 *
	 * @param string   $tier          User tier.
	 * @param string[] $loaded_groups Groups that should start loaded.
	 * @param array    $options       Loader options.
	 * @param string   $strategy      Strategy label.
	 * @return array
	 */
	private function resolve_group_scoped(
		string $tier,
		array $loaded_groups = array(),
		array $options = array(),
		string $strategy = 'filtered'
	): array {
		$options['tier'] = $tier;
		$sticky_groups   = $this->normalize_groups( $loaded_groups );
		$options['sticky_groups'] = $sticky_groups;

		$required_groups = $this->normalize_groups(
			array_merge( self::BASE_GROUPS, $sticky_groups )
		);
		$candidate_groups = array_values( array_diff(
			$this->normalize_groups( (array) ( $options['candidate_groups'] ?? array() ) ),
			$required_groups
		) );

		$tool_set = $this->build_tool_set( $required_groups, $strategy, $options );

		$budget_manager = $options['budget_manager'] ?? null;
		if ( $budget_manager instanceof PressArk_Token_Budget_Manager && ! empty( $candidate_groups ) ) {
			$group_costs = array();
			foreach ( $candidate_groups as $group ) {
				$group_costs[ $group ] = $this->estimate_group_schema_cost(
					$group,
					$tool_set['tool_names'],
					$budget_manager,
					$options
				);
			}

			$base_ledger = $budget_manager->build_request_ledger( array(
				'dynamic_prompt'      => (string) ( $options['dynamic_prompt'] ?? '' ),
				'loaded_tool_schemas' => $tool_set['schemas'],
				'conversation'        => (array) ( $options['conversation_messages'] ?? array() ),
				'tool_results'        => (array) ( $options['tool_results'] ?? array() ),
				'deferred_candidates' => (array) ( $tool_set['deferred_candidates'] ?? array() ),
			) );
			$hydration  = $budget_manager->plan_group_hydration(
				$required_groups,
				$candidate_groups,
				$group_costs,
				$base_ledger
			);
			$tool_set   = $this->build_tool_set(
				array_merge( $required_groups, (array) ( $hydration['selected_groups'] ?? array() ) ),
				$strategy,
				$options
			);
			$tool_set['group_costs']     = $group_costs;
			$tool_set['hydration_plan']  = $hydration;
			$tool_set['deferred_groups'] = (array) ( $hydration['deferred_groups'] ?? array() );
			$tool_set['deferred_candidates'] = $this->merge_deferred_candidate_rows(
				(array) ( $tool_set['deferred_candidates'] ?? array() ),
				(array) ( $tool_set['deferred_groups'] ?? array() )
			);
		}

		return $this->finalize_tool_set_budget( $tool_set, $options );
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
	public function expand( array $current, string $group, array $options = array() ): array {
		$groups = $this->normalize_groups( (array) ( $current['groups'] ?? array() ) );
		$current_tool_names = $this->normalize_tool_names( (array) ( $current['tool_names'] ?? array() ) );

		if ( ! PressArk_Operation_Registry::is_valid_group( $group ) ) {
			return $current;
		}

		$group_already_loaded = in_array( $group, $groups, true );
		if ( ! $group_already_loaded ) {
			$groups[] = $group;
		}

		if ( $group_already_loaded ) {
			$group_tool_names = $this->catalog->get_tool_names_for_groups( array( $group ) );
			$missing_deferred = array_values( array_filter(
				$this->normalize_tool_names( $group_tool_names ),
				static function ( string $tool_name ) use ( $current_tool_names ): bool {
					return PressArk_Operation_Registry::is_deferred_tool( $tool_name )
						&& ! in_array( $tool_name, $current_tool_names, true );
				}
			) );
			if ( empty( $missing_deferred ) ) {
				return $current;
			}
		}

		$tool_set  = $this->build_tool_set(
			$groups,
			(string) ( $current['strategy'] ?? 'filtered' ),
			array_merge(
				$this->merge_state_options( $options, $current ),
				array(
					'sticky_groups' => $groups,
				)
			)
		);
		$deferred  = array_values( array_filter(
			(array) ( $current['deferred_groups'] ?? array() ),
			static function ( $candidate ) use ( $group ): bool {
				return $group !== (string) ( $candidate['group'] ?? '' );
			}
		) );
		$tool_set['deferred_groups']     = $deferred;
		$tool_set['deferred_candidates'] = $this->merge_deferred_candidate_rows(
			(array) ( $tool_set['deferred_candidates'] ?? array() ),
			$deferred
		);

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
	public function expand_tools( array $current, array $tool_names, array $options = array() ): array {
		$groups             = $this->normalize_groups( (array) ( $current['groups'] ?? array() ) );
		$requested_tool_names = $this->normalize_tool_names( $tool_names );
		$current_tool_lookup = array_flip( $this->normalize_tool_names( (array) ( $current['tool_names'] ?? array() ) ) );
		$has_woo            = class_exists( 'WooCommerce' );
		$has_elementor      = class_exists( '\\Elementor\\Plugin' );
		$changed            = false;

		foreach ( $requested_tool_names as $name ) {
			$group = $this->catalog->find_group_for_tool( $name );
			if ( ! $group ) {
				continue;
			}
			if ( 'woocommerce' === $group && ! $has_woo ) {
				continue;
			}
			if ( 'elementor' === $group && ! $has_elementor ) {
				continue;
			}
			if ( ! isset( $current_tool_lookup[ $name ] ) ) {
				$changed = true;
			}
			if ( in_array( $group, $groups, true ) ) {
				continue;
			}
			$groups[] = $group;
			$changed  = true;
		}

		if ( ! $changed ) {
			return $current;
		}

		$tool_set = $this->build_tool_set(
			$groups,
			(string) ( $current['strategy'] ?? 'filtered' ),
			array_merge(
				$this->merge_state_options( $options, $current ),
				array(
					'sticky_groups'      => $groups,
					'explicit_tool_names' => $requested_tool_names,
				)
			)
		);
		$tool_set['deferred_groups'] = array_values( array_filter(
			(array) ( $current['deferred_groups'] ?? array() ),
			static function ( $candidate ) use ( $groups ): bool {
				return ! in_array( (string) ( $candidate['group'] ?? '' ), $groups, true );
			}
		) );
		$tool_set['deferred_candidates'] = $this->merge_deferred_candidate_rows(
			(array) ( $tool_set['deferred_candidates'] ?? array() ),
			(array) ( $tool_set['deferred_groups'] ?? array() )
		);

		return $tool_set;
	}

	/**
	 * Mark a set of tools as discovered without hydrating their schemas yet.
	 *
	 * The current request keeps them distinct from both the provider-loaded
	 * subset and the wider searchable pool so run details can explain what the
	 * harness surfaced versus what it actually hydrated.
	 *
	 * @param array    $current    Current tool-set payload.
	 * @param string[] $tool_names Discovered tool names.
	 * @param array    $options    Optional loader context.
	 * @return array
	 */
	public function mark_discovered_tools( array $current, array $tool_names, array $options = array() ): array {
		$history = array_values( array_unique( array_merge(
			(array) ( $current['discovered_tool_names'] ?? array() ),
			$this->normalize_tool_names( $tool_names )
		) ) );

		$current['discovered_tool_names'] = $history;
		$current['tool_state']            = $this->build_tool_state(
			(array) ( $current['tool_names'] ?? array() ),
			(array) ( $current['requested_groups'] ?? $current['groups'] ?? array() ),
			(array) ( $current['groups'] ?? array() ),
			(string) ( $current['strategy'] ?? 'filtered' ),
			$this->merge_state_options(
				array_merge(
					$options,
					array(
						'discovered_tool_names' => $history,
					)
				),
				$current
			),
			(array) ( $current['permission_surface'] ?? array() )
		);

		return $current;
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
		$descriptors = PressArk_Tools::get_prompt_snippets( $tool_names );

		return array(
			'schemas'                => $schemas,
			'descriptors'            => $descriptors,
			'capability_map'         => '',
			'capability_maps'        => array(),
			'capability_map_variant' => '',
			'groups'                 => PressArk_Operation_Registry::group_names(),
			'requested_groups'       => PressArk_Operation_Registry::group_names(),
			'strategy'               => 'full',
			'tool_count'             => count( $schemas ),
			'tool_names'             => $tool_names,
			'discovered_tool_names'  => array(),
			'effective_visible_tools' => $tool_names,
			'permission_surface'     => array(),
			'tool_state'             => $this->build_tool_state(
				$tool_names,
				PressArk_Operation_Registry::group_names(),
				PressArk_Operation_Registry::group_names(),
				'full',
				array(),
				array()
			),
			'deferred_groups'        => array(),
			'deferred_candidates'    => array(),
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
	private function build_tool_set( array $groups, string $strategy, array $options = array() ): array {
		$groups                   = $this->normalize_groups( $groups );
		$sticky_groups            = $this->normalize_groups( (array) ( $options['sticky_groups'] ?? array() ) );
		$explicit_tool_names      = $this->normalize_tool_names( (array) ( $options['explicit_tool_names'] ?? array() ) );
		$already_loaded_tool_names = $this->normalize_tool_names( (array) ( $options['already_loaded_tool_names'] ?? array() ) );
		$candidate_tool_names     = array_values( array_unique( array_merge(
			$this->catalog->get_tool_names_for_groups( $groups ),
			$this->catalog->get_tool_names_for_groups( $sticky_groups ),
			$this->get_always_load_tool_names(),
			$explicit_tool_names,
			$already_loaded_tool_names
		) ) );
		$loading_plan             = $this->classify_tool_names_by_loading_intent(
			$candidate_tool_names,
			$sticky_groups,
			$explicit_tool_names,
			$already_loaded_tool_names,
			$options
		);
		$candidate_tool_names     = (array) ( $loading_plan['selected_tool_names'] ?? array() );
		$effective_groups         = $groups;
		$permission_surface       = array();

		if ( class_exists( 'PressArk_Permission_Service' ) ) {
			$visibility           = PressArk_Permission_Service::evaluate_tool_set(
				$candidate_tool_names,
				$this->permission_context( $options ),
				$this->permission_meta( $options )
			);
			$candidate_tool_names = $visibility['visible_tool_names'];
			$effective_groups     = $this->visible_groups_from_tool_names( $groups, $candidate_tool_names );
			$permission_surface   = PressArk_Permission_Service::build_surface_snapshot( $visibility, $groups );
			if ( class_exists( 'PressArk_Policy_Diagnostics' ) ) {
				PressArk_Policy_Diagnostics::record_tool_surface(
					$permission_surface,
					array_merge(
						$this->permission_meta( $options ),
						array(
							'strategy' => $strategy,
						)
					)
				);
			}
		}

		$schemas = $this->catalog->get_schemas( $candidate_tool_names );
		$maps    = $this->catalog->get_capability_maps( $effective_groups, $candidate_tool_names );
		$descriptors = PressArk_Tools::get_prompt_snippets( $candidate_tool_names );

		return array(
			'schemas'                => $schemas,
			'descriptors'            => $descriptors,
			'capability_map'         => $maps['full'] ?? '',
			'capability_maps'        => $maps,
			'capability_map_variant' => 'full',
			'groups'                 => $effective_groups,
			'requested_groups'       => $groups,
			'strategy'               => $strategy,
			'tool_count'             => count( $schemas ),
			'tool_names'             => $candidate_tool_names,
			'discovered_tool_names'  => $this->normalize_tool_names( (array) ( $options['discovered_tool_names'] ?? array() ) ),
			'effective_visible_tools' => $candidate_tool_names,
			'permission_surface'     => $permission_surface,
			'tool_state'             => $this->build_tool_state(
				$candidate_tool_names,
				$groups,
				$effective_groups,
				$strategy,
				$options,
				$permission_surface
			),
			'deferred_groups'        => array(),
			'deferred_candidates'    => (array) ( $loading_plan['deferred_candidates'] ?? array() ),
			'hydration_plan'         => array(),
			'budget'                 => array(),
		);
	}

	/**
	 * Preserve discovery state when rebuilding a tool set after loads/expands.
	 *
	 * @param array $options Loader options for the next build.
	 * @param array $current Current tool-set payload.
	 * @return array
	 */
	private function merge_state_options( array $options, array $current ): array {
		if ( ! isset( $options['discovered_tool_names'] ) && ! empty( $current['discovered_tool_names'] ) ) {
			$options['discovered_tool_names'] = (array) $current['discovered_tool_names'];
		}
		if ( ! isset( $options['already_loaded_tool_names'] ) && ! empty( $current['tool_names'] ) ) {
			$options['already_loaded_tool_names'] = (array) $current['tool_names'];
		}

		return $options;
	}

	/**
	 * Build the canonical tool-state model shared by loader results, traces,
	 * and operator-facing run details.
	 *
	 * @param string[] $loaded_tool_names Provider-hydrated tool schemas.
	 * @param string[] $requested_groups  Groups requested by the loader.
	 * @param string[] $loaded_groups     Groups that ended up loaded.
	 * @param string   $strategy          Strategy label.
	 * @param array    $options           Loader options.
	 * @param array    $permission_surface Request-scoped permission snapshot.
	 * @return array
	 */
	private function build_tool_state(
		array $loaded_tool_names,
		array $requested_groups,
		array $loaded_groups,
		string $strategy,
		array $options = array(),
		array $permission_surface = array()
	): array {
		$loaded_tool_names = $this->normalize_tool_names( $loaded_tool_names );
		$visibility        = $this->capture_tool_visibility( $options );
		$visible_tools     = $this->normalize_tool_names( (array) ( $visibility['visible_tool_names'] ?? array() ) );
		$blocked_tools     = $this->normalize_tool_names( (array) ( $visibility['hidden_tool_names'] ?? array() ) );
		$discovered_history = array_values( array_intersect(
			$this->normalize_tool_names( (array) ( $options['discovered_tool_names'] ?? array() ) ),
			$visible_tools
		) );
		$discovered_tools = array_values( array_diff( $discovered_history, $loaded_tool_names ) );
		$searchable_tools = array_values( array_diff( $visible_tools, $loaded_tool_names, $discovered_tools ) );
		$loaded_lookup    = array_flip( $loaded_tool_names );
		$discovered_lookup = array_flip( $discovered_tools );
		$blocked_lookup   = array_flip( $blocked_tools );
		$rows             = array();
		$always_loaded_tools = array();
		$auto_loaded_tools = array();
		$deferred_loaded_tools = array();
		$deferred_available_tools = array();

		foreach ( array_merge( $loaded_tool_names, $discovered_tools, $searchable_tools, $blocked_tools ) as $tool_name ) {
			$tool_name = sanitize_key( (string) $tool_name );
			if ( '' === $tool_name || isset( $rows[ $tool_name ] ) ) {
				continue;
			}

			$state = isset( $loaded_lookup[ $tool_name ] )
				? 'loaded'
				: ( isset( $discovered_lookup[ $tool_name ] )
					? 'discovered'
					: ( isset( $blocked_lookup[ $tool_name ] ) ? 'blocked' : 'searchable' ) );
			$loading_intent = PressArk_Operation_Registry::get_loading_intent( $tool_name );

			if ( 'loaded' === $state ) {
				if ( 'always_load' === $loading_intent ) {
					$always_loaded_tools[] = $tool_name;
				} elseif ( 'deferred' === $loading_intent ) {
					$deferred_loaded_tools[] = $tool_name;
				} else {
					$auto_loaded_tools[] = $tool_name;
				}
			} elseif ( 'deferred' === $loading_intent && 'blocked' !== $state ) {
				$deferred_available_tools[] = $tool_name;
			}

			$rows[ $tool_name ] = array(
				'name'           => $tool_name,
				'group'          => $this->catalog->find_group_for_tool( $tool_name ),
				'state'          => $state,
				'schema_state'   => $state,
				'loading_intent' => $loading_intent,
				'loaded'         => isset( $loaded_lookup[ $tool_name ] ),
			);
		}

		return array(
			'contract'            => 'tool_state',
			'version'             => 1,
			'strategy'            => sanitize_key( $strategy ),
			'context'             => $this->permission_context( $options ),
			'requested_groups'    => array_values( array_unique( array_filter( array_map( 'sanitize_key', $requested_groups ) ) ) ),
			'loaded_groups'       => array_values( array_unique( array_filter( array_map( 'sanitize_key', $loaded_groups ) ) ) ),
			'visible_groups'      => $this->groups_from_tool_names( $visible_tools ),
			'loaded_groups_visible' => $this->groups_from_tool_names( $loaded_tool_names ),
			'searchable_groups'   => $this->groups_from_tool_names( $searchable_tools ),
			'discovered_groups'   => $this->groups_from_tool_names( $discovered_tools ),
			'blocked_groups'      => $this->groups_from_tool_names( $blocked_tools ),
			'visible_tools'       => $visible_tools,
			'visible_tool_count'  => count( $visible_tools ),
			'loaded_tools'        => $loaded_tool_names,
			'loaded_tool_count'   => count( $loaded_tool_names ),
			'always_loaded_tools' => array_values( array_unique( $always_loaded_tools ) ),
			'auto_loaded_tools'   => array_values( array_unique( $auto_loaded_tools ) ),
			'deferred_loaded_tools' => array_values( array_unique( $deferred_loaded_tools ) ),
			'deferred_available_tools' => array_values( array_unique( $deferred_available_tools ) ),
			'searchable_tools'    => $searchable_tools,
			'searchable_tool_count' => count( $searchable_tools ),
			'discovered_tools'    => $discovered_tools,
			'discovered_tool_count' => count( $discovered_tools ),
			'discovered_history'  => $discovered_history,
			'blocked_tools'       => $blocked_tools,
			'blocked_tool_count'  => count( $blocked_tools ),
			'blocked_summary'     => (array) ( $visibility['hidden_summary'] ?? array() ),
			'request_hidden_tools' => $this->normalize_tool_names( (array) ( $permission_surface['hidden_tools'] ?? array() ) ),
			'request_hidden_summary' => (array) ( $permission_surface['hidden_summary'] ?? array() ),
			'tools'               => array_values( $rows ),
		);
	}

	/**
	 * Resolve the full visible-vs-blocked capability pool for the current site
	 * and permission context.
	 *
	 * @param array $options Loader options.
	 * @return array
	 */
	private function capture_tool_visibility( array $options = array() ): array {
		$all_tool_names = $this->catalog->get_all_tool_names();

		if ( ! class_exists( 'PressArk_Permission_Service' ) ) {
			return array(
				'context'            => $this->permission_context( $options ),
				'visible_tool_names' => $all_tool_names,
				'hidden_tool_names'  => array(),
				'visible_groups'     => $this->groups_from_tool_names( $all_tool_names ),
				'decisions'          => array(),
				'hidden_summary'     => array(),
			);
		}

		return PressArk_Permission_Service::evaluate_tool_set(
			$all_tool_names,
			$this->permission_context( $options ),
			$this->permission_meta( $options )
		);
	}

	/**
	 * Derive unique groups for a set of tool names.
	 *
	 * @param string[] $tool_names Tool names.
	 * @return string[]
	 */
	private function groups_from_tool_names( array $tool_names ): array {
		$groups = array();
		foreach ( $this->normalize_tool_names( $tool_names ) as $tool_name ) {
			$group = $this->catalog->find_group_for_tool( $tool_name );
			if ( '' !== $group ) {
				$groups[] = $group;
			}
		}

		return array_values( array_unique( $groups ) );
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
			'deferred_candidates' => (array) ( $tool_set['deferred_candidates'] ?? $tool_set['deferred_groups'] ?? array() ),
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
			'deferred_candidates' => (array) ( $tool_set['deferred_candidates'] ?? $tool_set['deferred_groups'] ?? array() ),
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
		PressArk_Token_Budget_Manager $budget_manager,
		array $options = array()
	): int {
		$group_tool_names = array_values( array_diff(
			$this->catalog->get_tool_names_for_groups( array( $group ) ),
			$current_tool_names
		) );
		$group_tool_names = $this->filter_tool_names_by_loading_intent(
			$group_tool_names,
			array( $group ),
			$this->normalize_groups( (array) ( $options['sticky_groups'] ?? array() ) )
		);

		if ( class_exists( 'PressArk_Permission_Service' ) && ! empty( $group_tool_names ) ) {
			$visibility       = PressArk_Permission_Service::evaluate_tool_set(
				$group_tool_names,
				$this->permission_context( $options ),
				$this->permission_meta( $options )
			);
			$group_tool_names = array_values( array_diff(
				$visibility['visible_tool_names'],
				$current_tool_names
			) );
		}

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
		$always = PressArk_Operation_Registry::tool_names_by_loading_intent( 'always_load' );

		foreach ( self::UNIVERSAL_TOOLS as $fallback_tool ) {
			if ( PressArk_Operation_Registry::exists( $fallback_tool )
				&& PressArk_Operation_Registry::is_always_load_tool( $fallback_tool )
			) {
				continue;
			}

			$always[] = $fallback_tool;
		}

		$always = array_values( array_filter(
			array_unique( $always ),
			static function ( string $tool_name ): bool {
				$operation = PressArk_Operation_Registry::resolve( $tool_name );
				if ( ! $operation ) {
					return true;
				}
				if ( 'woocommerce' === $operation->requires ) {
					return class_exists( 'WooCommerce' );
				}
				if ( 'elementor' === $operation->requires ) {
					return class_exists( '\\Elementor\\Plugin' );
				}

				return true;
			}
		) );

		return array_values( $always );
	}

	/**
	 * Apply per-operation loading intent before permission and budget planning.
	 *
	 * - always_load: always included
	 * - deferred: hidden from the initial surface unless the group/tool was
	 *   explicitly loaded earlier in the conversation
	 * - auto: preserve current group-scoped behavior
	 *
	 * @param string[] $tool_names         Candidate tool names.
	 * @param string[] $loaded_groups      Groups in the current build.
	 * @param string[] $sticky_groups      Explicitly loaded/persisted groups.
	 * @param string[] $explicit_tool_names Explicitly requested tools.
	 * @return string[]
	 */
	private function filter_tool_names_by_loading_intent(
		array $tool_names,
		array $loaded_groups,
		array $sticky_groups = array(),
		array $explicit_tool_names = array()
	): array {
		unset( $loaded_groups );

		$classification = $this->classify_tool_names_by_loading_intent(
			$tool_names,
			$sticky_groups,
			$explicit_tool_names
		);

		return (array) ( $classification['selected_tool_names'] ?? array() );
	}

	/**
	 * Classify candidate tools using the registry loading contract first.
	 *
	 * @param string[] $tool_names                Candidate tool names.
	 * @param string[] $sticky_groups             Explicitly loaded groups.
	 * @param string[] $explicit_tool_names       Explicit tool-name loads.
	 * @param string[] $already_loaded_tool_names Previously hydrated tools.
	 * @param array    $options                   Loader context.
	 * @return array<string,mixed>
	 */
	private function classify_tool_names_by_loading_intent(
		array $tool_names,
		array $sticky_groups = array(),
		array $explicit_tool_names = array(),
		array $already_loaded_tool_names = array(),
		array $options = array()
	): array {
		$sticky_groups             = $this->normalize_groups( $sticky_groups );
		$explicit_tool_names       = $this->normalize_tool_names( $explicit_tool_names );
		$already_loaded_tool_names = $this->normalize_tool_names( $already_loaded_tool_names );
		$selected_tool_names       = array();
		$always_load_tool_names    = array();
		$auto_tool_names           = array();
		$deferred_tool_names       = array();

		foreach ( $this->normalize_tool_names( $tool_names ) as $tool_name ) {
			if ( in_array( $tool_name, array( 'discover_tools', 'load_tools', 'load_tool_group' ), true ) ) {
				$selected_tool_names[] = $tool_name;
				$always_load_tool_names[] = $tool_name;
				continue;
			}

			$operation = PressArk_Operation_Registry::resolve( $tool_name );
			if ( ! $operation ) {
				$selected_tool_names[] = $tool_name;
				$auto_tool_names[]     = $tool_name;
				continue;
			}

			$intent = PressArk_Operation_Registry::get_loading_intent( $tool_name );
			if ( 'always_load' === $intent ) {
				$selected_tool_names[] = $tool_name;
				$always_load_tool_names[] = $tool_name;
				continue;
			}

			if ( 'deferred' !== $intent ) {
				$selected_tool_names[] = $tool_name;
				$auto_tool_names[]     = $tool_name;
				continue;
			}

			$should_load_deferred = in_array( $tool_name, $explicit_tool_names, true )
				|| in_array( $tool_name, $already_loaded_tool_names, true )
				|| in_array( sanitize_key( $operation->group ), $sticky_groups, true );

			if ( $should_load_deferred ) {
				$selected_tool_names[] = $tool_name;
				continue;
			}

			$deferred_tool_names[] = $tool_name;
		}

		return array(
			'selected_tool_names'    => array_values( array_unique( $selected_tool_names ) ),
			'always_load_tool_names' => array_values( array_unique( $always_load_tool_names ) ),
			'auto_tool_names'        => array_values( array_unique( $auto_tool_names ) ),
			'deferred_tool_names'    => array_values( array_unique( $deferred_tool_names ) ),
			'deferred_candidates'    => $this->build_deferred_tool_candidates( $deferred_tool_names, $options ),
		);
	}

	/**
	 * Build budget-friendly deferred tool candidate rows.
	 *
	 * @param string[] $tool_names Deferred tool names.
	 * @param array    $options    Loader context.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_deferred_tool_candidates( array $tool_names, array $options = array() ): array {
		$budget_manager = $options['budget_manager'] ?? null;
		$candidates     = array();

		foreach ( $this->normalize_tool_names( $tool_names ) as $tool_name ) {
			$tokens = 0;
			if ( $budget_manager instanceof PressArk_Token_Budget_Manager ) {
				$tokens = $budget_manager->estimate_schema_tokens(
					$this->catalog->get_schemas( array( $tool_name ) )
				);
			}

			$candidates[] = array(
				'name'           => $tool_name,
				'tokens'         => $tokens,
				'type'           => 'tool',
				'loading_intent' => PressArk_Operation_Registry::get_loading_intent( $tool_name ),
			);
		}

		return $candidates;
	}

	/**
	 * Merge deferred group/tool rows while preserving their original shape.
	 *
	 * @param array ...$candidate_sets Deferred candidate lists.
	 * @return array<int,array<string,mixed>>
	 */
	private function merge_deferred_candidate_rows( array ...$candidate_sets ): array {
		$merged = array();

		foreach ( $candidate_sets as $candidate_set ) {
			foreach ( $candidate_set as $candidate ) {
				if ( ! is_array( $candidate ) ) {
					continue;
				}

				$name = sanitize_text_field(
					(string) ( $candidate['name'] ?? $candidate['group'] ?? $candidate['uri'] ?? '' )
				);
				$type = sanitize_key(
					(string) ( $candidate['type'] ?? ( isset( $candidate['group'] ) ? 'group' : 'tool' ) )
				);
				if ( '' === $name ) {
					continue;
				}

				$key = $type . ':' . $name;
				if ( ! isset( $candidate['name'] ) && 'group' !== $type ) {
					$candidate['name'] = $name;
				}
				if ( ! isset( $candidate['type'] ) ) {
					$candidate['type'] = $type;
				}

				$merged[ $key ] = $candidate;
			}
		}

		return array_values( $merged );
	}

	/**
	 * Normalize a list of tool names.
	 *
	 * @param array $tool_names Arbitrary tool identifiers.
	 * @return string[]
	 */
	private function normalize_tool_names( array $tool_names ): array {
		$normalized = array();

		foreach ( $tool_names as $tool_name ) {
			if ( ! is_string( $tool_name ) && ! is_int( $tool_name ) ) {
				continue;
			}

			$name = sanitize_key( (string) $tool_name );
			if ( '' !== $name ) {
				$normalized[] = $name;
			}
		}

		return array_values( array_unique( $normalized ) );
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

	/**
	 * Resolve the current permission context for tool exposure.
	 *
	 * @param array $options Loader options.
	 * @return string
	 */
	private function permission_context( array $options ): string {
		return (string) (
			$options['permission_context']
			?? ( class_exists( 'PressArk_Policy_Engine' )
				? PressArk_Policy_Engine::CONTEXT_INTERACTIVE
				: 'interactive' )
		);
	}

	/**
	 * Build permission-evaluation metadata for tool exposure.
	 *
	 * @param array $options Loader options.
	 * @return array
	 */
	private function permission_meta( array $options ): array {
		$meta = (array) ( $options['permission_meta'] ?? array() );
		if ( ! isset( $meta['tier'] ) && isset( $options['tier'] ) ) {
			$meta['tier'] = $options['tier'];
		}
		if ( ! isset( $meta['decision_purpose'] ) ) {
			$meta['decision_purpose'] = 'tool_surface';
		}
		return $meta;
	}

	/**
	 * Keep only groups that still expose at least one visible tool.
	 *
	 * @param string[] $groups     Candidate groups.
	 * @param string[] $tool_names Visible tool names.
	 * @return string[]
	 */
	private function visible_groups_from_tool_names( array $groups, array $tool_names ): array {
		$visible_groups = array();
		foreach ( $groups as $group ) {
			$group_tools = $this->catalog->get_tool_names_for_groups( array( $group ) );
			if ( ! empty( array_intersect( $group_tools, $tool_names ) ) ) {
				$visible_groups[] = $group;
			}
		}

		return array_values( array_unique( $visible_groups ) );
	}
}
