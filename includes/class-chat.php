<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/chat/class-chat-controller.php';

/**
 * Backward-compatible chat facade.
 */
class PressArk_Chat extends PressArk_Chat_Controller {}
