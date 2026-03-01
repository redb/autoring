<?php

if (! defined('ABSPATH')) {
	exit;
}

final class MWS_GitHub_Updater {
	private $config;

	public function __construct(MWS_Config $config) {
		$this->config = $config;
	}

	public function inject_update($transient) {
		if (! is_object($transient) || empty($transient->checked[ MWS_PLUGIN_BASENAME ])) {
			return $transient;
		}

		$release = $this->get_release();

		if (is_wp_error($release) || $release === null) {
			return $transient;
		}

		$current_version = (string) $transient->checked[ MWS_PLUGIN_BASENAME ];

		if (! version_compare($release->version, $current_version, '>')) {
			return $transient;
		}

		$transient->response[ MWS_PLUGIN_BASENAME ] = (object) array(
			'slug'        => MWS_PLUGIN_SLUG,
			'plugin'      => MWS_PLUGIN_BASENAME,
			'new_version' => $release->version,
			'package'     => $release->package_url,
			'url'         => $release->html_url,
			'tested'      => $release->tested,
			'requires'    => $release->requires,
			'requires_php'=> $release->requires_php,
		);

		return $transient;
	}

	public function provide_plugin_info($result, $action, $args) {
		if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== MWS_PLUGIN_SLUG) {
			return $result;
		}

		$release = $this->get_release();

		if (is_wp_error($release) || $release === null) {
			return $result;
		}

		return (object) array(
			'name'          => 'Morgao AutoRing',
			'slug'          => MWS_PLUGIN_SLUG,
			'version'       => $release->version,
			'author'        => '<a href="https://morgao.com">Morgao</a>',
			'homepage'      => $release->html_url,
			'download_link' => $release->package_url,
			'tested'        => $release->tested,
			'requires'      => $release->requires,
			'requires_php'  => $release->requires_php,
			'sections'      => array(
				'description' => wp_kses_post__('Simple and minimal webring signature for Morgao sites.', 'morgao-webring-signature'),
				'changelog'   => wp_kses_post(wpautop($release->body)),
			),
		);
	}

	public function filter_http_request_args($args, $url) {
		$settings   = $this->config->get_settings();
		$token      = $this->get_github_token();
		$api_base   = $this->config->get_github_api_base_url();
		$is_api_url = strpos($url, $api_base . '/repos/') === 0;

		if (! $is_api_url) {
			return $args;
		}

		if (! isset($args['headers']) || ! is_array($args['headers'])) {
			$args['headers'] = array();
		}

		if (strpos($url, '/releases/assets/') !== false) {
			$args['headers']['Accept'] = 'application/octet-stream';
		} else {
			$args['headers']['Accept'] = 'application/vnd.github+json';
		}

		$args['headers']['User-Agent'] = 'MorgaoAutoRing/' . MWS_PLUGIN_VERSION;
		$args['timeout']               = (int) $this->config->get('github_request_timeout');

		if ($token !== '') {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		if (! empty($settings['github_repository'])) {
			$args['headers']['X-GitHub-Api-Version'] = '2022-11-28';
		}

		return $args;
	}

	public function get_release($force_refresh = false) {
		$settings = $this->config->get_settings();

		if (empty($settings['use_github_updates']) || empty($settings['github_repository'])) {
			return null;
		}

		$repository = (string) $settings['github_repository'];
		$cache_key  = $this->config->get_github_release_cache_key($repository);
		$cached     = get_transient($cache_key);

		if (! $force_refresh && is_array($cached) && ! empty($cached['version'])) {
			return (object) $cached;
		}

		$release = $this->request_release($repository, (string) $settings['github_asset_name']);

		if (is_wp_error($release)) {
			return $release;
		}

		set_transient($cache_key, (array) $release, (int) $this->config->get('github_release_cache_ttl'));

		return $release;
	}

	private function request_release($repository, $asset_name) {
		$url      = $this->config->get_github_api_base_url() . '/repos/' . rawurlencode($this->get_repo_owner($repository)) . '/' . rawurlencode($this->get_repo_name($repository)) . '/releases/latest';
		$response = $this->request_json_with_retries($url);

		if (is_wp_error($response)) {
			MWS_Logger::log('github_release_fetch_failed', array('repository' => $repository, 'error' => $response->get_error_message()), 'warning');
			return $response;
		}

		$package = $this->find_release_asset_url($response, $asset_name, $repository);

		if (is_wp_error($package)) {
			MWS_Logger::log('github_release_asset_missing', array('repository' => $repository, 'asset' => $asset_name), 'warning');
			return $package;
		}

		return (object) array(
			'version'      => $this->normalize_version((string) ($response['tag_name'] ?? '')),
			'package_url'  => $package,
			'html_url'     => esc_url_raw((string) ($response['html_url'] ?? 'https://github.com/' . $repository)),
			'body'         => (string) ($response['body'] ?? ''),
			'tested'       => '',
			'requires'     => '6.0',
			'requires_php' => '7.4',
		);
	}

	private function request_json_with_retries($url) {
		$attempts = max(1, (int) $this->config->get('github_request_retries') + 1);
		$last     = null;

		for ($attempt = 1; $attempt <= $attempts; $attempt++) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => (int) $this->config->get('github_request_timeout'),
					'redirection' => 2,
				)
			);

			if (is_wp_error($response)) {
				$last = $response;
				continue;
			}

			$status = (int) wp_remote_retrieve_response_code($response);
			$body   = wp_remote_retrieve_body($response);

			if ($status < 200 || $status >= 300) {
				$last = new WP_Error('mws_github_bad_status', sprintf(__('GitHub returned HTTP %d.', 'morgao-webring-signature'), $status));
				continue;
			}

			$decoded = json_decode($body, true);

			if (! is_array($decoded)) {
				$last = new WP_Error('mws_github_invalid_json', __('GitHub returned an invalid JSON payload.', 'morgao-webring-signature'));
				continue;
			}

			return $decoded;
		}

		return $last instanceof WP_Error ? $last : new WP_Error('mws_github_failed', __('GitHub release metadata could not be fetched.', 'morgao-webring-signature'));
	}

	private function find_release_asset_url(array $release, $asset_name, $repository) {
		$assets = isset($release['assets']) && is_array($release['assets']) ? $release['assets'] : array();

		foreach ($assets as $asset) {
			if (! is_array($asset) || ($asset['name'] ?? '') !== $asset_name || empty($asset['id'])) {
				continue;
			}

			return $this->config->get_github_api_base_url() . '/repos/' . rawurlencode($this->get_repo_owner($repository)) . '/' . rawurlencode($this->get_repo_name($repository)) . '/releases/assets/' . (int) $asset['id'];
		}

		return new WP_Error(
			'mws_github_asset_missing',
			sprintf(__('GitHub release asset "%s" was not found.', 'morgao-webring-signature'), $asset_name)
		);
	}

	private function normalize_version($tag_name) {
		$tag_name = trim($tag_name);
		$tag_name = ltrim($tag_name, 'vV');

		return $tag_name !== '' ? $tag_name : MWS_PLUGIN_VERSION;
	}

	private function get_repo_owner($repository) {
		$parts = explode('/', $repository, 2);

		return $parts[0] ?? '';
	}

	private function get_repo_name($repository) {
		$parts = explode('/', $repository, 2);

		return $parts[1] ?? '';
	}

	private function get_github_token() {
		if (defined('MWS_GITHUB_TOKEN') && is_string(MWS_GITHUB_TOKEN)) {
			return trim(MWS_GITHUB_TOKEN);
		}

		return '';
	}
}
