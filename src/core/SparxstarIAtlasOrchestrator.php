<?php
namespace Starisian\Sparxstar\IAtlas\Core;

if (!defined('ABSPATH')) {
	exit;
}

use Starisian\Sparxstar\IAtlas\Frontend\SparxstarIAtlasDictionaryForm;
use Starisian\Sparxstar\IAtlas\Includes\SparxstarIAtlasPostTypes;
use Starisian\Sparxstar\IAtlas\Core\SparxstarIAtlasDictionaryCore;

/**
 * Class SparxstarIAtlasOrchestrator
 * 
 * Main orchestrator for the plugin. Initializes dependencies, hooks, and components.
 */
final class SparxstarIAtlasOrchestrator
{
	private static ?SparxstarIAtlasOrchestrator $instance = null;
	private $form;

	private function __construct()
	{
		$this->sparxIAtlas_load_textdomain();
		$this->sparxIAtlas_load_dependencies();
		$this->sparxIAtlas_register_hooks();
	}

	public static function sparxIAtlas_get_instance(): SparxstarIAtlasOrchestrator
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
            SPARX_IATLAS_URL . 'assets/js/app.min.js',
            [],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'aiwa-dictionary-style',
            SPARX_IATLAS_URL . 'assets/css/app.min.css',
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

	private function sparxIAtlas_load_textdomain(): void
	{
		load_plugin_textdomain('SparxstarIAtlasDictionary', false, dirname(plugin_basename(SPARX_IATLAS_PATH)) . '/languages');
	}

    // Prevent cloning and unserializing
	private function __clone(): void { }
	public function __wakeup(): void { }
}
