<?php
/**
 * Configuration for the Dictionary Node display adapter.
 *
 * Every value here is deployment-specific and is DELIBERATELY UNSET BY DEFAULT.
 * Nothing in this file invents an endpoint, a client identifier, a key id, or a
 * path: an unconfigured deployment reports a configuration problem and refuses
 * to call upstream, which is the honest failure. See `docs/display-adapter.md`.
 *
 * @package Starisian\Sparxstar\IAtlas\api\display
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\IAtlas\api\display;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

/**
 * Reads and validates the display adapter's deployment configuration.
 *
 * Each setting is read from a PHP constant (wp-config.php) and may then be
 * overridden by a filter, so a deployment can source values from a secret
 * manager without editing this plugin. No value has a fallback default.
 */
final class DisplayConfig {

    /**
     * Constant naming the identity node's token endpoint URL.
     *
     * @var string
     */
    public const CONST_TOKEN_ENDPOINT = 'SPARXSTAR_DICT_IDENTITY_TOKEN_ENDPOINT';

    /**
     * Constant naming the Dictionary Node base URL.
     *
     * @var string
     */
    public const CONST_NODE_BASE_URL = 'SPARXSTAR_DICT_NODE_BASE_URL';

    /**
     * Constant naming this deployment's registered identity client_id.
     *
     * @var string
     */
    public const CONST_CLIENT_ID = 'SPARXSTAR_DICT_CLIENT_ID';

    /**
     * Constant naming the registered key id (`kid`) of the signing key.
     *
     * @var string
     */
    public const CONST_CLIENT_KID = 'SPARXSTAR_DICT_CLIENT_KID';

    /**
     * Constant naming the absolute path of the RSA private key.
     *
     * @var string
     */
    public const CONST_PRIVATE_KEY_PATH = 'SPARXSTAR_DICT_PRIVATE_KEY_PATH';

    /**
     * Constant naming the salt used to derive the opaque reader reference.
     *
     * @var string
     */
    public const CONST_READER_REF_SALT = 'SPARXSTAR_DICT_READER_REF_SALT';

    /**
     * Constant enabling the display adapter (the cutover flag). Default OFF.
     *
     * @var string
     */
    public const CONST_ADAPTER_ENABLED = 'SPARXSTAR_DICT_DISPLAY_ADAPTER';

    /**
     * Constant overriding the upstream request timeout, in seconds.
     *
     * @var string
     */
    public const CONST_TIMEOUT = 'SPARXSTAR_DICT_NODE_TIMEOUT';

    /**
     * Constant overriding the maximum accepted upstream response size, in bytes.
     *
     * @var string
     */
    public const CONST_MAX_BYTES = 'SPARXSTAR_DICT_NODE_MAX_BYTES';

    /**
     * Default upstream timeout in seconds. A reader is waiting on this request.
     *
     * @var int
     */
    private const DEFAULT_TIMEOUT = 8;

    /**
     * Default response ceiling in bytes. A display payload is one entry or a
     * bounded result set; anything larger is not a payload this adapter asked for.
     *
     * @var int
     */
    private const DEFAULT_MAX_BYTES = 262144;

    /**
     * The identity node's token endpoint URL. Empty when unconfigured.
     *
     * @return string
     */
    public function token_endpoint(): string {
        return $this->string_setting( self::CONST_TOKEN_ENDPOINT, 'sparxstar_dictionary_identity_token_endpoint' );
    }

    /**
     * The Dictionary Node base URL, without a trailing slash. Empty when unconfigured.
     *
     * @return string
     */
    public function node_base_url(): string {
        $value = $this->string_setting( self::CONST_NODE_BASE_URL, 'sparxstar_dictionary_node_base_url' );
        return '' === $value ? '' : rtrim( $value, '/' );
    }

    /**
     * The registered identity `client_id` for this deployment. Empty when unconfigured.
     *
     * @return string
     */
    public function client_id(): string {
        return $this->string_setting( self::CONST_CLIENT_ID, 'sparxstar_dictionary_client_id' );
    }

