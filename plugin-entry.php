<?php
namespace Starisian;

/**
 * Plugin Name:       Plugin Name Placeholder
 * Plugin URI:        https://example.com/
 * Description:       A description of what this plugin does, including its strategic value or AI-driven functionality.
 * Version:           1.0.0
 * Author:            Starisian Technologies
 * Author URI:        https://www.starisian.com/
 * Contributor:       Max Barrett
 * License:           Proprietary
 * License URI:
 * Text Domain:       plugin-textdomain
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Tested up to:      6.4
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!defined('STARISIAN_PATH')) {
	define('STARISIAN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('STARISIAN_URL')) {
	define('STARISIAN_URL', plugin_dir_url(__FILE__));
}
if (!defined('STARISIAN_VERSION')) {
	define('STARISIAN_VERSION', '1.0.0');
}
if (!defined('STARISIAN_NAMESPACE')) {
	define('STARISIAN_NAMESPACE', 'Starisian\\src\\');
}

use Starisian\src\includes\Autoloader;

if (file_exists(STARISIAN_PATH . 'src/includes/Autoloader.php')) {
	require_once STARISIAN_PATH . 'src/includes/Autoloader.php';
	Autoloader::register();
} else {
	add_action('admin_notices', function (): void {
		echo '<div class="error"><p>' . esc_html__('Critical file Autoloader.php is missing.', 'plugin-textdomain') . '</p></div>';
	});
	return;
}

final class PluginCoreTemplate
{
	const VERSION = STARISIAN_VERSION;
	const MINIMUM_PHP_VERSION = '8.2';
	const MINIMUM_WP_VERSION = '6.4';

	private static ?PluginCoreTemplate $instance = null;
	private string $pluginPath;
	private string $pluginUrl;
	private string $version;
	private $core;

	private function __construct()
	{
		$this->pluginPath = STARISIAN_PATH;
		$this->pluginUrl = STARISIAN_URL;
		$this->version = self::VERSION;

		if (!$this->check_compatibility()) {
			add_action('admin_notices', [$this, 'admin_notice_compatibility']);
			return;
		}
		$this->load_textdomain();
		$this->load_dependencies();
	}

	public static function get_instance(): PluginCoreTemplate
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function load_dependencies(): void {
		if (!class_exists('Starisian\src\core\PluginCore', false)) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('Starisian Plugin: Main class PluginCore not found.');
                        }
                        return;
		}
		$this->core = \Starisian\src\core\PluginCore::getInstance();
	}

	private function check_compatibility(): bool
	{
		if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '<')) {
			return false;
		}
		global $wp_version;
		if (version_compare($wp_version, self::MINIMUM_WP_VERSION, '<')) {
			return false;
		}
		return true;
	}

	public function admin_notice_compatibility(): void
	{
		echo '<div class="notice notice-error"><p>';
		if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '<')) {
			echo esc_html__('This plugin requires PHP version ' . self::MINIMUM_PHP_VERSION . ' or higher.', 'plugin-textdomain') . '<br>';
		}
		if (version_compare($GLOBALS['wp_version'], self::MINIMUM_WP_VERSION, '<')) {
			echo esc_html__('This plugin requires WordPress version ' . self::MINIMUM_WP_VERSION . ' or higher.', 'plugin-textdomain');
		}
		echo '</p></div>';
	}

	private function load_textdomain(): void
	{
		load_plugin_textdomain('plugin-textdomain', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	private function __clone(): void
	{
		_doing_it_wrong(__FUNCTION__, esc_html__('Cloning is not allowed.', 'plugin-textdomain'), self::VERSION);
	}

        public function __wakeup(): void
        {
                _doing_it_wrong(__FUNCTION__, esc_html__('Unserializing is not allowed.', 'plugin-textdomain'), self::VERSION);
        }

        public function __sleep(): array
        {
                _doing_it_wrong(__FUNCTION__, esc_html__('Serialization is not allowed.', 'plugin-textdomain'), self::VERSION);
                return [];
        }

	private function __destruct()
	{
		_doing_it_wrong(__FUNCTION__, esc_html__('Destruction is not allowed.', 'plugin-textdomain'), self::VERSION);
	}

	public function __call($name, $arguments): void
	{
		_doing_it_wrong(__FUNCTION__, esc_html__('Calling undefined methods is not allowed.', 'plugin-textdomain'), self::VERSION);
	}

	public static function run(): void
	{
		if (!isset($GLOBALS['StarisianPlugin']) || !$GLOBALS['StarisianPlugin'] instanceof self) {
			$GLOBALS['StarisianPlugin'] = self::get_instance();
		}
	}

	public static function activate(): void
	{
		flush_rewrite_rules();
	}

	public static function deactivate(): void
	{
		flush_rewrite_rules();
	}

	public static function uninstall(): void
	{
		// Optional: cleanup logic
	}
}

// Hooks and initialization
register_activation_hook(__FILE__, ['Starisian\PluginCoreTemplate', 'activate']);
register_deactivation_hook(__FILE__, ['Starisian\PluginCoreTemplate', 'deactivate']);
register_uninstall_hook(__FILE__, ['Starisian\PluginCoreTemplate', 'uninstall']);
add_action('plugins_loaded', ['Starisian\PluginCoreTemplate', 'run']);

