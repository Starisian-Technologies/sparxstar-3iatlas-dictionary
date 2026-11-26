<?php
/**
 * The server-side HTTP client for the Dictionary Node's display tier.
 *
 * Nothing in this class ever reaches a browser: not the base URL, not the
 * bearer token, not a request header (contract §4.2, §9).
 *
 * Three guards run before a body is trusted, in this order, because the seam
 * invites exactly these failures:
 *
 * 1. **Content type must be JSON.** The Node's `/w/:slug` and `/search` return
 *    HTML by design, so a mistyped base URL, a captive proxy, a WAF block or a
 *    login wall yields a `200` whose body is a web page. An HTML body is a
 *    controlled error and is never parsed and never rendered.
 * 2. **Size against a ceiling**, refused before decoding.
 * 3. **Envelope shape** — `{success:true,data,meta?}` on success (the key is
 *    `success`, not `ok`), `{code,message,data:{status}}` on error, which maps
 *    to WP_Error without a translation layer.
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
 * Performs authenticated GETs against the Dictionary Node and returns decoded,
 * envelope-checked payloads or a WP_Error.
 */
final class NodeHttpClient {

    /**
     * Header carrying the opaque per-reader reference (contract §4.3).
     *
     * @var string
     */
    public const READER_REF_HEADER = 'X-Reader-Ref';

    /**
     * Deployment configuration.
     *
     * @var DisplayConfig
     */
    private DisplayConfig $config;

    /**
     * Machine token client.
     *
     * @var IdentityMachineTokenClient
     */
    private IdentityMachineTokenClient $tokens;

    /**
     * HTTP transport. Signature: fn(string $url, array $args): array|\WP_Error.
     *
     * @var callable|null
     */
    private $transport;

    /**
     * Constructor.
     *
     * @param DisplayConfig              $config    Deployment configuration.
     * @param IdentityMachineTokenClient $tokens    Machine token client.
     * @param callable|null              $transport HTTP transport; defaults to wp_remote_get().
     */
    public function __construct( DisplayConfig $config, IdentityMachineTokenClient $tokens, ?callable $transport = null ) {
        $this->config    = $config;
        $this->tokens    = $tokens;
        $this->transport = $transport;
    }