    /**
     * The registered `kid` of the signing key. Empty when unconfigured.
     *
     * @return string
     */
    public function client_kid(): string {
        return $this->string_setting( self::CONST_CLIENT_KID, 'sparxstar_dictionary_client_kid' );
    }

    /**
     * Absolute path to the RSA private key. Empty when unconfigured.
     *
     * @return string
     */
    public function private_key_path(): string {
        return $this->string_setting( self::CONST_PRIVATE_KEY_PATH, 'sparxstar_dictionary_private_key_path' );
    }

    /**
     * Salt for the opaque reader reference.
     *
     * Falls back to WordPress's own auth salt, which is already deployment-unique
     * and never leaves the server. It is not an identifier of any person.
     *
     * @return string
     */
    public function reader_ref_salt(): string {
        $configured = $this->string_setting( self::CONST_READER_REF_SALT, 'sparxstar_dictionary_reader_ref_salt' );
        if ( '' !== $configured ) {
            return $configured;
        }
        return function_exists( 'wp_salt' ) ? (string) wp_salt( 'auth' ) : '';
    }

    /**
     * Whether the display adapter is switched on. Defaults to OFF: the Node's
     * JSON routes must be deployed and verified before the UI switches
     * (contract §7 step 4).
     *
     * @return bool
     */
    public function is_adapter_enabled(): bool {
        $enabled = defined( self::CONST_ADAPTER_ENABLED ) && (bool) constant( self::CONST_ADAPTER_ENABLED );
        return (bool) apply_filters( 'sparxstar_dictionary_display_adapter_enabled', $enabled );
    }

    /**
     * Upstream request timeout in seconds.
     *
     * @return int
     */
    public function timeout(): int {
        $value = defined( self::CONST_TIMEOUT ) ? (int) constant( self::CONST_TIMEOUT ) : self::DEFAULT_TIMEOUT;
        $value = (int) apply_filters( 'sparxstar_dictionary_node_timeout', $value );
        return $value > 0 ? min( $value, 30 ) : self::DEFAULT_TIMEOUT;
    }

    /**
     * Maximum accepted upstream response size, in bytes.
     *
     * @return int
     */
    public function max_response_bytes(): int {
        $value = defined( self::CONST_MAX_BYTES ) ? (int) constant( self::CONST_MAX_BYTES ) : self::DEFAULT_MAX_BYTES;
        $value = (int) apply_filters( 'sparxstar_dictionary_node_max_bytes', $value );
        return $value > 0 ? $value : self::DEFAULT_MAX_BYTES;
    }

    /**
     * Whether every required setting is present and valid.
     *
     * @return bool
     */
    public function is_ready(): bool {
        return array() === $this->problems();
    }

    /**
     * Human-readable configuration problems. An empty array means ready.
     *
     * Messages name the CONSTANT that is missing or wrong. They never contain a
     * configured value, so an admin notice cannot leak the Node's location.
     *
     * @return array<int,string> Problem descriptions, already translated.
     */
    public function problems(): array {
        $problems = array();

        if ( '' === $this->client_id() ) {
            $problems[] = $this->missing( self::CONST_CLIENT_ID );
        }
        if ( '' === $this->client_kid() ) {
            $problems[] = $this->missing( self::CONST_CLIENT_KID );
        }

        $endpoint_problem = $this->url_problem( $this->token_endpoint(), self::CONST_TOKEN_ENDPOINT );
        if ( null !== $endpoint_problem ) {
            $problems[] = $endpoint_problem;
        }

        $node_problem = $this->url_problem( $this->node_base_url(), self::CONST_NODE_BASE_URL );
        if ( null !== $node_problem ) {
            $problems[] = $node_problem;
        }

        foreach ( $this->key_problems() as $key_problem ) {
            $problems[] = $key_problem;
        }

        return $problems;
    }

