<?php
/**
 * Verifies per-system service credentials presented as `Authorization: Bearer`.
 *
 * Implements the interim form of spec §1.2: "per-system static secrets — long
 * random values, stored only in server environments/secret managers, hashed at
 * rest on the WordPress side, presented as `Authorization: Bearer`, rotation
 * runbook with per-system schedule."
 *
 * One credential per consuming system, never one shared token. Each credential
 * carries a stable `credential_id` so a leak names the system that lost it, a
 * rotation touches one system, and a revocation is surgical (spec §1.2, §3).
 *
 * The target form — RS256 machine tokens from `id.sparxstar.com` with
 * `aud: dictionary`, verified against the shared JWKS — becomes a sibling
 * implementation of DictionaryAuthInterface behind the same resolver, with no
 * endpoint changes. It is blocked on the identity node's client-credentials
 * endpoint (ADR brief D-6/D-7), not on this class.
 *
 * @package Starisian\Sparxstar\IAtlas\api\auth
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\IAtlas\api\auth;

use Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryProtection;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

/**
 * SystemCredentialAuth — verifies system credentials for machine-to-machine callers.
 */
final class SystemCredentialAuth implements DictionaryAuthInterface {

    /**
     * Option storing the system credential registry.
     *
     * Stores only SHA-256 hashes. A plaintext secret is displayed once at generation
     * time and is never written to the database, a log, or a response.
     *
     * @var string
     */
    public const CREDENTIALS_OPTION = 'sparxstar_dict_system_credentials';

    /**
     * Credential type recorded on a successful AuthContext.
     *
     * @var string
     */
    public const CREDENTIAL_TYPE = 'system';

    /**
     * Extract the bearer value from an Authorization header.
     *
     * @param string $header Raw Authorization header value.
     * @return string The bearer value, or an empty string when absent or malformed.
     */
    public static function parse_bearer( string $header ): string {
        $header = trim( $header );
        if ( '' === $header ) {
            return '';
        }

        if ( 1 !== preg_match( '/^Bearer\s+(\S+)$/i', $header, $matches ) ) {
            return '';
        }

        return $matches[1];
    }

    /**
     * Whether the request presents a bearer credential at all.
     *
     * @param \WP_REST_Request $request Incoming REST request.
     * @return bool True when an Authorization: Bearer header is present.
     */
    public static function is_presented( \WP_REST_Request $request ): bool {
        return '' !== self::parse_bearer( (string) $request->get_header( 'Authorization' ) );
    }

    /**
     * Resolve a system credential from the Authorization header.
     *
     * @param \WP_REST_Request $request Incoming REST request.
     * @return AuthContext|\WP_Error AuthContext on success; WP_Error on failure.
     */
    public function resolve( \WP_REST_Request $request ): AuthContext|\WP_Error {
        $secret = self::parse_bearer( (string) $request->get_header( 'Authorization' ) );

        if ( '' === $secret ) {
            return new \WP_Error(
                'missing_system_credential',
                __( 'A system credential is required.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 401 )
            );
        }

        $record = self::find_by_secret( $secret );

        if ( null === $record ) {
            // The presented value is fingerprinted, never logged in full (spec §3).
            Sparxstar3IAtlasDictionaryProtection::log_security_event(
                'anomaly',
                'system_credential_rejected',
                array(
                    'fingerprint' => Sparxstar3IAtlasDictionaryProtection::fingerprint_credential( $secret ),
                    'route'       => $request->get_route(),
                )
            );

            return new \WP_Error(
                'invalid_system_credential',
                __( 'System credential not recognised.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 403 )
            );
        }

        if ( true !== ( $record['active'] ?? false ) ) {
            return new \WP_Error(
                'revoked_system_credential',
                __( 'System credential has been revoked.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 403 )
            );
        }

        $credential_id = (string) ( $record['credential_id'] ?? '' );

        return new AuthContext(
            credential_type: self::CREDENTIAL_TYPE,
            scope: 'system',
            key_id: '' !== $credential_id ? $credential_id : null,
            quota_remaining: UniqueEntryBudget::ceiling_for( $credential_id, $record ),
        );
    }

    /**
     * Look up a credential record by its plaintext secret.
     *
     * Compares against every active record with hash_equals so the comparison time
     * does not depend on how far down the registry a match sits.
     *
     * @param string $secret The presented plaintext secret.
     * @return array<string,mixed>|null The matching record, or null.
     */
    public static function find_by_secret( string $secret ): ?array {
        $hash  = hash( 'sha256', $secret );
        $found = null;

        foreach ( self::all() as $record ) {
            if ( ! is_array( $record ) || ! isset( $record['secret_hash'] ) ) {
                continue;
            }

            if ( hash_equals( (string) $record['secret_hash'], $hash ) ) {
                $found = $record;
            }
        }

        return $found;
    }

    /**
     * Look up a credential record by its credential ID.
     *
     * @param string $credential_id The credential identifier.
     * @return array<string,mixed>|null The matching record, or null.
     */
    public static function find_by_id( string $credential_id ): ?array {
        foreach ( self::all() as $record ) {
            if ( is_array( $record ) && (string) ( $record['credential_id'] ?? '' ) === $credential_id ) {
                return $record;
            }
        }

        return null;
    }

    /**
     * All stored credential records.
     *
     * @return array<int,array<string,mixed>> The credential registry.
     */
    public static function all(): array {
        $stored = get_option( self::CREDENTIALS_OPTION, array() );
        return is_array( $stored ) ? $stored : array();
    }

    /**
     * Persist the credential registry.
     *
     * @param array<int,array<string,mixed>> $records The registry to store.
     * @return bool True on success.
     */
    public static function save( array $records ): bool {
        return update_option( self::CREDENTIALS_OPTION, array_values( $records ), false );
    }
}
