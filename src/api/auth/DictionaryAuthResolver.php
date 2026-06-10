<?php
/**
 * Composite credential resolver for the 3iAtlas Dictionary REST API.
 *
 * Tries EphemeralTokenAuth first (if X-Page-Token present), then ApiKeyAuth
 * (if X-Api-Key present). Returns the first successful AuthContext or a
 * WP_Error 401 if neither credential is presented.
 *
 * When sparxstar-identity ships, its RS256 JWT verifier becomes a third
 * implementation behind this doorway — no endpoint code changes required.
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
 * DictionaryAuthResolver — selects and delegates to the appropriate credential verifier.
 */
final class DictionaryAuthResolver implements DictionaryAuthInterface {

    /**
     * Resolve credentials from the incoming request.
     *
     * Tries ephemeral token first, then API key. Returns 401 if neither is present.
     *
     * @param \WP_REST_Request $request Incoming REST request.
     * @return AuthContext|\WP_Error AuthContext on success; WP_Error on failure.
     */
    public function resolve( \WP_REST_Request $request ): AuthContext|\WP_Error {
        $has_page_token = '' !== trim( (string) $request->get_header( 'X-Page-Token' ) );
        $has_api_key    = '' !== trim( (string) $request->get_header( 'X-Api-Key' ) );

        if ( $has_page_token ) {
            $result = ( new EphemeralTokenAuth() )->resolve( $request );
            if ( ! is_wp_error( $result ) ) {
                return $result;
            }
            // Propagate real errors (quota exceeded 429, configuration failure 500,
            // invalid/expired token 401) so the client receives the correct status code
            // and can act accordingly. Only fall through to API key on missing_page_token,
            // which cannot happen here since $has_page_token is already true, but satisfies
            // the interface contract if the header disappears between the check and resolve.
            if ( 'missing_page_token' !== $result->get_error_code() ) {
                return $result;
            }
        }

        if ( $has_api_key ) {
            return ( new ApiKeyAuth() )->resolve( $request );
        }

        return new \WP_Error(
            'no_credentials',
            __( 'Authentication required. Provide X-Page-Token or X-Api-Key.', 'sparxstar-3iatlas-dictionary' ),
            array( 'status' => 401 )
        );
    }
}
