<?php
/**
 * Mints machine access tokens from the identity node using `private_key_jwt`.
 *
 * Implements the client half of the identity node's ratified
 * `SERVICE-CLIENT-AUTH-SPEC-v1.0` §2, quoted in the display contract §4.1:
 *
 * - RS256 client assertion; header `alg`/`typ`/`kid`.
 * - `iss` = `sub` = the registered `client_id`.
 * - `aud` = the token endpoint URL EXACTLY — not the requested audience. That is
 *   what stops an assertion captured en route to one endpoint being replayed at
 *   another, and it is why the audience travels as a form parameter instead.
 * - `exp` no more than 60 seconds after `iat`; `jti` cryptographically random.
 * - The issued token lives 300 seconds and there is no refresh token.
 *
 * Signing is `openssl_sign()` with OPENSSL_ALGO_SHA256. This plugin deliberately
 * carries no JWT library and no Composer dependency was added for this.
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
 * Obtains and caches the machine access token presented to the Dictionary Node.
 */
final class IdentityMachineTokenClient {

    /**
     * The audience this deployment requests. Named by the contract (§4.1) and by
     * the Node's own caller registry, not invented here.
     *
     * @var string
     */
    public const AUDIENCE = 'dictionary';

    /**
     * RFC 7523 client-assertion type. Fixed by the specification.
     *
     * @var string
     */
    public const ASSERTION_TYPE = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';

    /**
     * Maximum assertion lifetime permitted by the identity spec, in seconds.
     *
     * @var int
     */
    private const ASSERTION_LIFETIME = 60;

    /**
     * Seconds of headroom subtracted from a token's own lifetime before caching.
     *
     * A token that expires in flight is a failed page render, so the cache is
     * always shorter than the token — never "to the second".
     *
     * @var int
     */
    private const EXPIRY_HEADROOM = 45;

    /**
     * Lifetime assumed when the identity node does not state one.
     *
     * @var int
     */
    private const ASSUMED_LIFETIME = 300;

    /**
     * Deployment configuration.
     *
     * @var DisplayConfig
     */
    private DisplayConfig $config;

    /**
     * Server-side token store.
     *
     * @var TokenCacheInterface
     */
    private TokenCacheInterface $cache;

    /**
     * HTTP transport. Signature: fn(string $url, array $args): array|\WP_Error.
     *
     * @var callable|null
     */
    private $transport;

    /**
     * Constructor.
     *
     * @param DisplayConfig            $config    Deployment configuration.
     * @param TokenCacheInterface|null $cache     Token store; defaults to transients.
     * @param callable|null            $transport HTTP transport; defaults to wp_remote_post().
     */
    public function __construct( DisplayConfig $config, ?TokenCacheInterface $cache = null, ?callable $transport = null ) {
        $this->config    = $config;
        $this->cache     = $cache ?? new TransientTokenCache();
        $this->transport = $transport;
    }

    /**
     * A usable access token, minting one when the cache is empty.
     *
     * @return string|\WP_Error The bearer token, or a controlled error.
     */
    public function token(): string|\WP_Error {
        $cached = $this->cache->get( $this->cache_key() );
        if ( '' !== $cached ) {
            return $cached;
        }

        return $this->mint();
    }

    /**
     * Discard the cached token. Called the moment the Node answers 401.
     *
     * @return void
     */
    public function forget(): void {
        $this->cache->forget( $this->cache_key() );
    }

