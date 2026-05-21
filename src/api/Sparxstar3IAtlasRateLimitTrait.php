<?php
/**
 * Shared client IP and transient-based rate limiting helper.
 *
 * @package Starisian\Sparxstar\IAtlas\api
 * @license Starisian Technologies Proprietary License (STPL)
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\IAtlas\api;

trait Sparxstar3IAtlasRateLimitTrait {

    /**
     * Checks whether the current client has exceeded the rate limit.
     *
     * @return bool True if the request is allowed, false if rate-limited.
     */
    private function check_rate_limit(): bool {
        // TODO: Replace with Helios token introspection when available.
        $ip       = $this->get_client_ip();
        $key      = 'sparx_3iatlas_dict_rl_' . md5( $ip );
        $lock_key = 'sparx_3iatlas_dict_rl_lock_' . md5( $ip );

        if ( ! $this->acquire_rate_limit_lock( $lock_key ) ) {
            return false;
        }

        try {
            $now   = time();
            $state = get_transient( $key );

            if ( ! is_array( $state ) ) {
                $state = array(
                    'count'        => 0,
                    'window_start' => $now,
                );
            }

            $window_start = isset( $state['window_start'] ) ? (int) $state['window_start'] : $now;
            $count        = isset( $state['count'] ) ? (int) $state['count'] : 0;

            if ( ( $now - $window_start ) >= self::RATE_WINDOW ) {
                $window_start = $now;
                $count        = 0;
            }

            if ( $count >= self::RATE_LIMIT ) {
                return false;
            }

            ++$count;
            $expires_in = max( 1, self::RATE_WINDOW - ( $now - $window_start ) );

            set_transient(
                $key,
                array(
                    'count'        => $count,
                    'window_start' => $window_start,
                ),
                $expires_in
            );

            return true;
        } finally {
            $this->release_rate_limit_lock( $lock_key );
        }
    }

    /**
     * Attempts to acquire a named lock to prevent race conditions.
     *
     * @param string $lock_key The lock key.
     * @return bool True if the lock was acquired.
     */
    private function acquire_rate_limit_lock( string $lock_key ): bool {
        $lock_ttl    = 5;
        $attempts    = 5;
        $cache_group = 'sparx_3iatlas_rate_limit';

        for ( $attempt = 0; $attempt < $attempts; $attempt++ ) {
            if ( wp_using_ext_object_cache() ) {
                if ( wp_cache_add( $lock_key, '1', $cache_group, $lock_ttl ) ) {
                    return true;
                }
            } elseif ( $this->acquire_mysql_lock( $lock_key ) ) {
                    return true;
            }

            usleep( 50000 );
        }

        return false;
    }

    /**
     * Releases a previously acquired rate-limit lock.
     *
     * @param string $lock_key The lock key.
     * @return void
     */
    private function release_rate_limit_lock( string $lock_key ): void {
        if ( wp_using_ext_object_cache() ) {
            wp_cache_delete( $lock_key, 'sparx_3iatlas_rate_limit' );
            return;
        }

        $this->release_mysql_lock( $lock_key );
    }

    /**
     * Acquires a MySQL GET_LOCK for rate-limit synchronisation.
     *
     * @param string $lock_key The lock key.
     * @return bool True if the lock was acquired.
     */
    private function acquire_mysql_lock( string $lock_key ): bool {
        global $wpdb;

        if ( ! $wpdb instanceof \wpdb ) {
            return false;
        }

        // MySQL lock names are limited to 64 characters; prefix + md5 stays safely below that limit.
        $mysql_lock_name = 'sparx_3iatlas_rl_' . md5( $lock_key );
        $acquired        = $wpdb->get_var(
            $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $mysql_lock_name )
        );

        return 1 === (int) $acquired;
    }

    /**
     * Releases a MySQL GET_LOCK for rate-limit synchronisation.
     *
     * @param string $lock_key The lock key.
     * @return void
     */
    private function release_mysql_lock( string $lock_key ): void {
        global $wpdb;

        if ( ! $wpdb instanceof \wpdb ) {
            return;
        }

        $mysql_lock_name = 'sparx_3iatlas_rl_' . md5( $lock_key );
        $wpdb->get_var(
            $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $mysql_lock_name )
        );
    }

    /**
     * Returns the client IP address, respecting proxy headers when configured.
     *
     * @return string The validated client IP address, or 'unknown'.
     */
    private function get_client_ip(): string {
        $remote_addr = trim( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders,WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__ -- Value is validated with filter_var( FILTER_VALIDATE_IP ) below.
        $remote_addr = trim( $remote_addr );

        $remote_ip           = false !== filter_var( $remote_addr, FILTER_VALIDATE_IP ) ? $remote_addr : '';
        $trust_proxy_headers = defined( 'SPARX_3IATLAS_TRUST_PROXY_HEADERS' )
            && true === constant( 'SPARX_3IATLAS_TRUST_PROXY_HEADERS' );

        $candidates = $trust_proxy_headers
            ? array(
                (string) ( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '' ), // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders -- Value is validated with filter_var( FILTER_VALIDATE_IP ) below.
                (string) ( $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '' ), // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders -- Value is validated with filter_var( FILTER_VALIDATE_IP ) below.
                $remote_ip,
            )
            : array( $remote_ip );

        foreach ( $candidates as $candidate ) {
            if ( '' === $candidate ) {
                continue;
            }

            $ip = trim( explode( ',', $candidate )[0] );
            if ( '' === $ip ) {
                continue;
            }

            $is_forwarded_header_ip = $trust_proxy_headers && $ip !== $remote_ip;
            $flags                  = $is_forwarded_header_ip ? ( FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) : 0;

            if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, array( 'flags' => $flags ) ) ) {
                return $ip;
            }
        }

        return 'unknown';
    }
}
