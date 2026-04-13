<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Context {

	private int $user_id;

	private string $tier;

	private int $post_id;

	private string $screen;

	private string $permission_context;

	/**
	 * @var callable|null
	 */
	private $abort_signal;

	private ?PressArk_Action_Engine $action_engine;

	/**
	 * @var callable|null
	 */
	private $progress_callback;

	private array $meta;

	public function __construct(
		int $user_id = 0,
		string $tier = 'free',
		int $post_id = 0,
		string $screen = '',
		string $permission_context = '',
		$abort_signal = null,
		?PressArk_Action_Engine $action_engine = null,
		$progress_callback = null,
		array $meta = array()
	) {
		$this->user_id            = max( 0, $user_id );
		$this->tier               = '' !== $tier ? sanitize_key( $tier ) : 'free';
		$this->post_id            = max( 0, $post_id );
		$this->screen             = sanitize_key( $screen );
		$this->permission_context = '' !== $permission_context
			? sanitize_key( $permission_context )
			: ( class_exists( 'PressArk_Policy_Engine' )
				? PressArk_Policy_Engine::CONTEXT_INTERACTIVE
				: 'interactive' );
		$this->abort_signal       = is_callable( $abort_signal ) ? $abort_signal : null;
		$this->action_engine      = $action_engine;
		$this->progress_callback  = is_callable( $progress_callback ) ? $progress_callback : null;
		$this->meta               = is_array( $meta ) ? $meta : array();
	}

	public function get_user_id(): int {
		return $this->user_id;
	}

	public function get_tier(): string {
		return $this->tier;
	}

	public function get_post_id(): int {
		return $this->post_id;
	}

	public function get_screen(): string {
		return $this->screen;
	}

	public function get_permission_context(): string {
		return $this->permission_context;
	}

	public function get_action_engine(): ?PressArk_Action_Engine {
		return $this->action_engine;
	}

	public function get_progress_callback() {
		return $this->progress_callback;
	}

	public function get_meta(): array {
		return $this->meta;
	}

	public function is_aborted(): bool {
		if ( ! is_callable( $this->abort_signal ) ) {
			return false;
		}

		return (bool) call_user_func( $this->abort_signal );
	}

	public function to_permission_meta(): array {
		$meta = array_merge(
			$this->meta,
			array(
				'tier'    => $this->tier,
				'user_id' => $this->user_id,
				'post_id' => $this->post_id,
				'screen'  => $this->screen,
			)
		);

		return array_filter(
			$meta,
			static function ( $value ) {
				return ! ( is_string( $value ) && '' === $value );
			}
		);
	}

	public function to_execution_meta(): array {
		return array(
			'permission_context' => $this->permission_context,
			'permission_meta'    => $this->to_permission_meta(),
		);
	}
}
