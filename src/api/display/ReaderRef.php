<?php
/**
 * The opaque per-reader reference sent upstream as `X-Reader-Ref`.
 *
 * The Node meters this whole site as ONE credential with a per-reader
 * sub-budget underneath it. That sub-budget only works if the adapter
 * distinguishes readers — without the header every reader shares one bucket and
 * a single scraper exhausts the deployment for everybody (contract §4.3).
 *
 * The value is opaque BY CONTRACT. It is derived from a random per-browser
 * identifier this plugin mints, salted and hashed. It is never a username, an
 * email, an IP address, a WordPress user id, or anything else the Node could
 * correlate back to a person. Reader identity is WordPress's business and stays
 * here: the Node must remain unable to learn which entries a named person read.
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
 * Derives the opaque reader reference for the current request.
 */
final class ReaderRef {

    /**
     * Cookie holding the pseudonymous browser identifier.
     *
     * The cookie carries random bytes and nothing else: it identifies a browser
     * to this site, not a person to anyone.
     *
     * @var string
     */
    public const COOKIE = 'sparxstar_dict_reader';

    /**
     * Cookie lifetime in seconds.
     *
     * @var int
     */
    private const LIFETIME = 2592000;

    /**
     * Deployment configuration (supplies the salt).
     *
     * @var DisplayConfig
     */
    private DisplayConfig $config;

    /**
     * Memoised value for this request.
     *
     * @var string
     */
    private string $resolved = '';

    /**
     * Constructor.
     *
     * @param DisplayConfig $config Deployment configuration.
     */
    public function __construct( DisplayConfig $config ) {
        $this->config = $config;
    }

    /**
     * The opaque reference for the current reader.
     *
     * @return string A 32-character hex string. Never empty.
     */
    public function current(): string {
        if ( '' !== $this->resolved ) {
            return $this->resolved;
        }

        $session = $this->session_id();

        // Hashed with a server-held salt so the value in the cookie and the
        // value the Node sees are not the same string, and neither is reversible.
        $this->resolved = substr( hash_hmac( 'sha256', $session, $this->config->reader_ref_salt() ), 0, 32 );

        return $this->resolved;
    }

    /**
     * The pseudonymous browser identifier, minting and setting one when absent.
     *
     * When headers are already sent the cookie cannot be set, so a per-request
     * identifier is used instead. That meters more coarsely than intended but is
     * never a fatal and never identifies anyone.
     *
     * @return string
     */
    private function session_id(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- Read-only public view; the cookie carries no privilege. It cannot move to JavaScript: the value derived from it becomes a server-only upstream header the browser must never see (contract §4.2).
        $raw = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( (string) wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';

        if ( 32 === strlen( $raw ) && ctype_xdigit( $raw ) ) {
            return $raw;
        }

        $minted = bin2hex( random_bytes( 16 ) );

        if ( ! headers_sent() ) {
            // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie -- The identifier must be minted server-side: it is never exposed to page JavaScript, and the responses that carry it are sent `no-store`, so no shared cache serves it.
            setcookie(
                self::COOKIE,
                $minted,
                array(
                    'expires'  => time() + self::LIFETIME,
                    'path'     => defined( 'COOKIEPATH' ) && '' !== (string) constant( 'COOKIEPATH' ) ? (string) constant( 'COOKIEPATH' ) : '/',
                    'secure'   => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                )
            );
        }

        return $minted;
    }
}
