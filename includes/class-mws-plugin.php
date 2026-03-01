<?php

if (! defined('ABSPATH')) {
	exit;
}

final class MWS_Plugin {
	private $config;
	private $registry;
	private $renderer;
	private $admin;
	private $installer;
	private $site_status;
	private $github_updater;
	private $hub;

	public static function boot() {
		$defaults      = include MWS_PLUGIN_DIR . 'config/defaults.php';
		$config        = new MWS_Config($defaults);
		$validator     = new MWS_Validator();
		$installer     = new MWS_Installer($config, $validator);
		$github_updater = new MWS_GitHub_Updater($config);
		$hub           = new MWS_Hub($config, $validator);
		$remote_source = new MWS_Remote_Source($config, $validator);
		$registry      = new MWS_Registry($config, $validator, $remote_source, $hub);
		$site_status   = new MWS_Site_Status($config);
		$renderer      = new MWS_Renderer($config, $registry, $site_status);
		$rate_limiter  = new MWS_Rate_Limiter();
		$admin         = new MWS_Admin($config, $validator, $registry, $renderer, $rate_limiter, $site_status, $hub);
		$plugin        = new self($config, $registry, $renderer, $admin, $installer, $site_status, $github_updater, $hub);

		$plugin->register();
	}

	public static function activate() {
		$defaults  = include MWS_PLUGIN_DIR . 'config/defaults.php';
		$config    = new MWS_Config($defaults);
		$validator = new MWS_Validator();
		$installer = new MWS_Installer($config, $validator);

		$installer->activate();
	}

	public static function deactivate() {
		$defaults = include MWS_PLUGIN_DIR . 'config/defaults.php';
		$config   = new MWS_Config($defaults);
		$hook     = $config->get('status_cron_hook');
		$next_run = wp_next_scheduled($hook);

		while ($next_run) {
			wp_unschedule_event($next_run, $hook);
			$next_run = wp_next_scheduled($hook);
		}
	}

	public function __construct(MWS_Config $config, MWS_Registry $registry, MWS_Renderer $renderer, MWS_Admin $admin, MWS_Installer $installer, MWS_Site_Status $site_status, MWS_GitHub_Updater $github_updater, MWS_Hub $hub) {
		$this->config    = $config;
		$this->registry  = $registry;
		$this->renderer  = $renderer;
		$this->admin     = $admin;
		$this->installer = $installer;
		$this->site_status = $site_status;
		$this->github_updater = $github_updater;
		$this->hub = $hub;
	}

