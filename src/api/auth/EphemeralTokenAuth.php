<?php
/**
 * Verifies ephemeral page tokens minted by the /page-token endpoint.
 *
 * Token format: base64url( JSON {iat,exp,scope} ) . '.' . hex( HMAC-SHA256 )
 * Signing key:  SPARXSTAR_DICT_PAGE_SECRET constant (must be defined in wp-config.php)
 * Quota:        600 req/token (transient keyed on SHA-256 of token, TTL = token exp)
 *
 * @package Starisian\Sparxstar\IAtlas\api\auth
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\IAtlas\api\auth;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

/**
 * EphemeralTokenAuth — verifies HMAC-SHA256 signed ephemeral page tokens.
 */
final class EphemeralTokenAuth implements DictionaryAuthInterface {

    /**
     * Maximum requests per ephemeral token.
     *
     * @var int
     */
    private const TOKEN_QUOTA = 600;

    /**
     * Resolve an ephemeral page token from the X-Page-Token header.
     *
     * @param \WP_REST_Request $request Incoming REST request.
     * @return AuthContext|\WP_Error AuthContext on success; WP_Error on failure.
     */
    public function resolve( \WP_REST_Request $request ): AuthContext|\WP_Error {
        $raw_token = trim( (string) $request->get_header( 'X-Page-Token' ) );

        if ( '' === $raw_token ) {
            return new \WP_Error(
                'missing_page_token',
                __( 'X-Page-Token header is required.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 401 )
            );
        }

        $parts = explode( '.', $raw_token, 2 );
        if ( 2 !== count( $parts ) ) {
            return new \WP_Error(
                'invalid_page_token',
                __( 'Malformed page token.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 401 )
            );
        }

        [ $encoded_payload, $provided_sig ] = $parts;

        // Fail closed: if the secret is not configured no token can be valid.
        if ( ! defined( 'SPARXSTAR_DICT_PAGE_SECRET' ) ) {
            return new \WP_Error(
                'configuration_error',
                __( 'Page token verification is not configured.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 500 )
            );
        }

        // Verify signature using constant-time comparison.
        $secret       = $this->get_secret();
        $expected_sig = hash_hmac( 'sha256', $encoded_payload, $secret );
        if ( ! hash_equals( $expected_sig, $provided_sig ) ) {
            return new \WP_Error(
                'invalid_page_token',
                __( 'Page token signature is invalid.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 401 )
            );
        }

        // Decode payload — restore padding stripped at mint time before strict decode.
        $remainder      = strlen( $encoded_payload ) % 4;
        $padded_payload = $remainder ? $encoded_payload . str_repeat( '=', 4 - $remainder ) : $encoded_payload;
        $json_payload   = base64_decode( strtr( $padded_payload, '-_', '+/' ), true );
        if ( false === $json_payload ) {
            return new \WP_Error(
                'invalid_page_token',
                __( 'Page token payload could not be decoded.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 401 )
            );
        }

        $payload = json_decode( $json_payload, true );
        if ( ! is_array( $payload ) ) {
            return new \WP_Error(
                'invalid_page_token',
                __( 'Page token payload is not valid JSON.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 401 )
            );
        }

        $exp   = isset( $payload['exp'] ) ? (int) $payload['exp'] : 0;
        $scope = isset( $payload['scope'] ) ? (string) $payload['scope'] : '';

        // Check expiry.
        if ( $exp <= time() ) {
            return new \WP_Error(
                'expired_page_token',
                __( 'Page token has expired.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 401 )
            );
        }

        // Check and decrement quota.
        $token_hash    = hash( 'sha256', $raw_token );
        $transient_key = 'aiwa_dict_ptquota_' . $token_hash;
        $ttl           = max( 1, $exp - time() );

        $used = get_transient( $transient_key );
        if ( false === $used ) {
            $used = 0;
        }
        $used = (int) $used;

        if ( $used >= self::TOKEN_QUOTA ) {
            return new \WP_Error(
                'quota_exceeded',
                __( 'Page token quota exceeded.', 'sparxstar-3iatlas-dictionary' ),
                array(
                    'status'      => 429,
                    'retry_after' => 86400,
                    'headers'     => array( 'Retry-After' => '86400' ),
                )
            );
        }

        set_transient( $transient_key, $used + 1, $ttl );
        $remaining = self::TOKEN_QUOTA - ( $used + 1 );

        return new AuthContext(
            credential_type: 'ephemeral',
            scope: '' !== $scope ? $scope : 'browse',
            key_id: null,
            quota_remaining: $remaining,
        );
    }

    /**
     * Retrieve the signing secret from the defined constant.
     *
     * @return string The signing secret.
     */
    private function get_secret(): string {
        if ( ! defined( 'SPARXSTAR_DICT_PAGE_SECRET' ) ) {
            return '';
        }
        return (string) constant( 'SPARXSTAR_DICT_PAGE_SECRET' );
    }
}