    /**
     * Problems with the private key file: presence, readability, and — the rule
     * that actually matters — that it lives outside the plugin directory and
     * outside the web root (contract §4.2).
     *
     * @return array<int,string>
     */
    public function key_problems(): array {
        $path = $this->private_key_path();
        if ( '' === $path ) {
            return array( $this->missing( self::CONST_PRIVATE_KEY_PATH ) );
        }

        $real = realpath( $path );
        if ( false === $real || ! is_file( $real ) || ! is_readable( $real ) ) {
            return array(
                sprintf(
                    /* translators: %s: PHP constant name. */
                    __( '%s does not point at a readable file.', 'sparxstar-3iatlas-dictionary' ),
                    self::CONST_PRIVATE_KEY_PATH
                ),
            );
        }

        $problems = array();

        foreach ( $this->forbidden_key_roots() as $label => $root ) {
            if ( '' !== $root && str_starts_with( $real . DIRECTORY_SEPARATOR, $root ) ) {
                $problems[] = sprintf(
                    /* translators: 1: PHP constant name, 2: the forbidden location, already translated. */
                    __( 'The key named by %1$s is inside %2$s. It must live outside the plugin directory and outside the web root; move it and update the constant.', 'sparxstar-3iatlas-dictionary' ),
                    self::CONST_PRIVATE_KEY_PATH,
                    $label
                );
            }
        }

        return $problems;
    }

    /**
     * Locations a private key must never be stored in, keyed by a translated label.
     *
     * @return array<string,string> Label => normalised directory path with a trailing separator.
     */
    private function forbidden_key_roots(): array {
        $roots = array();

        if ( defined( 'SPARX_3IATLAS_PATH' ) ) {
            $plugin = realpath( (string) constant( 'SPARX_3IATLAS_PATH' ) );
            if ( false !== $plugin ) {
                $roots[ __( 'the plugin directory', 'sparxstar-3iatlas-dictionary' ) ] = $plugin . DIRECTORY_SEPARATOR;
            }
        }

        if ( defined( 'ABSPATH' ) ) {
            $web_root = realpath( (string) constant( 'ABSPATH' ) );
            if ( false !== $web_root ) {
                $roots[ __( 'the web root', 'sparxstar-3iatlas-dictionary' ) ] = $web_root . DIRECTORY_SEPARATOR;
            }
        }

        return $roots;
    }

    /**
     * Validate a configured URL.
     *
     * A bearer token is carried on these requests, so plain HTTP is refused
     * except to a loopback host, where there is no network to intercept.
     *
     * @param string $value    The configured URL.
     * @param string $constant The constant that supplied it.
     * @return string|null A problem description, or null when the URL is usable.
     */
    private function url_problem( string $value, string $constant ): ?string {
        if ( '' === $value ) {
            return $this->missing( $constant );
        }

        $parts = wp_parse_url( $value );
        if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
            return sprintf(
                /* translators: %s: PHP constant name. */
                __( '%s is not a valid absolute URL.', 'sparxstar-3iatlas-dictionary' ),
                $constant
            );
        }

        $scheme = strtolower( (string) $parts['scheme'] );
        $host   = strtolower( (string) $parts['host'] );

        if ( 'https' === $scheme ) {
            return null;
        }

        $loopback = array( 'localhost', '127.0.0.1', '::1' );
        if ( 'http' === $scheme && in_array( $host, $loopback, true ) ) {
            return null;
        }

        return sprintf(
            /* translators: %s: PHP constant name. */
            __( '%s must use https. A machine credential is sent on this request.', 'sparxstar-3iatlas-dictionary' ),
            $constant
        );
    }

    /**
     * Standard "not configured" problem text.
     *
     * @param string $constant The constant that is unset.
     * @return string
     */
    private function missing( string $constant ): string {
        return sprintf(
            /* translators: %s: PHP constant name. */
            __( '%s is not defined. Define it in wp-config.php.', 'sparxstar-3iatlas-dictionary' ),
            $constant
        );
    }

    /**
     * Read a string setting from its constant, then let a filter override it.
     *
     * @param string $constant The constant name.
     * @param string $filter   The filter name.
     * @return string Trimmed value, or an empty string when unset.
     */
    private function string_setting( string $constant, string $filter ): string {
        $value = defined( $constant ) ? (string) constant( $constant ) : '';
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Every caller passes a literal `sparxstar_dictionary_*` filter name; the names are declared on the methods above.
        return trim( (string) apply_filters( $filter, $value ) );
    }
}
