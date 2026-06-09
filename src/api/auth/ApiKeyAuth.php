<?php
/**
 * Verifies long-lived API keys sent via the X-Api-Key header.
 *
 * Keys are stored as SHA-256 hashes in the WP option aiwa_dict_api_keys.
 * Daily quota defaults to 10,000 and is filterable per key entry.
 * The plaintext key value is never logged — only the label is used in diagnostics.
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
 * ApiKeyAuth — verifies API keys against the aiwa_dict_api_keys option.
 */
final class ApiKeyAuth implements DictionaryAuthInterface {

    /**
     * Option name that stores the API key registry.
     *
     * @var string
     */
    public const KEYS_OPTION = 'aiwa_dict_api_keys';

    /**
     * Default daily quota per API key.
     *
     * @var int
     */
    private const DEFAULT_DAILY_QUOTA = 10000;

    /**
     * Resolve an API key from the X-Api-Key header.
     *
     * @param \WP_REST_Request $request Incoming REST request.
     * @return AuthContext|\WP_Error AuthContext on success; WP_Error on failure.
     */
    public function resolve( \WP_REST_Request $request ): AuthContext|\WP_Error {
        $raw_key = trim( (string) $request->get_header( 'X-Api-Key' ) );

        if ( '' === $raw_key ) {
            return new \WP_Error(
                'missing_api_key',
                __( 'X-Api-Key header is required.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 401 )
            );
        }

        $key_hash  = hash( 'sha256', $raw_key );
        $key_entry = $this->find_key( $key_hash );

        if ( null === $key_entry ) {
            return new \WP_Error(
                'invalid_api_key',
                __( 'API key not found or inactive.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 403 )
            );
        }

        if ( ! ( $key_entry['active'] ?? false ) ) {
            return new \WP_Error(
                'invalid_api_key',
                __( 'API key has been revoked.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 403 )
            );
        }

        // Check and decrement daily quota.
        $daily_quota   = (int) apply_filters(
            'sparxstar_dict_key_daily_quota',
            $key_entry['daily_quota'] ?? self::DEFAULT_DAILY_QUOTA,
            $key_entry
        );
        $today         = gmdate( 'Y-m-d' );
        $transient_key = 'aiwa_dict_keyquota_' . $key_hash . '_' . $today;

        $used = get_transient( $transient_key );
        if ( false === $used ) {
            $used = 0;
        }
        $used = (int) $used;

        if ( $used >= $daily_quota ) {
            return new \WP_Error(
                'quota_exceeded',
                __( 'Daily API key quota exceeded.', 'sparxstar-3iatlas-dictionary' ),
                array(
                    'status'      => 429,
                    'retry_after' => 86400,
                    'headers'     => array( 'Retry-After' => '86400' ),
                )
            );
        }

        // Quota TTL: seconds remaining until midnight UTC.
        $midnight = strtotime( 'tomorrow midnight UTC' );
        $ttl      = max( 1, ( false !== $midnight ? $midnight : time() + 86400 ) - time() );

        set_transient( $transient_key, $used + 1, $ttl );
        $remaining = $daily_quota - ( $used + 1 );
        $label     = (string) ( $key_entry['label'] ?? '' );

        return new AuthContext(
            credential_type: 'api_key',
            scope: 'consumer',
            key_id: '' !== $label ? $label : null,
            quota_remaining: $remaining,
        );
    }

    /**
     * Find a key entry by its SHA-256 hash. Returns null if not found.
     *
     * @param string $key_hash SHA-256 hash of the raw API key.
     * @return array<string,mixed>|null The key entry array, or null if not found.
     */
    private function find_key( string $key_hash ): ?array {
        $keys = get_option( self::KEYS_OPTION, array() );
        if ( ! is_array( $keys ) ) {
            return null;
        }

        foreach ( $keys as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }
            if ( isset( $entry['key_hash'] ) && hash_equals( $entry['key_hash'], $key_hash ) ) {
                return $entry;
            }
        }

        return null;
    }
}
