<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for the 3iAtlas Dictionary plugin tests.
 *
 * Loads the Composer autoloader when available. When WordPress is not
 * bootstrapped (no wp-env), provides minimal WP stubs so the auth and
 * CORS classes can be instantiated in unit tests.
 *
 * @package Starisian\Sparxstar\IAtlas\tests
 * @license Starisian Technologies Proprietary License (STPL)
 */

$autoload = __DIR__ . '/../../vendor/autoload.php';
if ( file_exists( $autoload ) ) {
    require $autoload;
}

// ---------------------------------------------------------------------------
// Minimal WP stubs — only defined if WordPress is NOT loaded (no wp-env).
// Placed in the shared bootstrap so all test files use the same stubs
// and cannot conflict with each other.
// ---------------------------------------------------------------------------

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/wp/' );
}

if ( ! function_exists( 'register_post_type' ) ) {

    if ( ! class_exists( 'WP_REST_Request' ) ) {
        /**
         * Minimal WP_REST_Request stub for unit tests.
         */
        class WP_REST_Request {
            /** @var array<string,string> */
            private array $headers = [];
            /** @var array<string,mixed> */
            private array $params = [];
            private string $method = 'GET';
            private string $route  = '/sparxstar/v1/dictionary/lookup';

            public function get_header( string $key ): string {
                return $this->headers[ strtolower( $key ) ] ?? '';
            }

            public function set_header( string $key, string $val ): void {
                $this->headers[ strtolower( $key ) ] = $val;
            }

            public function get_method(): string {
                return $this->method;
            }

            public function set_method( string $method ): void {
                $this->method = $method;
            }

            public function get_route(): string {
                return $this->route;
            }

            public function set_route( string $route ): void {
                $this->route = $route;
            }

            public function get_param( string $key ): mixed {
                return $this->params[ $key ] ?? null;
            }

            public function set_param( string $key, mixed $val ): void {
                $this->params[ $key ] = $val;
            }
        }
    }

    if ( ! class_exists( 'WP_REST_Response' ) ) {
        /**
         * Minimal WP_REST_Response stub for unit tests.
         */
        class WP_REST_Response {
            public int   $status;
            public mixed $data;
            /** @var array<string,string> */
            private array $headers = [];

            public function __construct( mixed $data = null, int $status = 200 ) {
                $this->data   = $data;
                $this->status = $status;
            }

            public function header( string $key, string $value ): void {
                $this->headers[ $key ] = $value;
            }

            /** @return array<string,string> */
            public function get_headers(): array {
                return $this->headers;
            }

            public function get_status(): int {
                return $this->status;
            }
        }
    }

    if ( ! class_exists( 'WP_Error' ) ) {
        /**
         * Minimal WP_Error stub for unit tests.
         */
        class WP_Error {
            public string $code;
            public string $message;
            /** @var array<string,mixed> */
            public array $data;

            /** @param array<string,mixed> $data */
            public function __construct( string $code = '', string $message = '', array $data = [] ) {
                $this->code    = $code;
                $this->message = $message;
                $this->data    = $data;
            }

            /** @return array<string,mixed> */
            public function get_error_data( string $code = '' ): mixed {
                return $this->data;
            }

            public function get_error_code(): string {
                return $this->code;
            }
        }
    }

    if ( ! class_exists( 'WP_REST_Server' ) ) {
        /**
         * Minimal WP_REST_Server stub for unit tests.
         */
        class WP_REST_Server {}
    }

    if ( ! function_exists( 'is_wp_error' ) ) {
        function is_wp_error( mixed $thing ): bool {
            return $thing instanceof WP_Error;
        }
    }
    if ( ! function_exists( 'get_transient' ) ) {
        function get_transient( string $key ): mixed {
            return false;
        }
    }
    if ( ! function_exists( 'set_transient' ) ) {
        function set_transient( string $key, mixed $value, int $expiry = 0 ): bool {
            return true;
        }
    }
    if ( ! function_exists( 'wp_cache_add' ) ) {
        function wp_cache_add( string $key, mixed $data, string $group = '', int $expire = 0 ): bool {
            $store_key = $group . ':' . $key;
            if ( array_key_exists( $store_key, $GLOBALS['__wp_object_cache'] ) ) {
                return false;
            }
            $GLOBALS['__wp_object_cache'][ $store_key ] = $data;
            return true;
        }
    }
    if ( ! function_exists( 'wp_cache_incr' ) ) {
        function wp_cache_incr( string $key, int $offset = 1, string $group = '' ): int|false {
            $store_key = $group . ':' . $key;
            if ( ! array_key_exists( $store_key, $GLOBALS['__wp_object_cache'] ) ) {
                return false;
            }
            $GLOBALS['__wp_object_cache'][ $store_key ] = (int) $GLOBALS['__wp_object_cache'][ $store_key ] + $offset;
            return $GLOBALS['__wp_object_cache'][ $store_key ];
        }
    }
    if ( ! function_exists( 'wp_cache_decr' ) ) {
        function wp_cache_decr( string $key, int $offset = 1, string $group = '' ): int|false {
            $store_key = $group . ':' . $key;
            if ( ! array_key_exists( $store_key, $GLOBALS['__wp_object_cache'] ) ) {
                return false;
            }
            $GLOBALS['__wp_object_cache'][ $store_key ] = max( 0, (int) $GLOBALS['__wp_object_cache'][ $store_key ] - $offset );
            return $GLOBALS['__wp_object_cache'][ $store_key ];
        }
    }
    if ( ! function_exists( 'wp_cache_delete' ) ) {
        function wp_cache_delete( string $key, string $group = '' ): bool {
            $store_key = $group . ':' . $key;
            unset( $GLOBALS['__wp_object_cache'][ $store_key ] );
            return true;
        }
    }
    if ( ! function_exists( 'get_option' ) ) {
        function get_option( string $key, mixed $default = false ): mixed {
            return $GLOBALS['__wp_options_store'][ $key ] ?? $default;
        }
    }
    if ( ! function_exists( 'update_option' ) ) {
        function update_option( string $key, mixed $value, bool $autoload = true ): bool {
            $GLOBALS['__wp_options_store'][ $key ] = $value;
            return true;
        }
    }
    if ( ! function_exists( 'apply_filters' ) ) {
        function apply_filters( string $tag, mixed $value, mixed ...$args ): mixed {
            return $value;
        }
    }
    if ( ! function_exists( 'add_action' ) ) {
        function add_action( mixed ...$args ): void {}
    }
    if ( ! function_exists( 'add_filter' ) ) {
        function add_filter( mixed ...$args ): void {}
    }
    if ( ! function_exists( '__' ) ) {
        function __( string $text, string $domain = 'default' ): string {
            return $text;
        }
    }
}

// Initialise the options store used by stubs.
if ( ! isset( $GLOBALS['__wp_options_store'] ) ) {
    $GLOBALS['__wp_options_store'] = [];
}

// Initialise the object cache store used by wp_cache_* stubs.
if ( ! isset( $GLOBALS['__wp_object_cache'] ) ) {
    $GLOBALS['__wp_object_cache'] = [];
}
