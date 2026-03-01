<?php
/**
 * Plugin Name: Morgao AutoRing
 * Plugin URI: https://morgao.com
 * Description: Generates a footer-ready webring signature for Morgao sites, with local admin management, optional GitHub updates, and safe fallback rendering.
 * Version: 0.1.0
 * Author: Morgao
 * Author URI: https://morgao.com
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: morgao-webring-signature
 * Update URI: https://github.com/redb/Webring
 */

if (! defined('ABSPATH')) {
	exit;
}

define('MWS_PLUGIN_VERSION', '0.1.0');
define('MWS_PLUGIN_FILE', __FILE__);
define('MWS_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('MWS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MWS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MWS_PLUGIN_SLUG', 'morgao-webring-signature');

$mws_includes = array(
	'includes/class-mws-logger.php',
	'includes/class-mws-config.php',
	'includes/class-mws-validator.php',
	'includes/class-mws-rate-limiter.php',
	'includes/class-mws-installer.php',
	'includes/class-mws-github-updater.php',
	'includes/class-mws-remote-source.php',
	'includes/class-mws-registry.php',
	'includes/class-mws-site-status.php',
	'includes/class-mws-renderer.php',
	'includes/class-mws-admin.php',
	'includes/class-mws-plugin.php',
);

foreach ($mws_includes as $mws_include) {
	require_once MWS_PLUGIN_DIR . $mws_include;
}

register_activation_hook(MWS_PLUGIN_FILE, array('MWS_Plugin', 'activate'));
register_deactivation_hook(MWS_PLUGIN_FILE, array('MWS_Plugin', 'deactivate'));

MWS_Plugin::boot();
