<?php
namespace Starisian;

/**
 * Class Sparxstar3IAtlasDictionary
 *
 * Main orchestrator class for the SPARXSTAR 3iAtlas Dictionary plugin.
 * This class handles initialization, dependency management, and coordinates
 * all plugin functionality including SCF integration and WPGraphQL schema.
 *
 * @package Starisian\Sparxstar3IAtlasDictionary
 */
final class Sparxstar3IAtlasDictionary
{
	const VERSION = '1.0.0';
	const MINIMUM_PHP_VERSION = '8.2';
	const MINIMUM_WP_VERSION = '6.4';

	private static ?Sparxstar3IAtlasDictionary $instance = null;
	private string $pluginPath;
	private string $pluginUrl;
	private string $version;
	private $core;

	private function __construct()
	{
		$this->pluginPath = SPARXSTAR_3IATLAS_PATH;
		$this->pluginUrl = SPARXSTAR_3IATLAS_URL;
		$this->version = SPARXSTAR_3IATLAS_VERSION;

		if (!$this->check_compatibility()) {
			add_action('admin_notices', [$this, 'admin_notice_compatibility']);
			return;
		}
		$this->load_textdomain();
		$this->load_dependencies();
		$this->enqueue_assets();
	}

	public static function get_instance(): Sparxstar3IAtlasDictionary
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function load_dependencies(): void {
		if (!class_exists('Starisian\src\core\PluginCore', false)) {
			$message = 'SPARXSTAR 3iAtlas Dictionary: Main class PluginCore not found.';
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log($message);
			}
			add_action('admin_notices', function () use ($message): void {
				echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
			});
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
			/* translators: %s: Minimum PHP version required */
			printf(
				esc_html__('This plugin requires PHP version %s or higher.', 'sparxstar-3iatlas-dictionary'),
				esc_html(self::MINIMUM_PHP_VERSION)
			);
			echo '<br>';
		}
		if (version_compare($GLOBALS['wp_version'], self::MINIMUM_WP_VERSION, '<')) {
			/* translators: %s: Minimum WordPress version required */
			printf(
				esc_html__('This plugin requires WordPress version %s or higher.', 'sparxstar-3iatlas-dictionary'),
				esc_html(self::MINIMUM_WP_VERSION)
			);
		}
		echo '</p></div>';
	}

	private function load_textdomain(): void
	{
		load_plugin_textdomain('sparxstar-3iatlas-dictionary', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	private function __clone(): void
	{
		_doing_it_wrong(__FUNCTION__, esc_html__('Cloning is not allowed.', 'sparxstar-3iatlas-dictionary'), self::VERSION);
	}

	public function __wakeup(): void
	{
		_doing_it_wrong(__FUNCTION__, esc_html__('Unserializing is not allowed.', 'sparxstar-3iatlas-dictionary'), self::VERSION);
	}

	public function __sleep(): array
	{
		_doing_it_wrong(__FUNCTION__, esc_html__('Serialization is not allowed.', 'sparxstar-3iatlas-dictionary'), self::VERSION);
		return [];
	}

	public function __call($name, $arguments)
	{
		_doing_it_wrong(__FUNCTION__, esc_html__('Calling undefined methods is not allowed.', 'sparxstar-3iatlas-dictionary'), self::VERSION);
		throw new \BadMethodCallException(
			sprintf('Call to undefined method %s::%s()', __CLASS__, $name)
		);
	}

	public static function run(): void
	{
		if (!isset($GLOBALS['Sparxstar3IAtlasDictionary']) || !$GLOBALS['Sparxstar3IAtlasDictionary'] instanceof self) {
			$GLOBALS['Sparxstar3IAtlasDictionary'] = self::get_instance();
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

	/**
	 * Enqueue CSS and JavaScript assets
	 */
	private function enqueue_assets(): void
	{
		add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets(): void
	{
		wp_enqueue_style(
			'sparxstar-3iatlas-dictionary-styles',
			$this->pluginUrl . 'src/css/style.css',
			[],
			$this->version
		);

		wp_enqueue_script(
			'sparxstar-3iatlas-dictionary-scripts',
			$this->pluginUrl . 'src/js/main.js',
			['jquery'],
			$this->version,
			true
		);
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets(): void
	{
		wp_enqueue_style(
			'sparxstar-3iatlas-dictionary-admin-styles',
			$this->pluginUrl . 'src/css/admin.css',
			[],
			$this->version
		);

		wp_enqueue_script(
			'sparxstar-3iatlas-dictionary-admin-scripts',
			$this->pluginUrl . 'src/js/admin.js',
			['jquery'],
			$this->version,
			true
		);
	}
}
