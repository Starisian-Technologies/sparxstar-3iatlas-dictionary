<?php

declare(strict_types=1);

/**
 * Contract that every credential verifier for the 3iAtlas Dictionary REST API must satisfy.
 *
 * When sparxstar-identity ships, its RS256 JWT verifier becomes a third implementation
 * behind DictionaryAuthResolver — no endpoint code changes required.
 *
 * @package Starisian\Sparxstar\IAtlas\api\auth
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

namespace Starisian\Sparxstar\IAtlas\api\auth;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

/**
 * Interface DictionaryAuthInterface
 *
 * Implemented by each credential verifier. resolve() either returns a populated
 * AuthContext DTO or a WP_Error with the appropriate HTTP status code.
 */
interface DictionaryAuthInterface {

    /**
     * Resolve authentication from the incoming request.
     *
     * @param \WP_REST_Request $request The incoming REST request.
     * @return AuthContext|\WP_Error AuthContext on success; WP_Error (401/403/429) on failure.
     */
    public function resolve( \WP_REST_Request $request ): AuthContext|\WP_Error;
}
