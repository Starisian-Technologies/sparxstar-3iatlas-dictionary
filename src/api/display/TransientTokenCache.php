<?php
/**
 * Transient-backed store for the machine access token.
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
 * Stores the access token in a WordPress transient, so a persistent object
 * cache holds it in memory where one is configured and the options table
 * holds it where one is not. Either way it is server-side and short-lived.
 */
final class TransientTokenCache implements TokenCacheInterface {

    /**
     * Transient key prefix.
     *
     * @var string
     */
    private const PREFIX = 'sparxstar_dict_svc_token_';

    /**
     * The cached token, or an empty string when there is none.
     *
     * @param string $key Cache key.
     * @return string
     */
    public function get( string $key ): string {
        $stored = get_transient( self::PREFIX . $key );
        return is_string( $stored ) ? $stored : '';
    }

    /**
     * Store a token for a bounded number of seconds.
     *
     * @param string $key     Cache key.
     * @param string $token   The access token.
     * @param int    $seconds Lifetime in seconds.
     * @return void
     */
    public function set( string $key, string $token, int $seconds ): void {
        if ( $seconds < 1 ) {
            return;
        }
        set_transient( self::PREFIX . $key, $token, $seconds );
    }

    /**
     * Discard a cached token.
     *
     * @param string $key Cache key.
     * @return void
     */
    public function forget( string $key ): void {
        delete_transient( self::PREFIX . $key );
    }
}
