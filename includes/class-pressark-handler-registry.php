<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lazy factory and dispatch layer for action handlers.
 *
 * Keeps handler construction and routing out of the action engine so the
 * engine can stay focused on normalization, entitlements, and execution flow.
 *
 * @since 4.1.2
 */
class PressArk_Handler_Registry {

	/** @var array<string, class-string> */
	private const HANDLER_CLASSES = array(
		'discovery'   => PressArk_Handler_Discovery::class,
		'seo'         => PressArk_Handler_SEO::class,
		'diagnostics' => PressArk_Handler_Diagnostics::class,
		'media'       => PressArk_Handler_Media::class,
		'content'     => PressArk_Handler_Content::class,
		'system'      => PressArk_Handler_System::class,
		'elementor'   => PressArk_Handler_Elementor::class,
		'woo'         => PressArk_Handler_WooCommerce::class,
		'automation'  => PressArk_Handler_Automation::class,
	);

	private PressArk_Action_Logger $logger;

	/** @var array<string, object> */
	private array $instances = array();

	private string $async_task_id = '';

	public function __construct( PressArk_Action_Logger $logger ) {
		$this->logger = $logger;
	}

	public function set_async_context( string $task_id ): void {
		$this->async_task_id = $task_id;

		foreach ( $this->instances as $handler ) {
			if ( $handler instanceof PressArk_Handler_Base ) {
				$handler->set_async_context( $task_id );
			}
		}
	}

	public function dispatch( PressArk_Operation $operation, array $params, ?callable $on_progress = null ): array {
		$handler = $this->get( $operation->handler );
		$method  = $operation->method;

		if ( ! is_callable( array( $handler, $method ) ) ) {
			throw new \UnexpectedValueException(
				sprintf(
					'Handler "%s" does not implement callable method "%s".',
					esc_html( (string) $operation->handler ),
					esc_html( $method )
				)
			);
		}

		$progress_callback = is_callable( $on_progress )
			? $on_progress
			: ( is_callable( $operation->on_progress ) ? $operation->on_progress : null );

		if ( ! is_callable( $progress_callback ) ) {
			return $handler->{$method}( $params );
		}

		try {
			$reflection = new \ReflectionMethod( $handler, $method );
			if ( $reflection->getNumberOfParameters() >= 2 ) {
				return $handler->{$method}( $params, $progress_callback );
			}
		} catch ( \ReflectionException $e ) {
			PressArk_Error_Tracker::warning(
				'HandlerRegistry',
				'Unable to inspect handler method for progress callback support',
				array(
					'handler' => $operation->handler,
					'method'  => $method,
					'error'   => $e->getMessage(),
				)
			);
		}

		return $handler->{$method}( $params );
	}

	public function check_permissions( PressArk_Operation $operation, array $params, array $context = array() ): array {
		$handler = $this->get( $operation->handler );

		if ( $handler instanceof PressArk_Handler_Base ) {
			return $handler->check_permissions( $operation->name, $params, $context );
		}

		return array(
			'allowed'   => true,
			'behavior'  => 'allow',
			'reason'    => '',
			'ui_action' => 'none',
		);
	}

	public function get( string $key ): object {
		if ( ! isset( $this->instances[ $key ] ) ) {
			$this->instances[ $key ] = $this->build_handler( $key );
		}

		return $this->instances[ $key ];
	}

	private function build_handler( string $key ): object {
		$class = self::HANDLER_CLASSES[ $key ] ?? '';

		if ( '' === $class ) {
			throw new \InvalidArgumentException( sprintf( 'Unknown handler key: %s', esc_html( $key ) ) );
		}

		$handler = new $class( $this->logger );

		if ( $handler instanceof PressArk_Handler_Base && $this->async_task_id ) {
			$handler->set_async_context( $this->async_task_id );
		}

		return $handler;
	}
}
