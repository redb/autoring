<?php

if (! defined('ABSPATH')) {
	exit;
}

final class MWS_Rate_Limiter {
	public function allow($bucket, $max_attempts, $window_seconds) {
		$user_id = get_current_user_id();
		$ip      = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
		$key     = 'mws_rate_' . md5($bucket . '|' . $user_id . '|' . $ip);
		$state   = get_transient($key);

		if (! is_array($state)) {
			$state = array(
				'count' => 0,
			);
		}

		$state['count']++;
		set_transient($key, $state, (int) $window_seconds);

		return $state['count'] <= (int) $max_attempts;
	}
}
