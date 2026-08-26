<?php
/**
 * CORS handler for the 3iAtlas Dictionary REST API.
 *
 * Reads allowed origins from the aiwa_dict_cors_origins option (filterable).
 * Handles OPTIONS preflight before auth runs. Never emits
 * Access-Control-Allow-Credentials.
 *
 * @package Starisian\Sparxstar\IAtlas\api
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\IAtlas\api;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

/**
 * Sparxstar3IAtlasDictionaryCors — CORS middleware scoped to the dictionary REST namespace.
 */
final class Sparxstar3IAtlasDictionaryCors {

    /**
     * The REST route prefix this CORS handler is scoped to.
     *
     * Inlined to avoid a load-order dependency on Sparxstar3IAtlasDictionaryRestApi.
     *
     * @var string
     */
    private const ROUTE_PREFIX = '/sparxstar/v1/dictionary';

    /**
     * Register all CORS-related hooks.
     *
     * @return void
     */
    public function register_hooks(): void {
        // Priority 1 — run before route registration so headers are set early.
        add_action( 'rest_api_init', array( $this, 'intercept_options_preflight' ), 1 );
        add_filter( 'rest_pre_serve_request', array( $this, 'add_cors_headers' ), 10, 4 );
    }

    /**
     * Register a filter on rest_request_before_callbacks to short-circuit OPTIONS
     * requests for this namespace before any auth or handler runs.
     *
     * @return void
     */
    public function intercept_options_preflight(): void {
        add_filter( 'rest_request_before_callbacks', array( $this, 'handle_options_request' ), 1, 3 );
    }

    /**
     * Short-circuit OPTIONS preflight requests for the dictionary namespace.
     * Respond 200 with CORS headers and an empty body.
     *
     * @param \WP_REST_Response|\WP_Error|true $response Current response (may be pre-empted response or true).
     * @param array<string,mixed>              $handler  Route handler array.
     * @param \WP_REST_Request                 $request  The incoming request.
     * @return \WP_REST_Response|\WP_Error|true
     */
    public function handle_options_request( $response, $handler, \WP_REST_Request $request ) {
        if ( 'OPTIONS' !== $request->get_method() ) {
            return $response;
        }

        if ( ! $this->is_dictionary_route( $request ) ) {
            return $response;
        }

        $origin = trim( (string) $request->get_header( 'Origin' ) );
        if ( '' === $origin || ! $this->is_allowed_origin( $origin ) ) {
            return $response;
        }

        $preflight = new \WP_REST_Response( null, 200 );
        $this->emit_cors_headers( $preflight, $origin );
        return $preflight;
    }

    /**
     * Add CORS headers to the response for matched origins on dictionary routes.
     * Hooked into rest_pre_serve_request.
     *
     * @param bool                        $served  Whether the request has already been served.
     * @param \WP_REST_Response|\WP_Error $result  Response or error; headers only applied when a WP_REST_Response is passed.
     * @param \WP_REST_Request            $request The request object.
     * @param \WP_REST_Server             $server  The REST server instance (required by filter signature).
     * @return bool
     */
    public function add_cors_headers( bool $served, \WP_REST_Response|\WP_Error $result, \WP_REST_Request $request, \WP_REST_Server $server ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $server is required by the rest_pre_serve_request filter signature but intentionally unused
        if ( ! $this->is_dictionary_route( $request ) ) {
            return $served;
        }

        if ( ! $result instanceof \WP_REST_Response ) {
            return $served;
        }

        $origin = trim( (string) $request->get_header( 'Origin' ) );
        if ( '' === $origin || ! $this->is_allowed_origin( $origin ) ) {
            return $served;
        }

        $this->emit_cors_headers( $result, $origin );
        return $served;
    }

    /**
     * Emit the standard CORS headers onto a response object.
     *
     * @param \WP_REST_Response $response The response to decorate.
     * @param string            $origin   The matched, allowlisted origin.
     * @return void
     */
    private function emit_cors_headers( \WP_REST_Response $response, string $origin ): void {
        $response->header( 'Access-Control-Allow-Origin', $origin );
        $headers       = array_change_key_case( $response->get_headers(), CASE_LOWER );
        $existing_vary = $headers['vary'] ?? '';
        if ( '' === $existing_vary ) {
            $response->header( 'Vary', 'Origin' );
        } elseif ( ! in_array( 'origin', array_map( 'strtolower', array_map( 'trim', explode( ',', $existing_vary ) ) ), true ) ) {
            $response->header( 'Vary', $existing_vary . ', Origin' );
        }
        $response->header( 'Access-Control-Allow-Methods', 'GET, POST, OPTIONS' );
        // X-Api-Key remains architecturally condemned (spec §1.1) even while it is
        // temporarily served under the §1.4 migration exception. It retires at cutover,
        // at which point this whole handler stops matching the route.
        $response->header( 'Access-Control-Allow-Headers', 'Content-Type, If-None-Match, X-Api-Key, X-Page-Token' );
        $response->header( 'Access-Control-Expose-Headers', 'ETag, X-RateLimit-Remaining, Retry-After' );
        $response->header( 'Access-Control-Max-Age', '86400' );
        // Never emit Access-Control-Allow-Credentials.
    }

    /**
     * Determine whether the request is scoped to the dictionary REST namespace.
     *
     * @param \WP_REST_Request $request The incoming request.
     * @return bool
     */
    private function is_dictionary_route( \WP_REST_Request $request ): bool {
        // Spec §1.4 step 4: at cutover, CORS is removed from the route. After that the
        // only callers are server-side systems, for which CORS is meaningless, and
        // continuing to advertise browser access would contradict §1.1.
        if ( \Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryProtection::is_cutover_complete() ) {
            return false;
        }

        $route = $request->get_route();

        // Boundary-checked, not a bare prefix match: a bare prefix would also match
        // `/sparxstar/v1/dictionary-evil` and emit CORS headers for it. Same fix, and
        // the same reasoning, as the nginx route regex's `(?:/|$)`.
        return self::ROUTE_PREFIX === $route || str_starts_with( $route, self::ROUTE_PREFIX . '/' );
    }

    /**
     * Check whether an origin is on the allow list.
     *
     * @param string $origin The Origin header value to check.
     * @return bool
     */
    private function is_allowed_origin( string $origin ): bool {
        $allowlist = $this->get_allowlist();
        return in_array( $origin, $allowlist, true );
    }

    /**
     * Retrieve the CORS origin allowlist from the option, with filter support.
     *
     * @return string[]
     */
    private function get_allowlist(): array {
        $stored = get_option( 'aiwa_dict_cors_origins', array() );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }

        /**
         * Filter the CORS origin allowlist for the dictionary REST API.
         *
         * @param string[] $stored Array of allowed origin strings.
         */
        $filtered = apply_filters( 'sparxstar_dict_cors_origins', $stored );
        return is_array( $filtered ) ? $filtered : $stored;
    }
}
