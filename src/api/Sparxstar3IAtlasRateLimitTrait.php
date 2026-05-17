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
        $key = 'dict_rl_' . md5($ip);
        $hit = (int) get_transient($key);

        if ($hit >= self::RATE_LIMIT) {
            return false;
        }

        set_transient($key, $hit + 1, self::RATE_WINDOW);
        return true;
    }

    private function get_client_ip(): string
    {
        $candidates = array(
            (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
            (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        );

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
