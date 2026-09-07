<?php
/**
 * Storage contract for the machine access token.
 *
 * The access token is the ONLY thing this plugin persists from upstream
 * (contract §4.2, §9). No dictionary record is ever stored here.
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
 * Server-side cache for a short-lived machine access token.
 */
interface TokenCacheInterface {

    /**
     * The cached token, or an empty string when there is none.
     *
     * @param string $key Cache key.
     * @return string
     */
    public function get( string $key ): string;

    /**
     * Store a token for a bounded number of seconds.
     *
     * @param string $key     Cache key.
     * @param string $token   The access token.
     * @param int    $seconds Lifetime in seconds; always shorter than the token's own.
     * @return void
     */
    public function set( string $key, string $token, int $seconds ): void;

    /**
     * Discard a cached token — used the moment the Node answers 401.
     *
     * @param string $key Cache key.
     * @return void
     */
    public function forget( string $key ): void;
}
