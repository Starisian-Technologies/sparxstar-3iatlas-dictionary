<?php

declare(strict_types=1);
/**
 * Main plugin orchestrator file.
 *
 * @package Starisian\Sparxstar\IAtlas\core
 * @author Starisian Technologies (Max Barrett) <support@starisian.com>
 * @version 0.6.5
 * @since 0.1.0
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

namespace Starisian\Sparxstar\IAtlas\core;

use Starisian\Sparxstar\IAtlas\frontend\Sparxstar3IAtlasDictionaryForm;
use Starisian\Sparxstar\IAtlas\includes\Sparxstar3IAtlasPostTypes;
use Starisian\Sparxstar\IAtlas\core\Sparxstar3IAtlasDictionaryCore;
use Starisian\Sparxstar\IAtlas\includes\Sparxstar3IAtlasAutoLinker;
use WP_DEBUG;
use WP_Post;
use Throwable;
use RuntimeException;
use function defined;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Sparxstar3IAtlasDictionary 
 * 
 * Main orchestrator for the plugin. Initializes dependencies, hooks, and components.
 */
final class Sparxstar3IAtlasDictionary
{
    /**
     * Singleton instance of the class.
     *
     * @var Sparxstar3IAtlasDictionary|null
     */
    private static ?Sparxstar3IAtlasDictionary $instance = null;

    private function __construct()
    {
        $this->sparxIAtlas_load_textdomain();
        $this->sparxIAtlas_load_dependencies();
        $this->sparxIAtlas_register_hooks();
    }

    /**
     * Gets the singleton instance of the class.
     *
     * @return Sparxstar3IAtlasDictionary The singleton instance.
     */
    public static function sparxIAtlas_get_instance(): Sparxstar3IAtlasDictionary
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registers the necessary actions and filters.
     *
     * @return void
     */
    private function sparxIAtlas_register_hooks(): void
    {
        add_action('init', array($this, 'sparxIAtlas_register_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'sparxIAtlas_register_assets'));
    }

    /**
     * Registers the shortcode for the dictionary app.
     * 
     * @return void
     */
    public function sparxIAtlas_register_shortcodes(): void
    {
        add_shortcode('sparxstar_dictionary', array($this, 'sparxIAtlas_render_app'));
    }

    /**
     * Registers and conditionally enqueues the assets for the dictionary app.
     * 
     * @return void
     */
    public function sparxIAtlas_register_assets(): void
    {
        try {
            // Register assets first so they can be enqueued later via shortcode or logic
            wp_register_script(
                'sparxstar-dictionary-app',
                SPARX_3IATLAS_URL . 'assets/js/sparxstar-3iatlas-dictionary-app.min.js',
                [],
                SPARX_3IATLAS_VERSION,
                true
            );

            wp_register_style(
                'sparxstar-dictionary-style',
                SPARX_3IATLAS_URL . 'assets/css/sparxstar-3iatlas-dictionary-app.min.css',
                array(),
                SPARX_3IATLAS_VERSION
            );
        } catch (\Throwable $throwable) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Starisian 3IAtlas Dictionary]: Error registering/enqueuing assets - ' . $throwable->getMessage());
            }
        }
    }

    /**
     * Shortcode callback to render the dictionary app.
     * Usage: [sparxstar_dictionary title="My Dictionary"]
     * 
     * @param array|string $atts Shortcode attributes.
     * @return string The rendered shortcode content.
     */
    public function sparxIAtlas_render_app($atts = array()): string
    {
        try {
            $graphql_url = \site_url(SPARX_3IATLAS_GRAPHQL_SLUG);

            if (empty($graphql_url) || filter_var($graphql_url, FILTER_VALIDATE_URL) === false) {
                return '<p>' . esc_html__('Dictionary endpoint is not available.', 'sparxstar-3iatlas-dictionary') . '</p>';
            }

            $atts = shortcode_atts(
                array(
                    'title' => 'Dictionary',
                ),
                $atts,
                'sparxstar_dictionary'
            );

            // Pass attributes to the frontend
            // FIX: Variable name changed to sparxstarDictionarySettings (Capital S) to match React App.js
            wp_localize_script(
                'sparxstar-dictionary-app',
                'sparxstarDictionarySettings',
                [
                    'root_id'    => 'sparxstar-dictionary-root',
                    'graphqlUrl' => $graphql_url,
                ]
            );
            // Ensure assets are enqueued (in case they weren't caught by the global check, e.g., in a widget)
            wp_enqueue_script('sparxstar-dictionary-app');
            wp_enqueue_style('sparxstar-dictionary-style');
        } catch (\Throwable $throwable) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Starisian 3IAtlas Dictionary]: Error rendering shortcode - ' . $throwable->getMessage());
            }
            return '<p>' . esc_html__('An error occurred while loading the dictionary.', 'sparxstar-3iatlas-dictionary') . '</p>';
        }

        return '<div id="sparxstar-dictionary-root" data-title="' . esc_attr($atts['title']) . '"></div>';
    }

    /**
     * Loads the plugin dependencies and initializes core components.
     *
     * @return void
     */
    private function sparxIAtlas_load_dependencies(): void
    {
        try {
            // Instantiate Post Types on init (handled by class constructor hook)
            if (class_exists(Sparxstar3IAtlasPostTypes::class)) {
                new Sparxstar3IAtlasPostTypes();
            }

            // Only load frontend components if not in admin area
            if (! is_admin()) {
                if (class_exists(Sparxstar3IAtlasDictionaryCore::class)) {
                    // Instantiate Core logic
                    Sparxstar3IAtlasDictionaryCore::sparxIAtlas_get_instance();
                }

                if (class_exists(Sparxstar3IAtlasDictionaryForm::class) && is_user_logged_in()) {
                    // Instantiate Form if needed
                    new Sparxstar3IAtlasDictionaryForm();
                }
            }

            // Instantiate Auto Linker
            if (class_exists(Sparxstar3IAtlasAutoLinker::class)) {
                new Sparxstar3IAtlasAutoLinker();
            }
        } catch (\Throwable $throwable) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Starisian 3IAtlas Dictionary]: Error loading dependencies - ' . $throwable->getMessage());
            }
        }
    }

    /**
     * Loads the plugin textdomain for translation.
     *
     * @return void
     */
    private function sparxIAtlas_load_textdomain(): void
    {
        load_plugin_textdomain('sparxstar-3iatlas-dictionary', false, dirname(plugin_basename(SPARX_3IATLAS_PATH . 'sparxstar-3iatlas-dictionary.php')) . '/languages');
    }

    // Prevent cloning and unserializing
    private function __clone(): never
    {
        _doing_it_wrong(__FUNCTION__, 'Cloning this object is forbidden.', SPARX_3IATLAS_VERSION);
        throw new \RuntimeException('Cloning is not allowed.');
    }

    public function __wakeup(): never
    {
        _doing_it_wrong(__FUNCTION__, 'Serializing this object is forbidden.', SPARX_3IATLAS_VERSION);
        throw new \RuntimeException('Serializing is not allowed.');
    }

    public function __unserialize(array $data): never
    {
        _doing_it_wrong(__FUNCTION__, 'Unserializing this object is forbidden.', SPARX_3IATLAS_VERSION);
        throw new \RuntimeException('Unserializing is not allowed.');
    }
}
