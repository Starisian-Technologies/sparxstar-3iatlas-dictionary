<?php

declare(strict_types=1);

/**
 * Shared client IP and transient-based rate limiting helper.
 *
 * @package Starisian\Sparxstar\IAtlas\api
 * @license Starisian Technologies Proprietary License (STPL)
 */

namespace Starisian\Sparxstar\IAtlas\api;

trait Sparxstar3IAtlasRateLimitTrait
{
    private function check_rate_limit(): bool
    {
        // TODO: Replace with Helios token introspection when available.
        $ip = $this->get_client_ip();
        $key = 'sparx_3iatlas_dict_rl_' . md5($ip);
        $lock_key = 'sparx_3iatlas_dict_rl_lock_' . md5($ip);

        if (!$this->acquire_rate_limit_lock($lock_key)) {
            return false;
        }

        try {
            $now = time();
            $state = get_transient($key);

            if (!is_array($state)) {
                $state = array(
                    'count'        => 0,
                    'window_start' => $now,
                );
            }

            $window_start = isset($state['window_start']) ? (int) $state['window_start'] : $now;
            $count = isset($state['count']) ? (int) $state['count'] : 0;

            if (($now - $window_start) >= self::RATE_WINDOW) {
                $window_start = $now;
                $count = 0;
            }

            if ($count >= self::RATE_LIMIT) {
                return false;
            }

            $count++;
            $expires_in = max(1, self::RATE_WINDOW - ($now - $window_start));

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
            $this->release_rate_limit_lock($lock_key);
        }
    }

    private function acquire_rate_limit_lock(string $lock_key): bool
    {
        $lock_ttl = 5;
        $attempts = 5;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $now = time();

            if (add_option($lock_key, (string) $now, '', false)) {
                return true;
            }

            $existing = (int) get_option($lock_key, 0);

            if ($existing > 0 && ($now - $existing) >= $lock_ttl) {
                delete_option($lock_key);

                if (add_option($lock_key, (string) $now, '', false)) {
                    return true;
                }
            }

            usleep(50000);
        }

        return false;
    }

    private function release_rate_limit_lock(string $lock_key): void
    {
        delete_option($lock_key);
    }

    private function get_client_ip(): string
    {
        $remote_addr = trim(sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? '')));

        $remote_ip = false !== filter_var($remote_addr, FILTER_VALIDATE_IP) ? $remote_addr : '';
        $trust_proxy_headers = defined('SPARX_3IATLAS_TRUST_PROXY_HEADERS')
            && true === constant('SPARX_3IATLAS_TRUST_PROXY_HEADERS');

        $candidates = $trust_proxy_headers
            ? array(
                (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
                (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
                $remote_ip,
            )
            : array($remote_ip);

        foreach ($candidates as $candidate) {
            if ('' === $candidate) {
                continue;
            }

            $ip = trim(explode(',', $candidate)[0]);
            if ('' === $ip) {
                continue;
            }

            if (false !== filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return 'unknown';
    }
}
