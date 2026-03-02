<?php

if (! defined('ABSPATH')) {
	exit;
}

return array(
	'option_name'             => 'mws_settings',
	'version_option_name'     => 'mws_plugin_version',
	'sites_option_name'       => 'mws_sites',
	'setup_option_name'       => 'mws_setup_complete',
	'activation_redirect_key' => 'mws_activation_redirect',
	'remote_cache_key'        => 'mws_remote_sites_',
	'remote_cache_ttl'        => 6 * HOUR_IN_SECONDS,
	'remote_request_timeout'  => 3,
	'remote_request_retries'  => 2,
	'github_release_cache_key'=> 'mws_github_release_',
	'github_release_cache_ttl'=> 30 * MINUTE_IN_SECONDS,
	'github_request_timeout'  => 3,
	'github_request_retries'  => 2,
	'github_api_base_url'     => 'https://api.github.com',
	'hub_request_timeout'     => 3,
	'hub_request_retries'     => 2,
	'hub_registration_ttl'    => DAY_IN_SECONDS,
	'hub_registration_key'    => 'mws_hub_registered_',
	'status_cache_key'        => 'mws_site_status_',
	'status_cache_ttl'        => 15 * MINUTE_IN_SECONDS,
	'status_request_timeout'  => 2,
	'status_request_retries'  => 1,
	'status_cron_hook'        => 'mws_refresh_site_statuses',
	'status_cron_interval'    => 'mws_fifteen_minutes',
	'rate_limit_window'       => MINUTE_IN_SECONDS,
	'rate_limit_max_attempts' => 8,
	'product_name'            => 'Morgao AutoRing',
	'brand_label'             => 'Morgao AutoRing',
	'accent_color'            => '#D86F2D',
	'open_in_new_tab'         => false,
	'use_github_updates'      => true,
	'give_default_label'      => 'Give',
);
