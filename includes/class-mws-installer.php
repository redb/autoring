<?php

if (! defined('ABSPATH')) {
	exit;
}

final class MWS_Installer {
	private $config;
	private $validator;

	public function __construct(MWS_Config $config, MWS_Validator $validator) {
		$this->config    = $config;
		$this->validator = $validator;
	}

	public function activate() {
		$this->upgrade_if_needed(true);
		set_transient($this->config->get_activation_redirect_key(), 1, MINUTE_IN_SECONDS);
	}

	public function upgrade_if_needed($force = false) {
		$installed_version = get_option($this->config->get_version_option_name(), '');

		if (! $force && $installed_version === MWS_PLUGIN_VERSION) {
			return;
		}

		$settings = get_option($this->config->get_option_name(), array());
		$settings = is_array($settings) ? $settings : array();
		$settings = wp_parse_args($settings, $this->config->get_default_settings());
		$sites    = get_option($this->config->get_sites_option_name(), null);

		if (empty($installed_version)) {
			$settings['hub_mode_enabled']        = true;
			$settings['hub_allow_registrations'] = true;
			$settings['hub_url']                 = untrailingslashit(home_url('/'));
			$settings['hub_auto_register']       = false;
		}

		update_option($this->config->get_option_name(), $settings, false);

		if (! is_array($sites) || empty($sites)) {
			$seed_sites = $this->validator->validate_sites(array($this->build_current_site()));

			if (! is_wp_error($seed_sites)) {
				update_option($this->config->get_sites_option_name(), $seed_sites, false);
			}
		}

		if (empty($installed_version)) {
			update_option($this->config->get_setup_option_name(), 0, false);
		}

		update_option($this->config->get_version_option_name(), MWS_PLUGIN_VERSION, false);

		MWS_Logger::log(
			'plugin_upgraded',
			array(
				'from' => $installed_version,
				'to'   => MWS_PLUGIN_VERSION,
			)
		);
	}

	private function build_current_site() {
		$host = wp_parse_url(home_url('/'), PHP_URL_HOST);
		$id   = is_string($host) && $host !== '' ? sanitize_key(str_replace('.', '-', $host)) : 'site-' . substr(md5(home_url('/')), 0, 8);
		$name = get_bloginfo('name');

		if (! is_string($name) || trim($name) === '') {
			$name = $host ?: 'Site';
		}

		return array(
			'id'   => $id,
			'name' => sanitize_text_field($name),
			'url'  => untrailingslashit(home_url('/')),
		);
	}
}
