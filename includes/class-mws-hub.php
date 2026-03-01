<?php

if (! defined('ABSPATH')) {
	exit;
}

final class MWS_Hub {
	private $config;
	private $validator;

	public function __construct(MWS_Config $config, MWS_Validator $validator) {
		$this->config    = $config;
		$this->validator = $validator;
	}

	public function register_routes() {
		register_rest_route(
			'mws/v1',
			'/sites',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'serve_sites'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'mws/v1',
			'/register',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'register_site'),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function serve_sites() {
		$settings = $this->config->get_settings();

		if (empty($settings['hub_mode_enabled'])) {
			return new WP_Error('mws_hub_disabled', __('This site is not configured as an AutoRing hub.', 'morgao-webring-signature'), array('status' => 404));
		}

		$sites = $this->get_hub_sites();

		return rest_ensure_response(
			array(
				'sites'  => $sites,
				'count'  => count($sites),
				'shared' => $this->get_shared_branding_payload(),
			)
		);
	}

	public function register_site(WP_REST_Request $request) {
		$settings = $this->config->get_settings();

		if (empty($settings['hub_mode_enabled']) || empty($settings['hub_allow_registrations'])) {
			return new WP_Error('mws_hub_registration_closed', __('This hub is not accepting registrations.', 'morgao-webring-signature'), array('status' => 403));
		}

		if (! $this->authorize_registration($request, (string) ($settings['hub_secret'] ?? ''))) {
			return new WP_Error('mws_hub_forbidden', __('Invalid hub secret.', 'morgao-webring-signature'), array('status' => 403));
		}

		$site = array(
			'id'   => $request->get_param('id'),
			'name' => $request->get_param('name'),
			'url'  => $request->get_param('url'),
		);

		$validated = $this->validator->validate_sites(array($site));

		if (is_wp_error($validated)) {
			return new WP_Error('mws_hub_invalid_site', $validated->get_error_message(), array('status' => 400));
		}

		$saved = $this->upsert_hub_site($validated[0]);

		return rest_ensure_response(
			array(
				'registered' => true,
				'site'       => $saved,
			)
		);
	}

	public function maybe_register_current_site($force = false) {
		$settings = $this->config->get_settings();
		$hub_url  = trim((string) ($settings['hub_url'] ?? ''));

		if (! empty($settings['hub_mode_enabled']) || empty($settings['hub_auto_register']) || $hub_url === '') {
			return new WP_Error('mws_hub_register_skipped', __('Auto-registration is not enabled for this site.', 'morgao-webring-signature'));
		}

		$site_url   = untrailingslashit(home_url('/'));
		$cache_key  = $this->config->get_hub_registration_cache_key($hub_url, $site_url);
		$registered = get_transient($cache_key);

		if (! $force && $registered) {
			return array('registered' => true, 'cached' => true);
		}

		$result = $this->post_registration($hub_url, $this->build_current_site_payload(), (string) ($settings['hub_secret'] ?? ''));

		if (is_wp_error($result)) {
			MWS_Logger::log('hub_register_failed', array('hub_url' => $hub_url, 'error' => $result->get_error_message()), 'warning');
			return $result;
		}

		set_transient($cache_key, 1, (int) $this->config->get('hub_registration_ttl'));
		MWS_Logger::log('hub_register_success', array('hub_url' => $hub_url, 'site_url' => $site_url));

		return $result;
	}

	public function get_sites_endpoint_url($hub_url) {
		return untrailingslashit((string) $hub_url) . '/wp-json/mws/v1/sites';
	}

	public function get_register_endpoint_url($hub_url) {
		return untrailingslashit((string) $hub_url) . '/wp-json/mws/v1/register';
	}

	public function build_current_site_payload() {
		$settings = $this->config->get_settings();

		return array(
			'id'   => $settings['current_site_id'] !== '' ? $settings['current_site_id'] : $this->build_current_site_id(),
			'name' => get_bloginfo('name'),
			'url'  => untrailingslashit(home_url('/')),
		);
	}

	private function authorize_registration(WP_REST_Request $request, $secret) {
		if ($secret === '') {
			return true;
		}

		$provided = (string) $request->get_header('x-mws-hub-secret');

		if ($provided === '') {
			$provided = (string) $request->get_param('secret');
		}

		return hash_equals($secret, $provided);
	}

	private function get_hub_sites() {
		$sites = get_option($this->config->get_sites_option_name(), array());
		$sites = $this->validator->validate_sites($sites);

		if (! is_wp_error($sites)) {
			return $sites;
		}

		return array($this->build_current_site_payload());
	}

	private function upsert_hub_site(array $site) {
		$current = get_option($this->config->get_sites_option_name(), array());
		$current = is_array($current) ? $current : array();
		$merged  = array();
		$updated = false;

		foreach ($current as $existing) {
			if (! is_array($existing)) {
				continue;
			}

			$existing_id  = isset($existing['id']) ? sanitize_key($existing['id']) : '';
			$existing_url = isset($existing['url']) ? untrailingslashit(esc_url_raw($existing['url'])) : '';

			if ($existing_id === $site['id'] || $existing_url === $site['url']) {
				$merged[] = $site;
				$updated  = true;
				continue;
			}

			$merged[] = $existing;
		}

		if (! $updated) {
			$merged[] = $site;
		}

		$validated = $this->validator->validate_sites($merged);

		if (is_wp_error($validated)) {
			return $site;
		}

		update_option($this->config->get_sites_option_name(), $validated, false);

		return $site;
	}

	private function post_registration($hub_url, array $payload, $secret) {
		$attempts = max(1, (int) $this->config->get('hub_request_retries') + 1);
		$url      = $this->get_register_endpoint_url($hub_url);
		$last     = null;

		for ($attempt = 1; $attempt <= $attempts; $attempt++) {
			$args = array(
				'timeout'     => (int) $this->config->get('hub_request_timeout'),
				'redirection' => 2,
				'headers'     => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'User-Agent'   => 'MorgaoAutoRing/' . MWS_PLUGIN_VERSION,
				),
				'body'        => wp_json_encode(
					array_merge(
						$payload,
						$secret !== '' ? array('secret' => $secret) : array()
					)
				),
			);

			if ($secret !== '') {
				$args['headers']['X-MWS-Hub-Secret'] = $secret;
			}

			$response = wp_remote_post($url, $args);

			if (is_wp_error($response)) {
				$last = $response;
				continue;
			}

			$status = (int) wp_remote_retrieve_response_code($response);
			$body   = wp_remote_retrieve_body($response);

			if ($status < 200 || $status >= 300) {
				$last = new WP_Error('mws_hub_register_bad_status', sprintf(__('Hub registration returned HTTP %d.', 'morgao-webring-signature'), $status));
				continue;
			}

			$decoded = json_decode($body, true);

			return is_array($decoded) ? $decoded : array('registered' => true);
		}

		return $last instanceof WP_Error ? $last : new WP_Error('mws_hub_register_failed', __('Hub registration failed.', 'morgao-webring-signature'));
	}

	private function build_current_site_id() {
		$host = wp_parse_url(home_url('/'), PHP_URL_HOST);

		if (! is_string($host) || $host === '') {
			return 'site-' . substr(md5(home_url('/')), 0, 8);
		}

		return sanitize_key(str_replace('.', '-', $host));
	}

	private function get_shared_branding_payload() {
		$settings = $this->config->get_settings();

		return $this->validator->sanitize_shared_branding($settings, $this->config);
	}
}
