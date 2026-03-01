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

		update_option($this->config->get_option_name(), $settings, false);

		if (! is_array($sites) || empty($sites)) {
			$seed_sites = include MWS_PLUGIN_DIR . 'config/default-sites.php';
			$seed_sites = $this->validator->validate_sites($seed_sites);

			if (! is_wp_error($seed_sites)) {
				update_option($this->config->get_sites_option_name(), $seed_sites, false);
			}
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
}