    /**
     * Mint a fresh token, bypassing and refreshing the cache.
     *
     * @return string|\WP_Error
     */
    public function mint(): string|\WP_Error {
        $problems = $this->config->problems();
        if ( array() !== $problems ) {
            return new \WP_Error(
                'display_adapter_not_configured',
                __( 'The dictionary display adapter is not configured.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 503 )
            );
        }

        $assertion = $this->build_assertion();
        if ( is_wp_error( $assertion ) ) {
            return $assertion;
        }

        $response = $this->post(
            $this->config->token_endpoint(),
            array(
                'timeout'     => $this->config->timeout(),
                'redirection' => 0,
                'headers'     => array(
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body'        => array(
                    'grant_type'            => 'client_credentials',
                    'client_id'             => $this->config->client_id(),
                    'audience'              => self::AUDIENCE,
                    'client_assertion_type' => self::ASSERTION_TYPE,
                    'client_assertion'      => $assertion,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error(
                'identity_unreachable',
                __( 'The identity service could not be reached.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 503 )
            );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $body   = (string) wp_remote_retrieve_body( $response );

        if ( 200 !== $status ) {
            // The identity node returns one code for thirty reasons on purpose
            // (spec §2.2). Nothing from its body is surfaced or logged here.
            return new \WP_Error(
                'identity_refused',
                __( 'The identity service refused this deployment&#8217;s credential.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 503 )
            );
        }

        if ( strlen( $body ) > $this->config->max_response_bytes() ) {
            return $this->malformed_identity_response();
        }

        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) || ! isset( $decoded['access_token'] ) || ! is_string( $decoded['access_token'] ) || '' === $decoded['access_token'] ) {
            return $this->malformed_identity_response();
        }

        $token = $decoded['access_token'];

        $lifetime = isset( $decoded['expires_in'] ) ? (int) $decoded['expires_in'] : self::ASSUMED_LIFETIME;
        if ( $lifetime < 1 ) {
            $lifetime = self::ASSUMED_LIFETIME;
        }

        $this->cache->set( $this->cache_key(), $token, max( 1, $lifetime - self::EXPIRY_HEADROOM ) );

        return $token;
    }

    /**
     * Build and sign the `private_key_jwt` client assertion.
     *
     * @return string|\WP_Error The compact JWS, or a controlled error.
     */
    private function build_assertion(): string|\WP_Error {
        $key = openssl_pkey_get_private( 'file://' . $this->config->private_key_path() );
        if ( false === $key ) {
            return new \WP_Error(
                'display_adapter_key_unreadable',
                __( 'The dictionary display adapter&#8217;s signing key could not be loaded.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 503 )
            );
        }

        $client_id = $this->config->client_id();
        $issued_at = time();

        $header = array(
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $this->config->client_kid(),
        );

        $claims = array(
            'iss' => $client_id,
            'sub' => $client_id,
            // Exactly the token endpoint URL — never the requested audience.
            'aud' => $this->config->token_endpoint(),
            'iat' => $issued_at,
            'exp' => $issued_at + self::ASSERTION_LIFETIME,
            'jti' => bin2hex( random_bytes( 16 ) ),
        );

        $signing_input = self::base64url( (string) wp_json_encode( $header ) )
            . '.' . self::base64url( (string) wp_json_encode( $claims ) );

        $signature = '';
        $signed    = openssl_sign( $signing_input, $signature, $key, OPENSSL_ALGO_SHA256 );

        if ( true !== $signed || '' === $signature ) {
            return new \WP_Error(
                'display_adapter_sign_failed',
                __( 'The dictionary display adapter could not sign its client assertion.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 503 )
            );
        }

        return $signing_input . '.' . self::base64url( $signature );
    }

    /**
     * The generic malformed-response error. Kept in one place so no variant of
     * it ever grows a detail from the upstream body.
     *
     * @return \WP_Error
     */
    private function malformed_identity_response(): \WP_Error {
        return new \WP_Error(
            'identity_malformed_response',
            __( 'The identity service returned an unusable response.', 'sparxstar-3iatlas-dictionary' ),
            array( 'status' => 502 )
        );
    }

    /**
     * Perform the POST through the injected transport, or wp_remote_post().
     *
     * @param string              $url  Absolute URL.
     * @param array<string,mixed> $args Request arguments.
     * @return array<string,mixed>|\WP_Error
     */
    private function post( string $url, array $args ): array|\WP_Error {
        if ( null !== $this->transport ) {
            return ( $this->transport )( $url, $args );
        }
        return wp_remote_post( $url, $args );
    }

    /**
     * Cache key, bound to the identity and client this token was minted for, so
     * a configuration change can never reuse a token minted for something else.
     *
     * @return string
     */
    private function cache_key(): string {
        return substr(
            hash( 'sha256', $this->config->token_endpoint() . '|' . $this->config->client_id() . '|' . self::AUDIENCE ),
            0,
            32
        );
    }

    /**
     * Base64url encoding without padding, per RFC 7515 §2.
     *
     * @param string $value Raw bytes.
     * @return string
     */
    private static function base64url( string $value ): string {
        return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
    }
}
