<?php

if (! defined('ABSPATH')) {
	exit;
}

final class MWS_Admin {
	private $config;
	private $validator;
	private $registry;
	private $renderer;
	private $rate_limiter;
	private $site_status;

	public function __construct(MWS_Config $config, MWS_Validator $validator, MWS_Registry $registry, MWS_Renderer $renderer, MWS_Rate_Limiter $rate_limiter, MWS_Site_Status $site_status) {
		$this->config       = $config;
		$this->validator    = $validator;
		$this->registry     = $registry;
		$this->renderer     = $renderer;
		$this->rate_limiter = $rate_limiter;
		$this->site_status  = $site_status;
	}

	public function register() {
		add_action('admin_menu', array($this, 'add_menu'));
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('admin_post_mws_save_sites', array($this, 'handle_save_sites'));
		add_action('admin_post_mws_refresh_site_statuses', array($this, 'handle_refresh_site_statuses'));
		add_action('admin_post_mws_refresh_remote_sites', array($this, 'handle_refresh_remote_sites'));
	}

	public function add_menu() {
		add_options_page(
			__('Morgao AutoRing', 'morgao-webring-signature'),
			__('Morgao AutoRing', 'morgao-webring-signature'),
			'manage_options',
			'morgao-webring-signature',
			array($this, 'render_page')
		);
	}

	public function register_settings() {
		register_setting(
			'mws_settings_group',
			$this->config->get_option_name(),
			array(
				'type'              => 'array',
				'sanitize_callback' => array($this, 'sanitize_settings'),
				'default'           => $this->config->get_default_settings(),
			)
		);

		add_settings_section(
			'mws_main_section',
			__('Signature settings', 'morgao-webring-signature'),
			'__return_false',
			'morgao-webring-signature'
		);

		add_settings_field(
			'current_site_id',
			__('Current site', 'morgao-webring-signature'),
			array($this, 'render_current_site_field'),
			'morgao-webring-signature',
			'mws_main_section'
		);

		add_settings_field(
			'accent_color',
			__('Accent color', 'morgao-webring-signature'),
			array($this, 'render_accent_color_field'),
			'morgao-webring-signature',
			'mws_main_section'
		);

		add_settings_field(
			'open_in_new_tab',
			__('Open links in new tab', 'morgao-webring-signature'),
			array($this, 'render_open_in_new_tab_field'),
			'morgao-webring-signature',
			'mws_main_section'
		);

		add_settings_field(
			'use_remote_source',
			__('Remote registry', 'morgao-webring-signature'),
			array($this, 'render_remote_source_field'),
			'morgao-webring-signature',
			'mws_main_section'
		);

		add_settings_field(
			'use_github_updates',
			__('GitHub updates', 'morgao-webring-signature'),
			array($this, 'render_github_updates_field'),
			'morgao-webring-signature',
			'mws_main_section'
		);

		add_settings_field(
			'show_give_button',
			__('Give button', 'morgao-webring-signature'),
			array($this, 'render_give_field'),
			'morgao-webring-signature',
			'mws_main_section'
		);
	}

	public function sanitize_settings($input) {
		return $this->validator->validate_settings($input, $this->config);
	}

	public function enqueue_assets($hook) {
		if ($hook !== 'settings_page_morgao-webring-signature') {
			return;
		}

		wp_enqueue_style('mws-signature', MWS_PLUGIN_URL . 'assets/css/mws-signature.css', array(), MWS_PLUGIN_VERSION);
		wp_enqueue_style('mws-admin', MWS_PLUGIN_URL . 'assets/css/mws-admin.css', array(), MWS_PLUGIN_VERSION);
		wp_enqueue_script('mws-admin', MWS_PLUGIN_URL . 'assets/js/mws-admin.js', array(), MWS_PLUGIN_VERSION, true);
	}

	public function handle_save_sites() {
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to do that.', 'morgao-webring-signature'));
		}

		check_admin_referer('mws_save_sites');

		if (! $this->rate_limiter->allow('admin_save_sites', (int) $this->config->get('rate_limit_max_attempts'), (int) $this->config->get('rate_limit_window'))) {
			$this->redirect_with_notice('rate_limited');
		}

		$raw_sites = isset($_POST['mws_sites']) ? wp_unslash($_POST['mws_sites']) : array();
		$sites     = array();

		if (is_array($raw_sites)) {
			foreach ($raw_sites as $site) {
				$sites[] = array(
					'id'   => is_array($site) ? ($site['id'] ?? '') : '',
					'name' => is_array($site) ? ($site['name'] ?? '') : '',
					'url'  => is_array($site) ? ($site['url'] ?? '') : '',
				);
			}
		}

