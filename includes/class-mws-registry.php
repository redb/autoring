<?php

if (! defined('ABSPATH')) {
	exit;
}

final class MWS_Registry {
	private $config;
	private $validator;
	private $remote_source;
	private $hub;
	private $shared_branding;

	public function __construct(MWS_Config $config, MWS_Validator $validator, MWS_Remote_Source $remote_source, MWS_Hub $hub) {
		$this->config        = $config;
		$this->validator     = $validator;
		$this->remote_source = $remote_source;
		$this->hub           = $hub;
		$this->shared_branding = array();
	}

	public function get_sites($force_refresh = false) {
		$settings = $this->config->get_settings();
		$this->shared_branding = array();

		if (! empty($settings['hub_mode_enabled'])) {
			$admin_sites = $this->get_admin_sites();

			if (! is_wp_error($admin_sites) && ! empty($admin_sites)) {
				$this->shared_branding = $this->validator->sanitize_shared_branding($settings, $this->config);
				return $admin_sites;
			}
		}

		if (empty($settings['hub_mode_enabled']) && ! empty($settings['hub_url'])) {
			$hub_payload = $this->remote_source->get_payload($this->hub->get_sites_endpoint_url($settings['hub_url']), $force_refresh);

			if (! is_wp_error($hub_payload) && is_array($hub_payload) && isset($hub_payload['sites']) && is_array($hub_payload['sites'])) {
				$this->shared_branding = is_array($hub_payload['shared'] ?? null) ? $hub_payload['shared'] : array();
				return $hub_payload['sites'];
			}

			$error_message = is_wp_error($hub_payload)
				? $hub_payload->get_error_message()
				: __('Hub payload is missing the sites registry.', 'morgao-webring-signature');

			MWS_Logger::log('hub_source_fallback', array('error' => $error_message, 'url' => $settings['hub_url']), 'warning');
		}

		$admin_sites = $this->get_admin_sites();

		if (! is_wp_error($admin_sites) && ! empty($admin_sites)) {
			$this->shared_branding = $this->validator->sanitize_shared_branding($settings, $this->config);
			return $admin_sites;
		}

		if (! empty($settings['use_remote_source']) && ! empty($settings['remote_source_url'])) {
			$remote_sites = $this->remote_source->get_sites($settings['remote_source_url'], $force_refresh);

			if (! is_wp_error($remote_sites)) {
				return $remote_sites;
			}

			MWS_Logger::log(
				'remote_source_fallback',
				array(
					'error' => $remote_sites->get_error_message(),
					'url'   => $settings['remote_source_url'],
				),
				'warning'
			);
		}

		$local_sites = include MWS_PLUGIN_DIR . 'config/default-sites.php';
		$validated   = $this->validator->validate_sites($local_sites);

		if (is_wp_error($validated)) {
			return array(
				array(
					'id'   => 'morgao-fallback',
					'name' => 'Morgao',
					'url'  => home_url('/'),
				),
			);
		}

		return $validated;
	}

	public function get_context() {
		$sites           = $this->get_sites();

		if (! is_array($sites)) {
			$sites = array();
		}

		$settings        = $this->get_effective_settings();
		$current_site_id = (string) ($settings['current_site_id'] ?? '');
		$current_index   = $this->find_current_site_index($sites, $current_site_id);
		$count           = count($sites);

		if ($count < 1) {
			return array();
		}

		if ($current_index < 0) {
			$current_index = 0;
		}

		$previous_index = $count > 1 ? (($current_index - 1 + $count) % $count) : $current_index;
		$next_index     = $count > 1 ? (($current_index + 1) % $count) : $current_index;
		$random_index   = $count > 1 ? $this->find_random_index($count, $current_index) : $current_index;

		return array(
			'sites'        => $sites,
			'count'        => $count,
			'current'      => $sites[ $current_index ],
			'previous'     => $sites[ $previous_index ],
			'next'         => $sites[ $next_index ],
			'random'       => $sites[ $random_index ],
			'currentIndex' => $current_index,
			'settings'     => $settings,
		);
	}

	public function get_effective_settings() {
		$settings = $this->config->get_settings();

		if (empty($this->shared_branding)) {
			$this->get_sites();
		}

		foreach ($this->shared_branding as $key => $value) {
			$settings[ $key ] = $value;
		}

		return $settings;
	}

	public function refresh_remote_sites() {
		$settings = $this->config->get_settings();

		if (empty($settings['use_remote_source']) || empty($settings['remote_source_url'])) {
			return new WP_Error('mws_remote_disabled', __('Remote source is not enabled.', 'morgao-webring-signature'));
		}

		return $this->remote_source->get_sites($settings['remote_source_url'], true);
	}

	public function get_admin_sites() {
		$sites = get_option($this->config->get_sites_option_name(), array());

		return $this->validator->validate_sites($sites);
	}

	public function save_admin_sites($sites) {
		$validated = $this->validator->validate_sites($sites);

		if (is_wp_error($validated)) {
			return $validated;
		}

		update_option($this->config->get_sites_option_name(), $validated, false);

		MWS_Logger::log('sites_saved', array('count' => count($validated)));

		return $validated;
	}

	private function find_current_site_index(array $sites, $current_site_id) {
		if ($current_site_id !== '') {
			foreach ($sites as $index => $site) {
				if ($site['id'] === $current_site_id) {
					return $index;
				}
			}
		}

		$current_host = wp_parse_url(home_url('/'), PHP_URL_HOST);

		if (! is_string($current_host) || $current_host === '') {
			return -1;
		}

		foreach ($sites as $index => $site) {
			$site_host = wp_parse_url($site['url'], PHP_URL_HOST);

			if ($site_host === $current_host) {
				return $index;
			}
		}

		return -1;
	}

	private function find_random_index($count, $current_index) {
		$available = array_values(array_diff(range(0, $count - 1), array($current_index)));

		if (empty($available)) {
			return $current_index;
		}

		return $available[ array_rand($available) ];
	}
}
