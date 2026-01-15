<?php
namespace Starisian\Sparxstar\IAtlas\core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Starisian\Sparxstar\IAtlas\frontend\Sparxstar3IAtlasDictionaryForm;
use Starisian\Sparxstar\IAtlas\includes\Sparxstar3IAtlasPostTypes;
use Starisian\Sparxstar\IAtlas\core\Sparxstar3IAtlasDictionaryCore;

/**
 * Class Sparxstar3IAtlasDictionary 
 * 
 * Main orchestrator for the plugin. Initializes dependencies, hooks, and components.
 */
final class Sparxstar3IAtlasDictionary {
    private static ?Sparxstar3IAtlasDictionary $instance = null;

    private function __construct() {
        $this->sparxIAtlas_load_textdomain();
        $this->sparxIAtlas_load_dependencies();
        $this->sparxIAtlas_register_hooks();
    }

    public static function sparxIAtlas_get_instance(): Sparxstar3IAtlasDictionary {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function sparxIAtlas_register_hooks(): void {
        add_shortcode( 'sparxstar_dictionary', array( $this, 'sparxIAtlas_render_app' ) );
    }

    /**
     * Shortcode callback to render the dictionary app.
     * Usage: [sparxstar_dictionary]
     */
    public function sparxIAtlas_render_app( $atts = array() ): string {
        // Enqueue assets only when shortcode is used
        if ( ! is_admin() ) {
            wp_enqueue_script(
                'sparxstar-dictionary-app',
                SPARX_3IATLAS_URL . 'assets/js/sparxstar-3iatlas-dictionary-app.min.js',
                array(),
                '1.0.0',
                true
            );

            wp_enqueue_style(
                'sparxstar-dictionary-style',
                SPARX_3IATLAS_URL . 'assets/css/sparxstar-3iatlas-dictionary-app.min.css',
                array(),
                '1.0.0'
            );
        }

        return '<div id="sparxstar-dictionary-root"></div>';
    }

    private function sparxIAtlas_load_dependencies(): void {
        // Instantiate Post Types on init (handled by class constructor hook)
        if ( class_exists( Sparxstar3IAtlasPostTypes::class ) ) {
            new Sparxstar3IAtlasPostTypes();
        }

        // Instantiate Core logic
        if ( class_exists( Sparxstar3IAtlasDictionaryCore::class ) ) {
            Sparxstar3IAtlasDictionaryCore::sparxIAtlas_get_instance();
        }
        
        // Instantiate Form if needed
        if ( class_exists( Sparxstar3IAtlasDictionaryForm::class ) && is_user_logged_in() ) {
            new Sparxstar3IAtlasDictionaryForm(); 
        } else {
            // If debugging is on and class is missing
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! class_exists( Sparxstar3IAtlasDictionaryForm::class ) ) {
                error_log( '[Starisian IAtlas Dictionary]: Main class Sparxstar3IAtlasDictionaryForm not found.' );
            }
        }
    }

    private function sparxIAtlas_load_textdomain(): void {
        load_plugin_textdomain( 'sparxstar-3iatlas-dictionary', false, dirname( plugin_basename( SPARX_3IATLAS_PATH ) ) . '/languages' );
    }

    // Prevent cloning and unserializing
    private function __clone(): never { 
        _doing_it_wrong(
            __FUNCTION__,
            'Cloning this object is forbidden.',
            SPARX_3IATLAS_VERSION
        );
    }
    public function __wakeup(): never { 
        _doing_it_wrong(
            __FUNCTION__,
            'Serializing this object is forbidden.',
            SPARX_3IATLAS_VERSION
        );
    }

    public function __unserialize( array $data ): never {
        _doing_it_wrong(
            __FUNCTION__,
            'Unserializing this object is forbidden.',
            SPARX_3IATLAS_VERSION
        );
    }
}
