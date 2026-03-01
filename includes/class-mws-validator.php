<?php

if (! defined('ABSPATH')) {
	exit;
}

final class MWS_Validator {
	public function validate_settings($input, MWS_Config $config) {
		$defaults = $config->get_default_settings();
		$input    = is_array($input) ? $input : array();

		$current_site_id = sanitize_key($input['current_site_id'] ?? '');
		$remote_source   = ! empty($input['use_remote_source']);
		$remote_url      = isset($input['remote_source_url']) ? trim((string) $input['remote_source_url']) : '';
		$accent_color    = sanitize_hex_color($input['accent_color'] ?? $defaults['accent_color']);
		$open_in_new_tab = ! empty($input['open_in_new_tab']);
		$hub_mode_enabled = ! empty($input['hub_mode_enabled']);
		$hub_allow_registrations = ! empty($input['hub_allow_registrations']);
		$hub_url         = isset($input['hub_url']) ? trim((string) $input['hub_url']) : '';
		$hub_secret      = isset($input['hub_secret']) ? trim((string) $input['hub_secret']) : '';
		$hub_auto_register = ! empty($input['hub_auto_register']);
		$github_updates  = ! empty($input['use_github_updates']);
		$github_repo     = isset($input['github_repository']) ? trim((string) $input['github_repository']) : '';
		$github_asset    = isset($input['github_asset_name']) ? trim((string) $input['github_asset_name']) : $defaults['github_asset_name'];
		$give_url        = isset($input['give_url']) ? trim((string) $input['give_url']) : '';
		$give_label      = isset($input['give_label']) ? sanitize_text_field((string) $input['give_label']) : $defaults['give_label'];
		$show_give_button = ! empty($input['show_give_button']);

		if ($accent_color === null || $accent_color === '') {
			$accent_color = $defaults['accent_color'];
			add_settings_error($config->get_option_name(), 'mws_invalid_color', __('Accent color was invalid and has been reset.', 'morgao-webring-signature'));
		}

		if ($remote_url !== '') {
			$remote_url = esc_url_raw($remote_url);
			$parsed     = wp_parse_url($remote_url);

			if (! $parsed || empty($parsed['scheme']) || ! in_array($parsed['scheme'], array('http', 'https'), true)) {
				$remote_url = '';
				add_settings_error($config->get_option_name(), 'mws_invalid_remote_url', __('Remote source URL must be a valid HTTP or HTTPS URL.', 'morgao-webring-signature'));
			}
		}

		if ($remote_source && $remote_url === '') {
			$remote_source = false;
			add_settings_error($config->get_option_name(), 'mws_missing_remote_url', __('Remote source was disabled because no valid URL was provided.', 'morgao-webring-signature'));
		}

		if ($hub_mode_enabled && $hub_url === '') {
			$hub_url = home_url('/');
		}

		if ($hub_url !== '') {
			$hub_url = esc_url_raw($hub_url);
			$parsed  = wp_parse_url($hub_url);

			if (! $parsed || empty($parsed['scheme']) || ! in_array($parsed['scheme'], array('http', 'https'), true)) {
				$hub_url = '';
				add_settings_error($config->get_option_name(), 'mws_invalid_hub_url', __('Hub URL must be a valid HTTP or HTTPS URL.', 'morgao-webring-signature'));
			}
		}

		$hub_secret = sanitize_text_field($hub_secret);

		if ($hub_auto_register && $hub_url === '') {
			$hub_auto_register = false;
			add_settings_error($config->get_option_name(), 'mws_missing_hub_url', __('Auto-registration was disabled because no valid hub URL was provided.', 'morgao-webring-signature'));
		}

		if ($github_repo !== '') {
			$github_repo = sanitize_text_field($github_repo);

			if (! preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $github_repo)) {
				$github_repo = '';
				add_settings_error($config->get_option_name(), 'mws_invalid_github_repo', __('GitHub repository must use the format owner/repository.', 'morgao-webring-signature'));
			}
		}

		$github_asset = sanitize_file_name($github_asset);

		if ($github_asset === '' || substr($github_asset, -4) !== '.zip') {
			$github_asset = $defaults['github_asset_name'];
			add_settings_error($config->get_option_name(), 'mws_invalid_github_asset', __('GitHub release asset must be a ZIP file name.', 'morgao-webring-signature'));
		}

		if ($github_updates && $github_repo === '') {
			$github_updates = false;
			add_settings_error($config->get_option_name(), 'mws_missing_github_repo', __('GitHub updates were disabled because no valid repository was provided.', 'morgao-webring-signature'));
		}

		if ($give_url !== '') {
			$give_url = esc_url_raw($give_url);
			$parsed   = wp_parse_url($give_url);

			if (! $parsed || empty($parsed['scheme']) || ! in_array($parsed['scheme'], array('http', 'https'), true)) {
				$give_url = '';
				add_settings_error($config->get_option_name(), 'mws_invalid_give_url', __('Give URL must be a valid HTTP or HTTPS URL.', 'morgao-webring-signature'));
			}
		}

		if ($give_label === '') {
			$give_label = $defaults['give_label'];
		}

		if ($show_give_button && $give_url === '') {
			$show_give_button = false;
			add_settings_error($config->get_option_name(), 'mws_missing_give_url', __('Give button was disabled because no valid Give URL was provided.', 'morgao-webring-signature'));
		}

		return array(
			'current_site_id'   => $current_site_id,
			'use_remote_source' => $remote_source,
			'remote_source_url' => $remote_url,
			'accent_color'      => $accent_color,
			'open_in_new_tab'   => $open_in_new_tab,
			'hub_mode_enabled'  => $hub_mode_enabled,
			'hub_allow_registrations' => $hub_allow_registrations,
			'hub_url'           => $hub_url,
			'hub_secret'        => $hub_secret,
			'hub_auto_register' => $hub_auto_register,
			'use_github_updates'=> $github_updates,
			'github_repository' => $github_repo,
			'github_asset_name' => $github_asset,
			'give_url'          => $give_url,
			'give_label'        => $give_label,
			'show_give_button'  => $show_give_button,
		);
	}

	public function validate_sites($sites) {
		if (! is_array($sites)) {
			return new WP_Error('mws_invalid_sites_payload', __('Site registry must be an array.', 'morgao-webring-signature'));
		}

		$normalized = array();
		$seen_ids   = array();

		foreach ($sites as $site) {
			if (! is_array($site)) {
				continue;
			}

			$id   = sanitize_key($site['id'] ?? '');
			$name = sanitize_text_field($site['name'] ?? '');
			$url  = esc_url_raw(trim((string) ($site['url'] ?? '')));

			if ($id === '' || $name === '' || $url === '') {
				continue;
			}

			$parsed = wp_parse_url($url);

			if (! $parsed || empty($parsed['scheme']) || ! in_array($parsed['scheme'], array('http', 'https'), true)) {
				continue;
			}

			if (isset($seen_ids[ $id ])) {
				continue;
			}

			$seen_ids[ $id ] = true;
			$normalized[]    = array(
				'id'   => $id,
				'name' => $name,
				'url'  => untrailingslashit($url),
			);
		}

		if (empty($normalized)) {
			return new WP_Error('mws_empty_sites', __('No valid sites were found in the registry.', 'morgao-webring-signature'));
		}

		return array_values($normalized);
	}
}
