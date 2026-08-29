<?php
/**
 * Autoloader.
 *
 * @package Sparxstar\3iAtlas\Dictionary
 */

declare( strict_types=1 );

namespace Starisian\Sparxstar\IAtlas\includes;

/**
 * Simple PSR-4 class autoloader for Starisian plugins.
 *
 * This autoloader supports OOP plugin development without requiring Composer.
 * It expects classes to be within the defined SPARX_3IATLAS_NAMESPACE and located in /src/.
 * 
 * @package Starisian\Sparxstar\IAtlas\includes
 * @author Starisian Technologies (Max Barrett) <support@starisian.com>
 * @version 0.6.5
 * @since 0.1.0
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Simple PSR-4 class autoloader for Starisian plugins.
 *
 * This autoloader supports OOP plugin development without requiring Composer.
 * It expects classes to be within the defined SPARX_3IATLAS_NAMESPACE and located in /src/.
 */
class Autoloader {

    /**
     * Register this autoloader with SPL.
     *
     * @return void
     */
    public static function sparxIAtlas_register(): void {
        spl_autoload_register( array( __CLASS__, 'sparxIAtlas_loadClass' ) );
    }

    /**
     * Unregister this autoloader.
     *
     * @return void
     */
    public static function sparxIAtlas_unregister(): void {
        spl_autoload_unregister( array( __CLASS__, 'sparxIAtlas_loadClass' ) );
    }

    /**
     * PSR-4 autoload implementation.
     *
     * @param string $class_name Fully qualified class name.
     * @return void
     */
    public static function sparxIAtlas_loadClass( string $class_name ): void {
        // Ensure required constants are defined.
        if ( ! defined( 'SPARX_3IATLAS_NAMESPACE' ) || ! defined( 'SPARX_3IATLAS_PATH' ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Deliberate bootstrap diagnostic, debug builds only.
                error_log( 'Autoloader error: SPARX_3IATLAS_NAMESPACE or SPARX_3IATLAS_PATH is not defined.' );
            }
            return;
        }

        $base_namespace = SPARX_3IATLAS_NAMESPACE;
        $base_dir       = SPARX_3IATLAS_PATH . 'src/';

        $len = strlen( $base_namespace );
        if ( strncmp( $class_name, $base_namespace, $len ) !== 0 ) {
            return;
        }

        $relative_class = substr( $class_name, $len );
        $file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