	public function register() {
		add_filter('cron_schedules', array($this, 'register_cron_schedules'));
		add_filter('pre_set_site_transient_update_plugins', array($this, 'inject_github_update'));
		add_filter('plugins_api', array($this, 'provide_github_plugin_info'), 10, 3);
		add_filter('http_request_args', array($this, 'filter_http_request_args'), 10, 2);
		add_filter('plugin_row_meta', array($this, 'add_plugin_row_meta'), 10, 2);
		add_action('admin_init', array($this, 'maybe_redirect_to_setup'));
		add_action('admin_init', array($this, 'maybe_auto_register_with_hub'));
		add_action('plugins_loaded', array($this, 'maybe_upgrade'));
		add_action('rest_api_init', array($this, 'register_hub_routes'));
		add_action($this->config->get('status_cron_hook'), array($this, 'refresh_site_statuses'));
		add_action('init', array($this, 'register_shortcode'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
		add_action('template_redirect', array($this, 'handle_public_actions'));
		add_filter(
			'plugin_action_links_' . plugin_basename(MWS_PLUGIN_FILE),
			array($this, 'add_plugin_action_links')
		);

		if (is_admin()) {
			$this->admin->register();
		}
	}

	public function maybe_upgrade() {
		$this->installer->upgrade_if_needed();
		self::ensure_cron_schedule($this->config);
	}

	public function maybe_redirect_to_setup() {
		if (! current_user_can('manage_options')) {
			return;
		}

		if (! get_transient($this->config->get_activation_redirect_key())) {
			return;
		}

		delete_transient($this->config->get_activation_redirect_key());

		if (wp_doing_ajax()) {
			return;
		}

		wp_safe_redirect(admin_url('options-general.php?page=morgao-webring-signature&mws_setup=1'));
		exit;
	}

	public function maybe_auto_register_with_hub() {
		if (! is_admin()) {
			return;
		}

		$this->hub->maybe_register_current_site(false);
	}

	public function register_hub_routes() {
		$this->hub->register_routes();
	}

	public function inject_github_update($transient) {
		return $this->github_updater->inject_update($transient);
	}

	public function provide_github_plugin_info($result, $action, $args) {
		return $this->github_updater->provide_plugin_info($result, $action, $args);
	}

	public function filter_http_request_args($args, $url) {
		return $this->github_updater->filter_http_request_args($args, $url);
	}

	public function register_cron_schedules($schedules) {
		$schedules[ $this->config->get('status_cron_interval') ] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __('Every 15 Minutes', 'morgao-webring-signature'),
		);

		return $schedules;
	}

	public function register_shortcode() {
		add_shortcode('morgao_webring_signature', array($this, 'render_shortcode'));
	}

	public function enqueue_frontend_assets() {
		$settings = $this->registry->get_effective_settings();

		wp_enqueue_style('mws-signature', MWS_PLUGIN_URL . 'assets/css/mws-signature.css', array(), MWS_PLUGIN_VERSION);
		wp_add_inline_style(
			'mws-signature',
			sprintf(':root{--mws-accent:%s;}', esc_html($settings['accent_color'] ?: $this->config->get('accent_color')))
		);
	}

	public function render_shortcode() {
		return $this->renderer->render_signature();
	}

	public function handle_public_actions() {
		if (! isset($_GET['mws-action'])) {
			return;
		}

		$action  = sanitize_key(wp_unslash($_GET['mws-action']));
		$context = $this->registry->get_context();

		if (empty($context)) {
			return;
		}

		if ($action === 'directory') {
			status_header(200);
			nocache_headers();
			echo $this->renderer->render_directory_page();
			exit;
		}

		$target_map = array(
			'prev'   => $context['previous']['url'],
			'next'   => $context['next']['url'],
			'random' => $context['random']['url'],
		);

		if (! isset($target_map[ $action ])) {
			return;
		}

		wp_redirect(esc_url_raw($target_map[ $action ]), 302, 'Morgao AutoRing');
		exit;
	}

	public function add_plugin_action_links($links) {
		$settings_url = admin_url('options-general.php?page=morgao-webring-signature');
		$settings     = $this->config->get_settings();
		$action_links = array(
			sprintf(
				'<a href="%s">%s</a>',
				esc_url($settings_url),
				esc_html__('Settings', 'morgao-webring-signature')
			),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url($settings_url),
				esc_html__('Open Admin', 'morgao-webring-signature')
			),
		);

		if (! empty($settings['show_give_button']) && ! empty($settings['give_url'])) {
			$action_links[] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url($settings['give_url']),
				esc_html($settings['give_label'])
			);
		}

		return array_merge($action_links, $links);
	}

	public function add_plugin_row_meta($links, $file) {
		if ($file !== MWS_PLUGIN_BASENAME) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(admin_url('options-general.php?page=morgao-webring-signature')),
			esc_html__('Configure AutoRing', 'morgao-webring-signature')
		);

		return $links;
	}

	public function refresh_site_statuses() {
		$sites = $this->registry->get_sites();

		if (empty($sites)) {
			return;
		}

		$this->site_status->refresh_sites($sites);
	}

	private static function ensure_cron_schedule(MWS_Config $config) {
		$hook = $config->get('status_cron_hook');

		if (! wp_next_scheduled($hook)) {
			wp_schedule_event(time() + MINUTE_IN_SECONDS, $config->get('status_cron_interval'), $hook);
		}
	}
}
