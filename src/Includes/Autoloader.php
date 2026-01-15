<?php
namespace Starisian\Sparxstar\IAtlas\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Simple PSR-4 class autoloader for Starisian plugins.
 *
 * This autoloader supports OOP plugin development without requiring Composer.
 * It expects classes to be within the defined STARISIAN_NAMESPACE and located in /src/.
 */
class Autoloader {

    /**
     * Register this autoloader with SPL.
     */
    public static function sparxIAtlas_register(): void {
        spl_autoload_register( array( __CLASS__, 'sparxIAtlas_loadClass' ) );
    }

    /**
     * Unregister this autoloader.
     */
    public static function sparxIAtlas_unregister(): void {
        spl_autoload_unregister( array( __CLASS__, 'sparxIAtlas_loadClass' ) );
    }

    /**
     * PSR-4 autoload implementation.
     *
     * @param string $className Fully qualified class name.
     */
    public static function sparxIAtlas_loadClass( string $className ): void {
        // Ensure required constants are defined
        if ( ! defined( 'STARISIAN_NAMESPACE' ) || ! defined( 'STARISIAN_PATH' ) ) {
            error_log( 'Autoloader error: STARISIAN_NAMESPACE or STARISIAN_PATH is not defined.' );
            return;
        }

        $baseNamespace = STARISIAN_NAMESPACE;
        $baseDir       = STARISIAN_PATH . 'src/';

        $len = strlen( $baseNamespace );
        if ( strncmp( $className, $baseNamespace, $len ) !== 0 ) {
            return;
        }

        $relativeClass = substr( $className, $len );
        $file          = $baseDir . str_replace( '\\', '/', $relativeClass ) . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
