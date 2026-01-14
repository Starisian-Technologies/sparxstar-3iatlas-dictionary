<?php
namespace Starisian\Sparxstar\IAtlas;

/**
 * SPARXSTAR 3IAtlas Dictionary
 * 
 * @file		     SparxstarIAtlasDictionary.php
 * @package 	     Starisian\Sparxstar\IAtlas
 * @author           Starisian Technologies (Max Barrett) <support@starisian.com>
 * @license	         Starisian Technologies Proprietary License (STPL)
 * @copyright	     Copyright (c) 2024 Starisian Technologies. All rights reserved.
 * 
 * @wordpress-plugin
 * Plugin Name:       SPARXSTAR 3IAtlas Dictionary
 * Plugin URI:        https://starisian.com/sparxstar/sparxstar-3iatlas-dictionary/
 * Description:       A description of what this plugin does, including its strategic value or AI-driven functionality.
 * Version:           1.0.0
 * Author:            Starisian Technologies
 * Author URI:        https://www.starisian.com/
 * Contributor:       Max Barrett
 * License:           Starisian Technologies Proprietary License (STPL)
 * License URI:
 * Text Domain:       SparxstarIAtlasDictionary
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Tested up to:      6.9
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!defined('SPARX_IATLAS_PATH')) {
	define('SPARX_IATLAS_PATH', plugin_dir_path(__FILE__));
}
if (!defined('SPARX_IATLAS_URL')) {
	define('SPARX_IATLAS_URL', plugin_dir_url(__FILE__));
}
if (!defined('SPARX_IATLAS_VERSION')) {
	define('SPARX_IATLAS_VERSION', '1.0.0');
}
if (!defined('SPARX_IATLAS_NAMESPACE')) {
	define('SPARX_IATLAS_NAMESPACE', 'Starisian\\Sparxstar\\IAtlas\\');
}
if(!defined('SPARX_IATLAS_DELETE_ON_UNINSTALL')){
	define('SPARX_IATLAS_DELETE_ON_UNINSTALL', false);
}	

// Use Composer autoloader if available
if (file_exists(SPARX_IATLAS_PATH . 'vendor/autoload.php')) {
	require_once SPARX_IATLAS_PATH . 'vendor/autoload.php';
} elseif (file_exists(SPARX_IATLAS_PATH . 'src/includes/Autoloader.php')) {
	require_once SPARX_IATLAS_PATH . 'src/includes/Autoloader.php';
	// Define constants for custom autoloader if needed
	if (!defined('STARISIAN_NAMESPACE')) define('STARISIAN_NAMESPACE', 'Starisian\\Sparxstar\\IAtlas\\');
	if (!defined('STARISIAN_PATH')) define('STARISIAN_PATH', SPARX_IATLAS_PATH);
	Starisian\Sparxstar\IAtlas\Includes\Autoloader::sparxIAtlas_register();
}

use Starisian\Sparxstar\IAtlas\Frontend\SparxstarIAtlasDictionaryForm;
use Starisian\Sparxstar\IAtlas\Includes\SparxstarIAtlasPostTypes;
use Starisian\Sparxstar\IAtlas\Core\SparxstarIAtlasDictionaryCore;

final class SparxstarIAtlasDictionary
{
	const VERSION = SPARX_IATLAS_VERSION;
	const MINIMUM_PHP_VERSION = '8.2';
	const MINIMUM_WP_VERSION = '6.8';

	private static ?SparxstarIAtlasDictionary $instance = null;

	private string $pluginName = 'Sparxstar IAtlas Dictionary';

	private string $pluginPath;
	private string $pluginUrl;
	private string $version;
	private $form;

	private function __construct()
	{
		$this->pluginPath = SPARX_IATLAS_PATH;
		$this->pluginUrl = SPARX_IATLAS_URL;
		$this->version = self::SPARX_IATLAS_VERSION;

		if (!$this->sparxIAtlas_check_compatibility()) {
			add_action('admin_notices', [$this, 'sparxIAtlas_admin_notice_compatibility']);
			return;
		}
		
		$this->sparxIAtlas_load_textdomain();
		$this->sparxIAtlas_load_dependencies();
		$this->sparxIAtlas_register_hooks();
	}

	public static function sparxIAtlas_get_instance(): SparxstarIAtlasDictionary
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function sparxIAtlas_register_hooks(): void{
		add_action('wp_enqueue_scripts', [$this, 'sparxIAtlas_enqueue_app']);
		add_action('wp_footer', [$this, 'sparxIAtlas_mount_div']);
	}

	 public function sparxIAtlas_enqueue_app() {
        wp_enqueue_script(
            'aiwa-dictionary-app',
            plugin_dir_url(__FILE__) . 'build/index.js',
            [],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'aiwa-dictionary-style',
            plugin_dir_url(__FILE__) . 'build/index.css',
            [],
            '1.0.0'
        );
    }

    public function sparxIAtlas_mount_div() {
        echo '<div id="aiwa-dictionary-root"></div>';
    }

	private function sparxIAtlas_load_dependencies(): void {
		// Instantiate Post Types on init (handled by class constructor hook)
		if (class_exists(SparxstarIAtlasPostTypes::class)) {
			new SparxstarIAtlasPostTypes();
		}

		// Instantiate Core logic
		if(class_exists(SparxstarIAtlasDictionaryCore::class)) {
			SparxstarIAtlasDictionaryCore::sparxIAtlas_get_instance();
		}
		
		// Instantiate Form if needed
		if (class_exists(SparxstarIAtlasDictionaryForm::class) && is_user_logged_in()) {
			$this->form = new SparxstarIAtlasDictionaryForm(); 
		} else {
             // If debugging is on and class is missing
			if (defined('WP_DEBUG') && WP_DEBUG && !class_exists(SparxstarIAtlasDictionaryForm::class)) {
				error_log('[Starisian IAtlas Dictionary]: Main class SparxstarIAtlasDictionaryForm not found.');
			}
        }
	}

	private function sparxIAtlas_check_compatibility(): bool
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

	public function sparxIAtlas_admin_notice_compatibility(): void
	{
		echo '<div class="notice notice-error"><p>';
		if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '<')) {
			echo esc_html__('The Sparxstar IAtlas Dictionary plugin requires PHP version ' . self::MINIMUM_PHP_VERSION . ' or higher.', 'plugin-textdomain') . '<br>';
		}
		if (version_compare($GLOBALS['wp_version'], self::MINIMUM_WP_VERSION, '<')) {
			echo esc_html__('The Sparxstar IAtlas Dictionary plugin requires WordPress version ' . self::MINIMUM_WP_VERSION . ' or higher.', 'plugin-textdomain');
		}
		echo '</p></div>';
	}

	private function sparxIAtlas_load_textdomain(): void
	{
		load_plugin_textdomain('SparxstarIAtlasDictionary', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	private function __clone(): void
	{
		_doing_it_wrong(__FUNCTION__, esc_html__('Cloning is not allowed.', 'SparxstarIAtlasDictionary'), self::VERSION);
	}

	public function __wakeup(): void
	{
			_doing_it_wrong(__FUNCTION__, esc_html__('Unserializing is not allowed.', 'SparxstarIAtlasDictionary'), self::VERSION);
	}

	public function __sleep(): array
	{
			_doing_it_wrong(__FUNCTION__, esc_html__('Serialization is not allowed.', 'SparxstarIAtlasDictionary'), self::VERSION);
			return [];
    }

	private function __destruct()
	{
		_doing_it_wrong(__FUNCTION__, esc_html__('Destruction is not allowed.', 'SparxstarIAtlasDictionary'), self::VERSION);
	}

	public function __call($name, $arguments): void
	{
		_doing_it_wrong(__FUNCTION__, esc_html__('Calling undefined methods is not allowed.', 'SparxstarIAtlasDictionary'), self::VERSION);
	}

	public static function sparxIAtlas_run(): void
	{
		self::sparxIAtlas_get_instance();
	}

	public static function sparxIAtlas_activate(): void
	{
		if (class_exists(SparxstarIAtlasPostTypes::class)) {
			// This will just register hooks, but we need to run register_post_type explicitly to flush rewrite rules
            // However, since rewrite rules are flushed, WP needs to know about the CPT.
            // Activating the class here won't register CPT *right now* if it hooks to init.
            // But strict activation hook is distinct from init. It's fine.
            // Just instantiating hooks to init won't register post type for THIS request's flush_rewrite_rules.
            // But typically, flush_rewrite_rules is called after registering post type.
            // In strict activation callback, init has already passed.
            // So we should manually call register_dictionary_cpt() if we strictly need it.
            // But let's just keep instantiation for now.
			$pt = new SparxstarIAtlasPostTypes();
            // Manually trigger registration for this request so rewrite works
            if(method_exists($pt, 'sparxIAtlas_register_dictionary_cpt')) {
                $pt->sparxIAtlas_register_dictionary_cpt();
            }
		}
		flush_rewrite_rules();
	}

	public static function sparxIAtlas_deactivate(): void
	{
		flush_rewrite_rules();
	}

	public static function sparxIAtlas_uninstall(): void
	{
		// Optional: cleanup logic
	}
}

// Hooks and initialization
register_activation_hook(__FILE__, ['Starisian\Sparxstar\IAtlas\SparxstarIAtlasDictionary', 'sparxIAtlas_activate']);
register_deactivation_hook(__FILE__, ['Starisian\Sparxstar\IAtlas\SparxstarIAtlasDictionary', 'sparxIAtlas_deactivate']);
register_uninstall_hook(__FILE__, ['Starisian\Sparxstar\IAtlas\SparxstarIAtlasDictionary', 'sparxIAtlas_uninstall']);

add_action('plugins_loaded', ['Starisian\Sparxstar\IAtlas\SparxstarIAtlasDictionary', 'sparxIAtlas_run']);