		$result = $this->registry->save_admin_sites($sites);

		if (is_wp_error($result)) {
			MWS_Logger::log('sites_save_failed', array('error' => $result->get_error_message()), 'warning');
			$this->redirect_with_notice('sites_save_failed');
		}

		$this->redirect_with_notice('sites_saved');
	}

	public function handle_refresh_site_statuses() {
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to do that.', 'morgao-webring-signature'));
		}

		check_admin_referer('mws_refresh_site_statuses');

		if (! $this->rate_limiter->allow('admin_refresh_site_statuses', (int) $this->config->get('rate_limit_max_attempts'), (int) $this->config->get('rate_limit_window'))) {
			$this->redirect_with_notice('rate_limited');
		}

		$sites = $this->registry->get_sites();

		if (empty($sites)) {
			$this->redirect_with_notice('status_refresh_failed');
		}

		$this->site_status->refresh_sites($sites);
		$this->redirect_with_notice('status_refresh_success');
	}

	public function handle_refresh_remote_sites() {
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to do that.', 'morgao-webring-signature'));
		}

		check_admin_referer('mws_refresh_remote_sites');

		if (! $this->rate_limiter->allow('admin_refresh_remote_sites', (int) $this->config->get('rate_limit_max_attempts'), (int) $this->config->get('rate_limit_window'))) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => 'morgao-webring-signature',
						'mws_notice'  => 'rate_limited',
					),
					admin_url('options-general.php')
				)
			);
			exit;
		}

		$sites = $this->registry->refresh_remote_sites();

		if (is_wp_error($sites)) {
			MWS_Logger::log('remote_refresh_failed', array('error' => $sites->get_error_message()), 'warning');
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => 'morgao-webring-signature',
						'mws_notice' => 'refresh_failed',
					),
					admin_url('options-general.php')
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'morgao-webring-signature',
					'mws_notice' => 'refresh_success',
				),
				admin_url('options-general.php')
			)
		);
		exit;
	}

	public function render_page() {
		$settings = $this->config->get_settings();
		$snippet  = $this->renderer->render_signature();
		$sites    = $this->registry->get_admin_sites();

		if (is_wp_error($sites)) {
			$sites = array();
		}

		$this->render_notice();
		?>
		<div class="wrap mws-admin">
			<h1><?php echo esc_html($this->config->get('product_name')); ?></h1>
			<p><?php esc_html_e('Generate a footer-ready HTML signature for Divi and keep your Morgao sites linked together.', 'morgao-webring-signature'); ?></p>
			<form method="post" action="options.php" class="mws-admin__card">
				<?php
				settings_fields('mws_settings_group');
				do_settings_sections('morgao-webring-signature');
				submit_button(__('Save settings', 'morgao-webring-signature'));
				?>
			</form>

			<div class="mws-admin__card">
				<h2><?php esc_html_e('Ring sites', 'morgao-webring-signature'); ?></h2>
				<p><?php esc_html_e('Add, edit, and reorder the sites directly from WordPress admin. The first valid saved list becomes the active ring source.', 'morgao-webring-signature'); ?></p>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<input type="hidden" name="action" value="mws_save_sites">
					<?php wp_nonce_field('mws_save_sites'); ?>
					<div class="mws-admin__sites" data-mws-sites>
						<div class="mws-admin__sites-header">
							<span><?php esc_html_e('ID', 'morgao-webring-signature'); ?></span>
							<span><?php esc_html_e('Name', 'morgao-webring-signature'); ?></span>
							<span><?php esc_html_e('URL', 'morgao-webring-signature'); ?></span>
							<span><?php esc_html_e('Action', 'morgao-webring-signature'); ?></span>
						</div>
						<div data-mws-sites-list>
							<?php foreach ($sites as $index => $site) : ?>
								<?php $this->render_site_row($index, $site); ?>
							<?php endforeach; ?>
						</div>
					</div>
					<p>
						<button type="button" class="button button-secondary" data-mws-add-row><?php esc_html_e('Add site', 'morgao-webring-signature'); ?></button>
					</p>
					<?php submit_button(__('Save sites', 'morgao-webring-signature')); ?>
				</form>
				<template data-mws-site-template>
					<?php $this->render_site_row('__INDEX__', array('id' => '', 'name' => '', 'url' => '')); ?>
				</template>
			</div>

			<div class="mws-admin__card">
				<h2><?php esc_html_e('Footer snippet', 'morgao-webring-signature'); ?></h2>
				<p><?php esc_html_e('Paste this HTML into Divi > Theme Options > Edit Footer Credits.', 'morgao-webring-signature'); ?></p>
				<div class="mws-admin__preview"><?php echo wp_kses_post($snippet); ?></div>
				<textarea class="large-text code mws-admin__textarea" rows="8" readonly data-mws-copy-source="signature"><?php echo esc_textarea($snippet); ?></textarea>
				<p><button type="button" class="button button-secondary" data-mws-copy-target="signature"><?php esc_html_e('Copy snippet', 'morgao-webring-signature'); ?></button></p>
			</div>

			<div class="mws-admin__card">
				<h2><?php esc_html_e('Site statuses', 'morgao-webring-signature'); ?></h2>
				<p><?php esc_html_e('The public directory reads cached health checks only. Refresh them here or let WordPress cron refresh them in the background.', 'morgao-webring-signature'); ?></p>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<input type="hidden" name="action" value="mws_refresh_site_statuses">
					<?php wp_nonce_field('mws_refresh_site_statuses'); ?>
					<?php submit_button(__('Refresh statuses', 'morgao-webring-signature'), 'secondary', 'submit', false); ?>
				</form>
			</div>

			<div class="mws-admin__card">
				<h2><?php esc_html_e('Shortcode', 'morgao-webring-signature'); ?></h2>
				<p><code>[morgao_webring_signature]</code></p>
				<p><?php esc_html_e('Useful if a builder area supports shortcodes instead of raw HTML.', 'morgao-webring-signature'); ?></p>
			</div>

			<div class="mws-admin__card">
				<h2><?php esc_html_e('Local registry', 'morgao-webring-signature'); ?></h2>
				<p><?php esc_html_e('Versioned sites live in config/default-sites.php. Keep the ring list in Git, not in the database.', 'morgao-webring-signature'); ?></p>
				<pre class="mws-admin__path"><?php echo esc_html(MWS_PLUGIN_DIR . 'config/default-sites.php'); ?></pre>
			</div>

			<?php if (! empty($settings['use_remote_source']) && ! empty($settings['remote_source_url'])) : ?>
				<div class="mws-admin__card">
					<h2><?php esc_html_e('Remote cache', 'morgao-webring-signature'); ?></h2>
					<p><?php esc_html_e('Refresh the remote registry manually. Failed refreshes never block rendering; the plugin falls back to the local registry.', 'morgao-webring-signature'); ?></p>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<input type="hidden" name="action" value="mws_refresh_remote_sites">
						<?php wp_nonce_field('mws_refresh_remote_sites'); ?>
						<?php submit_button(__('Refresh remote registry', 'morgao-webring-signature'), 'secondary', 'submit', false); ?>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_current_site_field() {
		$settings = $this->config->get_settings();
		$sites    = $this->registry->get_sites();
		?>
		<select name="<?php echo esc_attr($this->config->get_option_name()); ?>[current_site_id]">
			<option value=""><?php esc_html_e('Auto-detect from current domain', 'morgao-webring-signature'); ?></option>
			<?php foreach ($sites as $site) : ?>
				<option value="<?php echo esc_attr($site['id']); ?>" <?php selected($settings['current_site_id'], $site['id']); ?>>
					<?php echo esc_html($site['name'] . ' (' . $site['url'] . ')'); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e('Set this only if the current domain cannot be auto-matched against the registry.', 'morgao-webring-signature'); ?></p>
		<?php
	}

	public function render_accent_color_field() {
		$settings = $this->config->get_settings();
		?>
		<input type="text" class="regular-text code" name="<?php echo esc_attr($this->config->get_option_name()); ?>[accent_color]" value="<?php echo esc_attr($settings['accent_color']); ?>" />
		<p class="description"><?php esc_html_e('Hex color used by the frontend signature and the directory page.', 'morgao-webring-signature'); ?></p>
		<?php
	}

	public function render_open_in_new_tab_field() {
		$settings = $this->config->get_settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr($this->config->get_option_name()); ?>[open_in_new_tab]" value="1" <?php checked(! empty($settings['open_in_new_tab'])); ?> />
			<?php esc_html_e('Open signature links in a new tab.', 'morgao-webring-signature'); ?>
		</label>
		<?php
	}

	public function render_remote_source_field() {
		$settings = $this->config->get_settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr($this->config->get_option_name()); ?>[use_remote_source]" value="1" <?php checked(! empty($settings['use_remote_source'])); ?> />
			<?php esc_html_e('Use a remote JSON registry before falling back to the local file.', 'morgao-webring-signature'); ?>
		</label>
		<p>
			<input type="url" class="regular-text code" name="<?php echo esc_attr($this->config->get_option_name()); ?>[remote_source_url]" value="<?php echo esc_attr($settings['remote_source_url']); ?>" placeholder="https://example.com/webring.json" />
		</p>
		<p class="description"><?php esc_html_e('Accepted payloads: a JSON array of sites or an object with a "sites" array. Requests use a short timeout, 2 retries max, and cached responses.', 'morgao-webring-signature'); ?></p>
		<?php
	}

	public function render_github_updates_field() {
		$settings = $this->config->get_settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr($this->config->get_option_name()); ?>[use_github_updates]" value="1" <?php checked(! empty($settings['use_github_updates'])); ?> />
			<?php esc_html_e('Enable update checks from GitHub Releases.', 'morgao-webring-signature'); ?>
		</label>
		<p>
			<input type="text" class="regular-text code" name="<?php echo esc_attr($this->config->get_option_name()); ?>[github_repository]" value="<?php echo esc_attr($settings['github_repository']); ?>" placeholder="owner/repository" />
		</p>
		<p>
			<input type="text" class="regular-text code" name="<?php echo esc_attr($this->config->get_option_name()); ?>[github_asset_name]" value="<?php echo esc_attr($settings['github_asset_name']); ?>" placeholder="<?php echo esc_attr(MWS_PLUGIN_SLUG . '.zip'); ?>" />
		</p>
		<p class="description"><?php esc_html_e('Use a published GitHub Release containing a ZIP asset with the same plugin folder name. For private repositories, define MWS_GITHUB_TOKEN in wp-config.php.', 'morgao-webring-signature'); ?></p>
		<?php
	}

	public function render_give_field() {
		$settings = $this->config->get_settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr($this->config->get_option_name()); ?>[show_give_button]" value="1" <?php checked(! empty($settings['show_give_button'])); ?> />
			<?php esc_html_e('Show a Give button on the public directory page.', 'morgao-webring-signature'); ?>
		</label>
		<p>
			<input type="url" class="regular-text code" name="<?php echo esc_attr($this->config->get_option_name()); ?>[give_url]" value="<?php echo esc_attr($settings['give_url']); ?>" placeholder="https://buy.stripe.com/..." />
		</p>
		<p>
			<input type="text" class="regular-text code" name="<?php echo esc_attr($this->config->get_option_name()); ?>[give_label]" value="<?php echo esc_attr($settings['give_label']); ?>" placeholder="<?php echo esc_attr($this->config->get('give_default_label')); ?>" />
		</p>
		<p class="description"><?php esc_html_e('Optional monetization CTA. It is only displayed if a valid URL is configured.', 'morgao-webring-signature'); ?></p>
		<?php
	}

	private function render_notice() {
		$notice = isset($_GET['mws_notice']) ? sanitize_key(wp_unslash($_GET['mws_notice'])) : '';

		if ($notice === '') {
			return;
		}

		$map = array(
			'sites_saved'      => array('success', __('Ring sites saved.', 'morgao-webring-signature')),
			'sites_save_failed'=> array('error', __('Sites could not be saved. Check IDs, names, and URLs.', 'morgao-webring-signature')),
			'status_refresh_success' => array('success', __('Site statuses refreshed.', 'morgao-webring-signature')),
			'status_refresh_failed'  => array('error', __('Site statuses could not be refreshed.', 'morgao-webring-signature')),
			'refresh_success' => array('success', __('Remote registry refreshed.', 'morgao-webring-signature')),
			'refresh_failed'  => array('error', __('Remote registry refresh failed. Local registry is still being used.', 'morgao-webring-signature')),
			'rate_limited'    => array('warning', __('Refresh rate limit reached. Wait a minute before trying again.', 'morgao-webring-signature')),
		);

		if (! isset($map[ $notice ])) {
			return;
		}

		list($type, $message) = $map[ $notice ];
		printf('<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr($type), esc_html($message));
	}

	private function render_site_row($index, array $site) {
		?>
		<div class="mws-admin__site-row" data-mws-site-row>
			<input type="text" name="mws_sites[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($site['id'] ?? ''); ?>" placeholder="morgao" />
			<input type="text" name="mws_sites[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($site['name'] ?? ''); ?>" placeholder="Morgao" />
			<input type="url" name="mws_sites[<?php echo esc_attr($index); ?>][url]" value="<?php echo esc_attr($site['url'] ?? ''); ?>" placeholder="https://morgao.com" />
			<button type="button" class="button-link-delete" data-mws-remove-row><?php esc_html_e('Remove', 'morgao-webring-signature'); ?></button>
		</div>
		<?php
	}

	private function redirect_with_notice($notice) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'morgao-webring-signature',
					'mws_notice' => $notice,
				),
				admin_url('options-general.php')
			)
		);
		exit;
	}
}
