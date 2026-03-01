<?php

if (! defined('ABSPATH')) {
	exit;
}

if (! function_exists('et_get_footer_credits')) {
	function et_get_footer_credits() {
		$credits = '';

		if (function_exists('et_get_option')) {
			$credits = (string) et_get_option('custom_footer_credits', '');
		}

		if ($credits === '') {
			$credits = '&copy; ' . esc_html(get_bloginfo('name')) . ' ' . esc_html(gmdate('Y'));
		}

		return do_shortcode($credits);
	}
}