    /**
     * GET a display-tier route.
     *
     * On a 401 the cached token is discarded, exactly ONE fresh token is minted
     * and the request is retried exactly ONCE. A second 401 is a controlled
     * error — never a third attempt.
     *
     * @param string               $path       Route path, e.g. '/v1/display/entry'.
     * @param array<string,string> $query      Query parameters.
     * @param string               $reader_ref Opaque reader reference; '' omits the header.
     * @return array{data:mixed,meta:array<string,mixed>}|\WP_Error
     */
    public function get( string $path, array $query = array(), string $reader_ref = '' ): array|\WP_Error {
        if ( ! $this->config->is_ready() ) {
            return new \WP_Error(
                'display_adapter_not_configured',
                __( 'The dictionary display adapter is not configured.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 503 )
            );
        }

        $token = $this->tokens->token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $result = $this->attempt( $path, $query, $reader_ref, $token );

        if ( self::is_unauthorized( $result ) ) {
            // One discard, one fresh token, one retry. Then stop.
            $this->tokens->forget();
            $token = $this->tokens->mint();
            if ( is_wp_error( $token ) ) {
                return $token;
            }
            $result = $this->attempt( $path, $query, $reader_ref, $token );

            if ( self::is_unauthorized( $result ) ) {
                return new \WP_Error(
                    'display_upstream_unauthorized',
                    __( 'The dictionary service did not accept this site&#8217;s credential.', 'sparxstar-3iatlas-dictionary' ),
                    array( 'status' => 502 )
                );
            }
        }

        return $result;
    }

    /**
     * One request attempt, fully validated.
     *
     * @param string               $path       Route path.
     * @param array<string,string> $query      Query parameters.
     * @param string               $reader_ref Opaque reader reference.
     * @param string               $token      Bearer token.
     * @return array{data:mixed,meta:array<string,mixed>}|\WP_Error
     */
    private function attempt( string $path, array $query, string $reader_ref, string $token ): array|\WP_Error {
        $url = $this->config->node_base_url() . '/' . ltrim( $path, '/' );
        if ( array() !== $query ) {
            $url = add_query_arg( array_map( 'strval', $query ), $url );
        }

        $headers = array(
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        );

        // Entry-bearing requests carry the opaque reader reference so the Node's
        // per-reader sub-budget works. Without it the whole site meters as one
        // bucket and a single scraper exhausts every reader's allowance (§4.3).
        if ( '' !== $reader_ref ) {
            $headers[ self::READER_REF_HEADER ] = $reader_ref;
        }

        $response = $this->request(
            $url,
            array(
                'timeout'             => $this->config->timeout(),
                'redirection'         => 0,
                'headers'             => $headers,
                'limit_response_size' => $this->config->max_response_bytes() + 1,
            )
        );

        if ( is_wp_error( $response ) ) {
            // Timeouts, DNS failures and refused connections all land here. The
            // upstream message is not surfaced: it names hosts.
            return new \WP_Error(
                'display_upstream_unavailable',
                __( 'The dictionary service is temporarily unavailable.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 503 )
            );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $body   = (string) wp_remote_retrieve_body( $response );

        // GUARD 1 — content type. An HTML body is never parsed and never rendered.
        if ( ! self::is_json_content_type( $this->header_of( $response, 'content-type' ) ) ) {
            return new \WP_Error(
                'display_upstream_not_json',
                __( 'The dictionary service returned a non-JSON response.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 502 )
            );
        }

        // GUARD 2 — size, refused before any decode is attempted.
        if ( strlen( $body ) > $this->config->max_response_bytes() ) {
            return new \WP_Error(
                'display_upstream_too_large',
                __( 'The dictionary service returned an oversized response.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 502 )
            );
        }

        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) ) {
            return new \WP_Error(
                'display_upstream_malformed',
                __( 'The dictionary service returned an unreadable response.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 502 )
            );
        }

        // GUARD 3 — envelope. The error envelope is already WordPress-shaped, so
        // it becomes a WP_Error without translation.
        if ( $status < 200 || $status > 299 ) {
            return self::error_from_envelope( $decoded, $status );
        }

        if ( true !== ( $decoded['success'] ?? null ) || ! array_key_exists( 'data', $decoded ) ) {
            return new \WP_Error(
                'display_upstream_malformed',
                __( 'The dictionary service returned an unreadable response.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 502 )
            );
        }

        $meta = ( isset( $decoded['meta'] ) && is_array( $decoded['meta'] ) ) ? $decoded['meta'] : array();

        return array(
            'data' => $decoded['data'],
            'meta' => $meta,
        );
    }

    /**
     * Whether a result is an upstream 401.
     *
     * @param array<string,mixed>|\WP_Error $result Attempt result.
     * @return bool
     */
    private static function is_unauthorized( array|\WP_Error $result ): bool {
        if ( ! is_wp_error( $result ) ) {
            return false;
        }
        $data = $result->get_error_data();
        return is_array( $data ) && 401 === (int) ( $data['status'] ?? 0 );
    }

    /**
     * Convert an upstream error envelope into a WP_Error.
     *
     * Two rules from the contract §3 are honoured here: an authentication
     * failure is one code for every internal reason, and an authorization
     * failure never names the scope a route wants. Neither is elaborated on.
     *
     * @param array<string,mixed> $decoded Decoded body.
     * @param int                 $status  HTTP status.
     * @return \WP_Error
     */
    private static function error_from_envelope( array $decoded, int $status ): \WP_Error {
        /*
         * The code is echoed to the reader's browser, so it is accepted only in
         * the shape the contract's own codes take. An upstream that has been
         * tampered with does not get to choose what this site renders.
         */
        $code = 'display_upstream_error';
        if ( isset( $decoded['code'] ) && is_string( $decoded['code'] )
            && 1 === preg_match( '/^[a-z][a-z0-9_]{0,63}$/', $decoded['code'] ) ) {
            $code = $decoded['code'];
        }

        $envelope_status = 0;
        if ( isset( $decoded['data'] ) && is_array( $decoded['data'] ) && isset( $decoded['data']['status'] ) ) {
            $envelope_status = (int) $decoded['data']['status'];
        }
        $effective = $envelope_status > 0 ? $envelope_status : $status;

        return new \WP_Error( $code, self::message_for( $code ), array( 'status' => $effective ) );
    }

    /**
     * A reader-facing message for an upstream error code.
     *
     * The upstream `message` is deliberately NOT reused: it is written for an
     * operator, and for `unauthorized` and `forbidden` it must stay opaque.
     *
     * @param string $code Upstream error code.
     * @return string
     */
    private static function message_for( string $code ): string {
        switch ( $code ) {
            case 'not_found':
                return __( 'That word is not in this dictionary.', 'sparxstar-3iatlas-dictionary' );
            case 'budget_exceeded':
                return __( 'The dictionary is busy right now. Please try again shortly.', 'sparxstar-3iatlas-dictionary' );
            case 'over_cap':
            case 'bad_request':
                return __( 'That request could not be understood.', 'sparxstar-3iatlas-dictionary' );
            case 'unauthorized':
            case 'forbidden':
                return __( 'The dictionary service did not accept this site&#8217;s credential.', 'sparxstar-3iatlas-dictionary' );
            default:
                return __( 'The dictionary service could not answer that request.', 'sparxstar-3iatlas-dictionary' );
        }
    }

    /**
     * Whether a Content-Type header names JSON.
     *
     * @param string $content_type Raw header value.
     * @return bool
     */
    private static function is_json_content_type( string $content_type ): bool {
        $type = strtolower( trim( strtok( $content_type, ';' ) ?: '' ) );
        return 'application/json' === $type || str_ends_with( $type, '+json' );
    }

    /**
     * Read one header from a response, tolerating both the WP_Http case-insensitive
     * dictionary and the plain array an injected transport may return.
     *
     * @param array<string,mixed> $response Response.
     * @param string              $name     Lower-case header name.
     * @return string
     */
    private function header_of( array $response, string $name ): string {
        $headers = $response['headers'] ?? array();

        if ( is_array( $headers ) ) {
            foreach ( $headers as $key => $value ) {
                if ( strtolower( (string) $key ) === $name ) {
                    return is_array( $value ) ? (string) reset( $value ) : (string) $value;
                }
            }
            return '';
        }

        return (string) wp_remote_retrieve_header( $response, $name );
    }

    /**
     * Perform the GET through the injected transport, or wp_remote_get().
     *
     * @param string              $url  Absolute URL.
     * @param array<string,mixed> $args Request arguments.
     * @return array<string,mixed>|\WP_Error
     */
    private function request( string $url, array $args ): array|\WP_Error {
        if ( null !== $this->transport ) {
            return ( $this->transport )( $url, $args );
        }
        // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get -- vip_safe_wp_remote_get() exists only on VIP Go, and this call already carries an explicit timeout, a response ceiling, a content-type guard and a controlled error for every failure path.
        return wp_remote_get( $url, $args );
    }
}
