<?php

declare(strict_types=1);

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
 * x-release-please-start-version
 * Version:           2.8.14
 * x-release-please-end
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
    define( 'SPARX_3IATLAS_VERSION', '2.8.14' ); // x-release-please-version
}
if ( ! defined( 'SPARX_3IATLAS_NAMESPACE' ) ) {
    define( 'SPARX_3IATLAS_NAMESPACE', 'Starisian\\Sparxstar\\IAtlas\\' );
}
if ( ! defined( 'SPARX_3IATLAS_GRAPHQL_SLUG' ) ) {
    define( 'SPARX_3IATLAS_GRAPHQL_SLUG', 'graphql' );
}
if ( ! defined( 'SPARX_3IATLAS_GOOGLE_FONTS_URL' ) ) {
    // Inter — wide African-language coverage; system fonts are the fallback/swap. Single source of truth.
    define( 'SPARX_3IATLAS_GOOGLE_FONTS_URL', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' );
}


// 2. Compatibility Checks (Bootloader level)
if ( version_compare( PHP_VERSION, '8.2', '<' ) || version_compare( $GLOBALS['wp_version'], '6.4', '<' ) ) {
    add_action(
        'admin_notices',
        function () {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Sparxstar 3IAtlas Dictionary requires PHP 8.2+ and WordPress 6.4+.', 'sparxstar-3iatlas-dictionary' ) . '</p></div>';
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

use Starisian\Sparxstar\IAtlas\core\Sparxstar3IAtlasDictionary;
use Starisian\Sparxstar\IAtlas\includes\Sparxstar3IAtlasPostTypes;

// 4. Activation / Deactivation Hooks
register_activation_hook( __FILE__, 'Starisian\Sparxstar\IAtlas\sparxIAtlas_activate_plugin' );
register_deactivation_hook( __FILE__, 'Starisian\Sparxstar\IAtlas\sparxIAtlas_deactivate_plugin' );
register_uninstall_hook( __FILE__, 'Starisian\Sparxstar\IAtlas\sparxIAtlas_uninstall_plugin' );

/**
 * Activates the plugin and registers Custom Post Types to flush rewrite rules.
 *
 * @return void
 */
function sparxIAtlas_activate_plugin() {
    // Trigger CPT registration to verify rewrite rules
    if ( class_exists( Sparxstar3IAtlasPostTypes::class ) ) {
        $pt = new Sparxstar3IAtlasPostTypes();
    }
    // Flag a one-shot flush so the standalone app route (registered on init) is
    // picked up on the next request without requiring a manual permalink save.
    // The actual flush runs on the next init (after all rewrite rules exist),
    // so flushing here would be premature and miss the app route.
    update_option( 'sparxstar_dict_flush_routes', 1 );
}

/**
 * Deactivates the plugin and flushes rewrite rules.
 *
 * @return void
 */
function sparxIAtlas_deactivate_plugin() {
    flush_rewrite_rules();
}

/**
 * Handles plugin uninstallation.
 *
 * @return void
 */
function sparxIAtlas_uninstall_plugin() {
    // Clean up options or data if needed
    delete_option( 'sparxstar_dict_flush_routes' );
}

// 5. Run the Plugin (Orchestration)
add_action(
    'plugins_loaded',
    function () {
        if ( class_exists( Sparxstar3IAtlasDictionary::class ) ) {
            Sparxstar3IAtlasDictionary::sparxIAtlas_get_instance();
        }
    }
);
