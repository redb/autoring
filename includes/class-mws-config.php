<?php

if (! defined('ABSPATH')) {
	exit;
}

final class MWS_Config {
	private $defaults;

	public function __construct(array $defaults) {
		$this->defaults = $defaults;
	}

	public function get($key) {
		return $this->defaults[ $key ] ?? null;
	}

	public function get_option_name() {
		return (string) $this->get('option_name');
	}

	public function get_version_option_name() {
		return (string) $this->get('version_option_name');
	}

	public function get_sites_option_name() {
		return (string) $this->get('sites_option_name');
	}

	public function get_setup_option_name() {
		return (string) $this->get('setup_option_name');
	}

	public function get_activation_redirect_key() {
		return (string) $this->get('activation_redirect_key');
	}

	public function get_default_settings() {
		return array(
			'current_site_id'    => '',
			'use_remote_source'  => false,
			'remote_source_url'  => '',
			'accent_color'       => (string) $this->get('accent_color'),
			'shared_signature_label' => '',
			'open_in_new_tab'    => (bool) $this->get('open_in_new_tab'),
			'hub_mode_enabled'   => false,
			'hub_allow_registrations' => true,
			'hub_url'            => '',
			'hub_secret'         => '',
			'hub_auto_register'  => false,
			'use_github_updates' => (bool) $this->get('use_github_updates'),
			'github_repository'  => 'redb/autoring',
			'github_asset_name'  => MWS_PLUGIN_SLUG . '.zip',
			'give_url'           => '',
			'give_label'         => (string) $this->get('give_default_label'),
			'show_give_button'   => false,
		);
	}

	public function get_settings() {
		$saved = get_option($this->get_option_name(), array());
		$saved = is_array($saved) ? $saved : array();

		$settings = wp_parse_args($saved, $this->get_default_settings());

		if (defined('MWS_CURRENT_SITE_ID') && is_string(MWS_CURRENT_SITE_ID) && MWS_CURRENT_SITE_ID !== '') {
			$settings['current_site_id'] = MWS_CURRENT_SITE_ID;
		}

		if (defined('MWS_REMOTE_SOURCE_URL') && is_string(MWS_REMOTE_SOURCE_URL)) {
			$settings['remote_source_url'] = MWS_REMOTE_SOURCE_URL;
		}

		if (defined('MWS_HUB_URL') && is_string(MWS_HUB_URL)) {
			$settings['hub_url'] = trim(MWS_HUB_URL);
		}

		if (defined('MWS_HUB_SECRET') && is_string(MWS_HUB_SECRET)) {
			$settings['hub_secret'] = trim(MWS_HUB_SECRET);
		}

		if (defined('MWS_GITHUB_UPDATES_ENABLED')) {
			$settings['use_github_updates'] = (bool) MWS_GITHUB_UPDATES_ENABLED;
		}

		if (defined('MWS_GITHUB_REPOSITORY') && is_string(MWS_GITHUB_REPOSITORY)) {
			$settings['github_repository'] = trim(MWS_GITHUB_REPOSITORY);
		}

		if (defined('MWS_GITHUB_RELEASE_ASSET') && is_string(MWS_GITHUB_RELEASE_ASSET)) {
			$settings['github_asset_name'] = trim(MWS_GITHUB_RELEASE_ASSET);
		}

		return $settings;
	}

	public function get_remote_cache_key($url) {
		return (string) $this->get('remote_cache_key') . md5((string) $url);
	}

	public function get_github_release_cache_key($repository) {
		return (string) $this->get('github_release_cache_key') . md5((string) $repository);
	}

	public function get_github_api_base_url() {
		return rtrim((string) $this->get('github_api_base_url'), '/');
	}

	public function get_hub_registration_cache_key($hub_url, $site_url) {
		return (string) $this->get('hub_registration_key') . md5((string) $hub_url . '|' . (string) $site_url);
	}

	public function get_shared_branding_keys() {
		return array(
			'shared_signature_label',
			'accent_color',
			'show_give_button',
			'give_url',
			'give_label',
		);
	}
}
