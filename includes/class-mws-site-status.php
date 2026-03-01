<?php

if (! defined('ABSPATH')) {
	exit;
}

final class MWS_Site_Status {
	private $config;

	public function __construct(MWS_Config $config) {
		$this->config = $config;
	}

	public function get_status_map(array $sites) {
		$map = array();

		foreach ($sites as $site) {
			$id = isset($site['id']) ? (string) $site['id'] : '';

			if ($id === '') {
				continue;
			}

			$map[ $id ] = $this->get_cached_status($site);
		}

		return $map;
	}

	public function refresh_sites(array $sites) {
		$statuses = array();

		foreach ($sites as $site) {
			$id = isset($site['id']) ? (string) $site['id'] : '';

			if ($id === '') {
				continue;
			}

			$statuses[ $id ] = $this->probe_site($site);
			set_transient(
				$this->get_cache_key($site),
				$statuses[ $id ],
				(int) $this->config->get('status_cache_ttl')
			);
		}

		MWS_Logger::log('site_statuses_refreshed', array('count' => count($statuses)));

		return $statuses;
	}

	private function get_cached_status(array $site) {
		$cached = get_transient($this->get_cache_key($site));

		if (is_array($cached) && isset($cached['state'])) {
			return $cached;
		}

		return array(
			'state'        => 'offline',
			'status_code'  => 0,
			'checked_at'   => '',
			'response_ms'  => null,
			'error'        => 'No cached check available yet.',
		);
	}

	private function probe_site(array $site) {
		$url      = (string) ($site['url'] ?? '');
		$attempts = max(1, (int) $this->config->get('status_request_retries') + 1);
		$last     = null;

		for ($attempt = 1; $attempt <= $attempts; $attempt++) {
			$started  = microtime(true);
			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => (int) $this->config->get('status_request_timeout'),
					'redirection' => 2,
					'headers'     => array(
						'User-Agent' => 'MorgaoAutoRing/' . MWS_PLUGIN_VERSION,
					),
				)
			);

			if (is_wp_error($response)) {
				$last = array(
					'state'       => 'offline',
					'status_code' => 0,
					'checked_at'  => gmdate('c'),
					'response_ms' => (int) round((microtime(true) - $started) * 1000),
					'error'       => $response->get_error_message(),
				);
				continue;
			}

			$status_code = (int) wp_remote_retrieve_response_code($response);
			$state       = $status_code > 0 && $status_code < 600 ? 'online' : 'offline';

			return array(
				'state'       => $state,
				'status_code' => $status_code,
				'checked_at'  => gmdate('c'),
				'response_ms' => (int) round((microtime(true) - $started) * 1000),
				'error'       => '',
			);
		}

		MWS_Logger::log(
			'site_status_probe_failed',
			array(
				'url'   => $url,
				'error' => is_array($last) ? ($last['error'] ?? 'unknown') : 'unknown',
			),
			'warning'
		);

		return is_array($last) ? $last : array(
			'state'       => 'offline',
			'status_code' => 0,
			'checked_at'  => gmdate('c'),
			'response_ms' => null,
			'error'       => 'Unknown health-check error.',
		);
	}

	private function get_cache_key(array $site) {
		$id = isset($site['id']) ? (string) $site['id'] : md5(wp_json_encode($site));

		return (string) $this->config->get('status_cache_key') . $id;
	}
}
