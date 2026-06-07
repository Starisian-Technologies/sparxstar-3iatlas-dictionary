<?php

declare(strict_types=1);

/**
 * Immutable data transfer object representing a successfully resolved credential.
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
 * AuthContext DTO — produced by a DictionaryAuthInterface implementation after
 * a credential has been successfully verified.
 *
 * credential_type: 'ephemeral' | 'api_key'
 * scope:           'browse' | 'consumer'
 * key_id:          opaque label/identifier — never the raw key value
 * quota_remaining: requests remaining in the current window for this credential
 */
readonly class AuthContext {

    /**
     * AuthContext constructor.
     *
     * @param string      $credential_type 'ephemeral' or 'api_key'.
     * @param string      $scope           'browse' or 'consumer'.
     * @param string|null $key_id          Key label/identifier; null for ephemeral tokens.
     * @param int         $quota_remaining Remaining requests in the current window.
     */
    public function __construct(
        public string $credential_type,
        public string $scope,
        public ?string $key_id,
        public int $quota_remaining,
    ) {}
}
