<?php

if (! defined('ABSPATH')) {
	exit;
}

final class MWS_Renderer {
	private $config;
	private $registry;
	private $site_status;

	public function __construct(MWS_Config $config, MWS_Registry $registry, MWS_Site_Status $site_status) {
		$this->config      = $config;
		$this->registry    = $registry;
		$this->site_status = $site_status;
	}

	public function render_signature() {
		$context = $this->registry->get_context();

		if (empty($context['current'])) {
			$fallback_label = get_bloginfo('name');
			$fallback_label = is_string($fallback_label) && $fallback_label !== '' ? $fallback_label : __('Webring', 'morgao-webring-signature');

			return sprintf('<span class="mws-signature mws-signature--fallback">%s</span>', esc_html($fallback_label));
		}

		$settings = $context['settings'];
		$label    = ! empty($settings['shared_signature_label']) ? $settings['shared_signature_label'] : $context['current']['name'];
		$target   = ! empty($settings['open_in_new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '';
		$home     = home_url('/');
		$prev     = add_query_arg('mws-action', 'prev', $home);
		$next     = add_query_arg('mws-action', 'next', $home);
		$random   = add_query_arg('mws-action', 'random', $home);
		$dir      = add_query_arg('mws-action', 'directory', $home);

		$items = array(
			sprintf('<span class="mws-signature__current">%s</span>', esc_html($label)),
			sprintf('<a class="mws-signature__link" href="%s"%s>%s</a>', esc_url($prev), $target, esc_html__('Previous', 'morgao-webring-signature')),
			sprintf('<a class="mws-signature__link" href="%s"%s>%s</a>', esc_url($dir), $target, esc_html__('Index', 'morgao-webring-signature')),
			sprintf('<a class="mws-signature__link" href="%s"%s>%s</a>', esc_url($next), $target, esc_html__('Next', 'morgao-webring-signature')),
		);

		if ($context['count'] > 2) {
			$items[] = sprintf('<a class="mws-signature__link" href="%s"%s>%s</a>', esc_url($random), $target, esc_html__('Random', 'morgao-webring-signature'));
		}

		return sprintf('<span class="mws-signature">%s</span>', implode('<span class="mws-signature__divider" aria-hidden="true">/</span>', $items));
	}

	public function render_directory_page() {
		$context = $this->registry->get_context();
		$brand   = $this->config->get('brand_label');
		$items   = array();
		$lang    = get_bloginfo('language');
		$statuses = $this->site_status->get_status_map($context['sites']);
		$settings = $context['settings'];

		foreach ($context['sites'] as $index => $site) {
			$current_class = $index === $context['currentIndex'] ? ' mws-directory__item--current' : '';
			$status        = $statuses[ $site['id'] ] ?? array('state' => 'offline', 'status_code' => 0, 'checked_at' => '', 'error' => '');
			$status_class  = $status['state'] === 'online' ? ' mws-directory__status-dot--online' : ' mws-directory__status-dot--offline';
			$status_title  = $status['state'] === 'online'
				? sprintf(__('Online%s', 'morgao-webring-signature'), ! empty($status['status_code']) ? ' (' . (int) $status['status_code'] . ')' : '')
				: sprintf(__('Offline%s', 'morgao-webring-signature'), ! empty($status['error']) ? ' - ' . $status['error'] : '');
			$items[]       = sprintf(
				'<li class="mws-directory__item%s"><a href="%s" rel="noopener noreferrer"><span class="mws-directory__site"><span class="mws-directory__status-dot%s" aria-hidden="true"></span><span>%s</span></span><span class="screen-reader-text">%s</span></a></li>',
				esc_attr($current_class),
				esc_url($site['url']),
				esc_attr($status_class),
				esc_html($site['name'])
				,
				esc_html($status_title)
			);
		}

		$give_html = '';

		if (! empty($settings['show_give_button']) && ! empty($settings['give_url'])) {
			$give_html = sprintf(
				'<p class="mws-directory__give"><a class="mws-directory__give-link" href="%s" rel="noopener noreferrer"%s>%s</a></p>',
				esc_url($settings['give_url']),
				! empty($settings['open_in_new_tab']) ? ' target="_blank"' : '',
				esc_html($settings['give_label'])
			);
		}

		return sprintf(
			'<!DOCTYPE html><html %1$s><head><meta charset="%2$s"><meta name="viewport" content="width=device-width, initial-scale=1"><title>%3$s</title><style>%4$s</style></head><body><main class="mws-directory"><p class="mws-directory__eyebrow">%5$s</p><h1>%6$s</h1><p class="mws-directory__intro">%7$s</p><ul class="mws-directory__list">%8$s</ul><p class="mws-directory__actions"><a href="%9$s">%10$s</a><span aria-hidden="true"> · </span><a href="%11$s">%12$s</a></p>%13$s</main></body></html>',
			$lang !== '' ? 'lang="' . esc_attr($lang) . '"' : '',
			esc_attr(get_bloginfo('charset')),
			esc_html($brand),
			$this->get_directory_styles($settings),
			esc_html($brand),
			esc_html__('Webring directory', 'morgao-webring-signature'),
			esc_html__('Hand-picked sites connected by a shared signature.', 'morgao-webring-signature'),
			implode('', $items),
			esc_url(add_query_arg('mws-action', 'prev', home_url('/'))),
			esc_html__('Previous site', 'morgao-webring-signature'),
			esc_url(add_query_arg('mws-action', 'next', home_url('/'))),
			esc_html__('Next site', 'morgao-webring-signature'),
			$give_html
		);
	}

	private function get_directory_styles(array $settings) {
		$accent   = $settings['accent_color'] ?: $this->config->get('accent_color');

		return sprintf(
			'body{margin:0;background:#111;color:#f6efe8;font-family:Georgia,serif}a{color:inherit}.screen-reader-text{position:absolute;left:-9999px}.mws-directory{max-width:720px;margin:0 auto;padding:64px 24px}.mws-directory__eyebrow{letter-spacing:.14em;text-transform:uppercase;color:%1$s;font:600 12px/1.4 sans-serif}.mws-directory h1{font-size:clamp(2rem,5vw,4rem);margin:.25rem 0 1rem}.mws-directory__intro{max-width:42rem;color:#d7ccc2}.mws-directory__list{list-style:none;padding:0;margin:2rem 0;border-top:1px solid rgba(255,255,255,.14)}.mws-directory__item{border-bottom:1px solid rgba(255,255,255,.14)}.mws-directory__item a{display:flex;align-items:center;justify-content:space-between;padding:1rem 0;text-decoration:none}.mws-directory__item--current a{color:%1$s}.mws-directory__site{display:inline-flex;align-items:center;gap:.75rem}.mws-directory__status-dot{width:.6rem;height:.6rem;border-radius:50%%;display:inline-block;box-shadow:0 0 0 1px rgba(255,255,255,.08)}.mws-directory__status-dot--online{background:#3ecf6d}.mws-directory__status-dot--offline{background:#d94b4b}.mws-directory__actions{color:#d7ccc2}.mws-directory__give{margin-top:1.5rem}.mws-directory__give-link{display:inline-flex;padding:.45rem .85rem;border:1px solid rgba(255,255,255,.18);border-radius:999px;text-decoration:none}',
			esc_html($accent)
		);
	}
}
