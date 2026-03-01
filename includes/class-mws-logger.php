<?php

if (! defined('ABSPATH')) {
	exit;
}

final class MWS_Logger {
	public static function log($event, array $context = array(), $level = 'info') {
		if (! defined('WP_DEBUG') || ! WP_DEBUG) {
			return;
		}

		$payload = array(
			'channel'   => 'morgao-webring-signature',
			'event'     => (string) $event,
			'level'     => (string) $level,
			'timestamp' => gmdate('c'),
			'context'   => $context,
		);

		error_log(wp_json_encode($payload));
	}
}
