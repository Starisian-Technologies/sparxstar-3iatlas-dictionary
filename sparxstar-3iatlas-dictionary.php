<?php
namespace Starisian;

/**
 * Plugin Name:       SPARXSTAR 3iAtlas Dictionary
 * Plugin URI:        https://www.starisian.com/
 * Description:       A WordPress plugin for 3iAtlas Dictionary management with SCF and WPGraphQL integration.
 * Version:           1.0.0
 * Author:            Starisian Technologies
 * Author URI:        https://www.starisian.com/
 * Contributor:       Max Barrett
 * License:           Proprietary
 * License URI:
 * Text Domain:       sparxstar-3iatlas-dictionary
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Tested up to:      6.4
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!defined('SPARXSTAR_3IATLAS_PATH')) {
	define('SPARXSTAR_3IATLAS_PATH', plugin_dir_path(__FILE__));
}
if (!defined('SPARXSTAR_3IATLAS_URL')) {
	define('SPARXSTAR_3IATLAS_URL', plugin_dir_url(__FILE__));
}
if (!defined('SPARXSTAR_3IATLAS_VERSION')) {
	define('SPARXSTAR_3IATLAS_VERSION', '1.0.0');
}
if (!defined('SPARXSTAR_3IATLAS_NAMESPACE')) {
	define('SPARXSTAR_3IATLAS_NAMESPACE', 'Starisian\\src\\');
}

use Starisian\src\includes\Autoloader;

if (file_exists(SPARXSTAR_3IATLAS_PATH . 'src/includes/Autoloader.php')) {
	require_once SPARXSTAR_3IATLAS_PATH . 'src/includes/Autoloader.php';
	Autoloader::register();
} else {
	add_action('admin_notices', function (): void {
		echo '<div class="error"><p>' . esc_html__('Critical file Autoloader.php is missing.', 'sparxstar-3iatlas-dictionary') . '</p></div>';
	});
	return;
}

// Load the orchestrator
if (file_exists(SPARXSTAR_3IATLAS_PATH . 'Sparxstar3IAtlasDictionary.php')) {
	require_once SPARXSTAR_3IATLAS_PATH . 'Sparxstar3IAtlasDictionary.php';
}

/**
 * Check if required plugins are active
 */
function sparxstar_3iatlas_check_dependencies(): bool {
	$required_plugins = [
		'Smart Custom Fields' => 'smart-custom-fields/smart-custom-fields.php',
		'WPGraphQL' => 'wp-graphql/wp-graphql.php',
	];

	$missing_plugins = [];
	foreach ($required_plugins as $name => $path) {
		if (!is_plugin_active($path) && !is_plugin_active_for_network($path)) {
			$missing_plugins[] = $name;
		}
	}

	if (!empty($missing_plugins)) {
		add_action('admin_notices', function () use ($missing_plugins): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__('SPARXSTAR 3iAtlas Dictionary requires the following plugins to be installed and activated: ', 'sparxstar-3iatlas-dictionary');
			echo esc_html(implode(', ', $missing_plugins));
			echo '</p></div>';
		});
		return false;
	}

	return true;
}

// Hooks and initialization
register_activation_hook(__FILE__, ['Starisian\Sparxstar3IAtlasDictionary', 'activate']);
register_deactivation_hook(__FILE__, ['Starisian\Sparxstar3IAtlasDictionary', 'deactivate']);
register_uninstall_hook(__FILE__, ['Starisian\Sparxstar3IAtlasDictionary', 'uninstall']);

add_action('plugins_loaded', function (): void {
	// Check dependencies first
	if (!function_exists('is_plugin_active')) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	
	if (sparxstar_3iatlas_check_dependencies()) {
		\Starisian\Sparxstar3IAtlasDictionary::run();
	}
});
