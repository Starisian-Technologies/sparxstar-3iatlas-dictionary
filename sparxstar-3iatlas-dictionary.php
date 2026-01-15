<?php
namespace Starisian\Sparxstar\IAtlas;

/**
 * SPARXSTAR 3IAtlas Dictionary
 * 
 * @file             sparxstar-3iatlas-dictionary.php
 * @package          Starisian\Sparxstar\IAtlas
 * @author           Starisian Technologies (Max Barrett) <support@starisian.com>
 * @license          Starisian Technologies Proprietary License (STPL)
 * @copyright        Copyright (c) 2024 Starisian Technologies. All rights reserved.
 * 
 * @wordpress-plugin
 * Plugin Name:       SPARXSTAR 3IAtlas Dictionary
 * Plugin URI:        https://starisian.com/sparxstar/sparxstar-3iatlas-dictionary/
 * Description:       A WordPress plugin for 3iAtlas Dictionary management with SCF and WPGraphQL integration.
 * Version:           0.6.0
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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Define Constants
if ( ! defined( 'SPARX_3IATLAS_PATH' ) ) {
    define( 'SPARX_3IATLAS_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SPARX_3IATLAS_URL' ) ) {
    define( 'SPARX_3IATLAS_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'SPARX_3IATLAS_VERSION' ) ) {
    define( 'SPARX_3IATLAS_VERSION', '0.6.0' );
}
if ( ! defined( 'SPARX_3IATLAS_NAMESPACE' ) ) {
    define( 'SPARX_3IATLAS_NAMESPACE', 'Starisian\\Sparxstar\\IAtlas\\' );
}

// 2. Compatibility Checks (Bootloader level)
if ( version_compare( PHP_VERSION, '8.2', '<' ) || version_compare( $GLOBALS['wp_version'], '6.4', '<' ) ) {
    add_action(
        'admin_notices',
        function () {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Sparxstar 3IAtlas Dictionary requires PHP 8.2+ and WordPress 6.4+.', 'SparxstarIAtlasDictionary' ) . '</p></div>';
        }
    );
    return;
}

// 3. Autoloader Setup
if ( file_exists( SPARX_3IATLAS_PATH . 'vendor/autoload.php' ) ) {
    require_once SPARX_3IATLAS_PATH . 'vendor/autoload.php';
} elseif ( file_exists( SPARX_3IATLAS_PATH . 'src/includes/Autoloader.php' ) ) {
    require_once SPARX_3IATLAS_PATH . 'src/includes/Autoloader.php';
    
    if ( ! defined( 'SPARX_3IATLAS_NAMESPACE' ) ) {
        define( 'SPARX_3IATLAS_NAMESPACE', 'Starisian\\Sparxstar\\IAtlas\\' );
    }
    if ( ! defined( 'SPARX_3IATLAS_PATH' ) ) {
        define( 'SPARX_3IATLAS_PATH', SPARX_3IATLAS_PATH );
    }
    
    // Register the Autoloder
    if ( class_exists( 'Starisian\Sparxstar\IAtlas\includes\Autoloader' ) ) {
        \Starisian\Sparxstar\IAtlas\includes\Autoloader::sparxIAtlas_register();
    }
}

use Starisian\Sparxstar\IAtlas\core\Sparxstar3IAtlasOrchestrator;
use Starisian\Sparxstar\IAtlas\includes\Sparxstar3IAtlasPostTypes;

// 4. Activation / Deactivation Hooks
register_activation_hook( __FILE__, 'Starisian\Sparxstar\IAtlas\sparxIAtlas_activate_plugin' );
register_deactivation_hook( __FILE__, 'Starisian\Sparxstar\IAtlas\sparxIAtlas_deactivate_plugin' );
register_uninstall_hook( __FILE__, 'Starisian\Sparxstar\IAtlas\sparxIAtlas_uninstall_plugin' );

function sparxIAtlas_activate_plugin() {
    // Trigger CPT registration to verify rewrite rules
    if ( class_exists( Sparxstar3IAtlasPostTypes::class ) ) {
        $pt = new Sparxstar3IAtlasPostTypes();
        if ( method_exists( $pt, 'sparxIAtlas_register_dictionary_cpt' ) ) {
            $pt->sparxIAtlas_register_dictionary_cpt();
        }
    }
    flush_rewrite_rules();
}

function sparxIAtlas_deactivate_plugin() {
    flush_rewrite_rules();
}

function sparxIAtlas_uninstall_plugin() {
    // Clean up options or data if needed
}

// 5. Run the Plugin (Orchestration)
add_action(
    'plugins_loaded',
    function () {
        if ( class_exists( Sparxstar3IAtlasOrchestrator::class ) ) {
            SparxstarIAtlasOrchestrator::sparxIAtlas_get_instance();
        }
    }
);
