<?php
/**
 * Composite credential resolver for the 3iAtlas Dictionary REST API.
 *
 * Selection follows the Asset Protection Specification's access model (§1):
 *
 * - At TARGET STATE (cutover complete, spec §1.4 step 4) the only accepted
 *   credential is a per-system service credential presented as
 *   `Authorization: Bearer`. Browser-held credentials are refused outright:
 *   `X-Api-Key` is architecturally condemned by §1.1 and ephemeral page tokens
 *   are cursors, never identities (§2).
 * - BEFORE cutover the documented migration exception applies (§1): the deployed
 *   browser app keeps working on its existing credentials, because enforcing M2M
 *   before the consuming-system path is live would lock out every player (§1.4).
 *
 * System credentials are tried first in both states, so a consuming system that
 * has already migrated is never mistaken for a browser.
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
 * DictionaryAuthResolver — selects and delegates to the appropriate credential verifier.
 */
final class DictionaryAuthResolver implements DictionaryAuthInterface {

    /**
     * Resolve credentials from the incoming request.
     *
     * @param \WP_REST_Request $request Incoming REST request.
     * @return AuthContext|\WP_Error AuthContext on success; WP_Error on failure.
     */
    public function resolve( \WP_REST_Request $request ): AuthContext|\WP_Error {
        $has_system_credential = SystemCredentialAuth::is_presented( $request );
        $has_page_token        = '' !== trim( (string) $request->get_header( 'X-Page-Token' ) );
        $has_api_key           = '' !== trim( (string) $request->get_header( 'X-Api-Key' ) );

        // A system credential is always tried first, in either state.
        if ( $has_system_credential ) {
            return ( new SystemCredentialAuth() )->resolve( $request );
        }

        // Target state: nothing else is a credential here.
        if ( Sparxstar3IAtlasDictionaryProtection::is_cutover_complete() ) {
            if ( $has_api_key || $has_page_token ) {
                Sparxstar3IAtlasDictionaryProtection::log_security_event(
                    'anomaly',
                    'retired_credential_presented_after_cutover',
                    array(
                        'route'             => $request->get_route(),
                        'presented_api_key' => $has_api_key,
                        'presented_token'   => $has_page_token,
                    )
                );
            }

            return new \WP_Error(
                'system_credential_required',
                __( 'This API serves approved systems only. Provide a system credential via the Authorization header.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 401 )
            );
        }

        // Migration exception (spec §1, §1.4): existing browser-app credentials continue
        // to work until the consuming-system path is live and verified.
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
            __( 'Authentication required.', 'sparxstar-3iatlas-dictionary' ),
            array( 'status' => 401 )
        );
    }
}
