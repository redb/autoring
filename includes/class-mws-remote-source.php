<?php

if (! defined('ABSPATH')) {
	exit;
}

final class MWS_Remote_Source {
	private $config;
	private $validator;
	private $last_payload;

	public function __construct(MWS_Config $config, MWS_Validator $validator) {
		$this->config    = $config;
		$this->validator = $validator;
		$this->last_payload = null;
	}

	public function get_sites($url, $force_refresh = false) {
		$payload = $this->get_payload($url, $force_refresh);

		if (is_wp_error($payload)) {
			return $payload;
		}

		return $payload['sites'];
	}

	public function get_payload($url, $force_refresh = false) {
		$url = trim((string) $url);

		if ($url === '') {
			return new WP_Error('mws_remote_url_missing', __('Remote source URL is missing.', 'morgao-webring-signature'));
		}

		$cache_key = $this->config->get_remote_cache_key($url);
		$cached    = get_transient($cache_key);

		if (! $force_refresh && is_array($cached) && ! empty($cached)) {
			$this->last_payload = $cached;
			return $cached;
		}

		$result = $this->request_with_retries($url);

		if (is_wp_error($result)) {
			return $result;
		}

		set_transient($cache_key, $result, (int) $this->config->get('remote_cache_ttl'));
		$this->last_payload = $result;

		return $result;
	}

	public function get_last_payload() {
		return is_array($this->last_payload) ? $this->last_payload : null;
	}

	private function request_with_retries($url) {
		$attempts = max(1, (int) $this->config->get('remote_request_retries') + 1);
		$last     = null;

		for ($attempt = 1; $attempt <= $attempts; $attempt++) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => (int) $this->config->get('remote_request_timeout'),
					'redirection' => 2,
					'headers'     => array(
						'Accept'     => 'application/json',
						'User-Agent' => 'MorgaoAutoRing/' . MWS_PLUGIN_VERSION,
					),
				)
			);

			if (is_wp_error($response)) {
				$last = $response;
				MWS_Logger::log('remote_fetch_error', array('attempt' => $attempt, 'message' => $response->get_error_message(), 'url' => $url), 'warning');
				continue;
			}

			$status = (int) wp_remote_retrieve_response_code($response);
			$body   = wp_remote_retrieve_body($response);

			if ($status < 200 || $status >= 300) {
				$last = new WP_Error('mws_remote_bad_status', sprintf(__('Remote source returned HTTP %d.', 'morgao-webring-signature'), $status));
				MWS_Logger::log('remote_fetch_status', array('attempt' => $attempt, 'status' => $status, 'url' => $url), 'warning');
				continue;
			}

			$decoded = json_decode($body, true);

			$validated = $this->normalize_payload($decoded);

			if (is_wp_error($validated)) {
				$last = $validated;
				MWS_Logger::log('remote_fetch_invalid_payload', array('attempt' => $attempt, 'url' => $url, 'error' => $validated->get_error_message()), 'warning');
				continue;
			}

			return $validated;
		}

		return $last instanceof WP_Error ? $last : new WP_Error('mws_remote_failed', __('Remote source could not be loaded.', 'morgao-webring-signature'));
	}

	private function normalize_payload($decoded) {
		$sites  = $decoded;
		$shared = array();

		if (is_array($decoded) && isset($decoded['sites']) && is_array($decoded['sites'])) {
			$sites = $decoded['sites'];

			if (isset($decoded['shared']) && is_array($decoded['shared'])) {
				$shared = $this->validator->sanitize_shared_branding($decoded['shared'], $this->config);
			}
		}

		$validated_sites = $this->validator->validate_sites($sites);

		if (is_wp_error($validated_sites)) {
			return $validated_sites;
		}

		return array(
			'sites'   => $validated_sites,
			'shared'  => $shared,
		);
	}
}
