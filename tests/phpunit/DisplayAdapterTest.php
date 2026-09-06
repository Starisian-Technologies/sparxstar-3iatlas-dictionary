<?php
/**
 * Contract tests for the Dictionary Node display adapter.
 *
 * These cover the fixture matrix in
 * `.github/instructions/3IATLAS-DICTIONARY-DISPLAY-JSON-CONTRACT-v1.0.md` §8,
 * without a live Node.
 *
 * FIXTURE PROVENANCE — read this before trusting the payload shapes.
 * The contract says one fixture set, owned by the Node repo, is consumed by
 * both sides. At the time these tests were written the Node's `/v1/display/*`
 * routes and their fixtures were not yet committed, so the payloads in
 * `tests/fixtures/display/` were built from what could actually be read:
 * contract §3 (the envelope, verified there against `src/http/envelope.ts`),
 * contract §8 (the matrix), and the `DisplayEntry` interface in the Node's
 * `src/domain/types.ts`. When the Node publishes its fixture set, replace the
 * files in that directory with it — these tests are written to read the
 * directory, not to restate the payloads.
 *
 * @package Starisian\Sparxstar\IAtlas\tests
 * @license Starisian Technologies Proprietary License (STPL)
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\IAtlas\Tests;

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\IAtlas\api\display\DictionaryDisplaySourceInterface;
use Starisian\Sparxstar\IAtlas\api\display\DisplayConfig;
use Starisian\Sparxstar\IAtlas\api\display\DisplayLanguageSettings;
use Starisian\Sparxstar\IAtlas\api\display\DisplayText;
use Starisian\Sparxstar\IAtlas\api\display\IdentityMachineTokenClient;
use Starisian\Sparxstar\IAtlas\api\display\LiveJsonDisplaySource;
use Starisian\Sparxstar\IAtlas\api\display\NodeHttpClient;
use Starisian\Sparxstar\IAtlas\api\display\ReaderRef;
use Starisian\Sparxstar\IAtlas\api\display\TokenCacheInterface;

/**
 * In-memory token cache, so a test never touches a transient.
 */
final class ArrayTokenCache implements TokenCacheInterface {

    /** @var array<string,string> */
    public array $store = [];

    public function get( string $key ): string {
        return $this->store[ $key ] ?? '';
    }

    public function set( string $key, string $token, int $seconds ): void {
        $this->store[ $key ] = $token;
    }

    public function forget( string $key ): void {
        unset( $this->store[ $key ] );
    }
}

/**
 * Contract tests for the display adapter.
 */
final class DisplayAdapterTest extends TestCase {

    /** Absolute path of the generated RSA private key, outside the plugin tree. */
    private static string $key_path = '';

    /** PEM of the matching public key, used to verify assertions. */
    private static string $public_key = '';

    /** Temp directory standing in for the plugin directory. */
    private static string $plugin_dir = '';

    /** Requests the fake identity transport received. */
    private array $identity_calls = [];

    /** Requests the fake Node transport received. */
    private array $node_calls = [];

    /** Queued Node responses, consumed in order. */
    private array $node_queue = [];

    public static function setUpBeforeClass(): void {
        $base = sys_get_temp_dir() . '/sparxstar-display-adapter-tests';
        @mkdir( $base, 0700, true );

        self::$plugin_dir = $base . '/plugin/';
        @mkdir( self::$plugin_dir, 0700, true );

        if ( ! defined( 'SPARX_3IATLAS_PATH' ) ) {
            define( 'SPARX_3IATLAS_PATH', self::$plugin_dir );
        }

        $resource = openssl_pkey_new(
            [
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]
        );
        openssl_pkey_export( $resource, $pem );

        self::$key_path = $base . '/display-adapter-test.key';
        file_put_contents( self::$key_path, $pem );
        chmod( self::$key_path, 0600 );

        $details          = openssl_pkey_get_details( $resource );
        self::$public_key = (string) $details['key'];
    }

    protected function setUp(): void {
        $this->identity_calls = [];
        $this->node_calls     = [];
        $this->node_queue     = [];

        $GLOBALS['__wp_filters']       = [];
        $GLOBALS['__wp_options_store'] = [];

        add_filter( 'sparxstar_dictionary_identity_token_endpoint', static fn(): string => 'https://identity.invalid/oauth2/token' );
        add_filter( 'sparxstar_dictionary_node_base_url', static fn(): string => 'https://node.invalid' );
        add_filter( 'sparxstar_dictionary_client_id', static fn(): string => 'test-client-id' );
        add_filter( 'sparxstar_dictionary_client_kid', static fn(): string => 'test-kid' );
        add_filter( 'sparxstar_dictionary_private_key_path', static fn(): string => self::$key_path );
        add_filter( 'sparxstar_dictionary_reader_ref_salt', static fn(): string => 'test-salt' );
    }

    protected function tearDown(): void {
        $GLOBALS['__wp_filters'] = [];
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function fixture( string $name ): string {
        return (string) file_get_contents( __DIR__ . '/../fixtures/display/' . $name );
    }

    /** A well-formed upstream response. */
    private function json_response( string $body, int $status = 200 ): array {
        return [
            'response' => [ 'code' => $status ],
            'headers'  => [ 'content-type' => 'application/json; charset=utf-8' ],
            'body'     => $body,
        ];
    }

    /** The identity transport: always issues a token, and records the request. */
    private function identity_transport(): callable {
        return function ( string $url, array $args ): array {
            $this->identity_calls[] = [
                'url'  => $url,
                'args' => $args,
            ];
            return $this->json_response(
                (string) wp_json_encode(
                    [
                        'access_token' => 'test-access-token-' . count( $this->identity_calls ),
                        'token_type'   => 'Bearer',
                        'expires_in'   => 300,
                        'audience'     => 'dictionary',
                    ]
                )
            );
        };
    }

    /** The Node transport: replays the queued responses, recording each request. */
    private function node_transport(): callable {
        return function ( string $url, array $args ) {
            $this->node_calls[] = [
                'url'  => $url,
                'args' => $args,
            ];
            $next = array_shift( $this->node_queue );
            return $next ?? $this->json_response( '{"success":true,"data":{}}' );
        };
    }

    private function source( array $responses ): LiveJsonDisplaySource {
        $this->node_queue = $responses;
        $config           = new DisplayConfig();

        return new LiveJsonDisplaySource(
            new NodeHttpClient(
                $config,
                new IdentityMachineTokenClient( $config, new ArrayTokenCache(), $this->identity_transport() ),
                $this->node_transport()
            )
        );
    }

    private function assertWpError( mixed $result, string $code ): void {
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( $code, $result->get_error_code() );
    }

    // -----------------------------------------------------------------------
    // §8 — Content type
    // -----------------------------------------------------------------------

    public function test_html_upstream_is_a_controlled_error_and_is_never_parsed(): void {
        $html   = $this->fixture( 'upstream-html-body.html' );
        $source = $this->source(
            [
                [
                    'response' => [ 'code' => 200 ],
                    'headers'  => [ 'content-type' => 'text/html; charset=utf-8' ],
                    'body'     => $html,
                ],
            ]
        );

        $result = $source->get_entry( 'kaŋo', 'zxx', 'reader-ref' );

        $this->assertWpError( $result, 'display_upstream_not_json' );
        $this->assertStringNotContainsString( 'Access denied', $result->get_error_message() );
        $this->assertStringNotContainsString( '<html', $result->get_error_message() );
    }

    public function test_a_json_body_served_as_html_is_still_refused(): void {
        $source = $this->source(
            [
                [
                    'response' => [ 'code' => 200 ],
                    'headers'  => [ 'content-type' => 'text/html' ],
                    'body'     => $this->fixture( 'entry-success.json' ),
                ],
            ]
        );

        $this->assertWpError( $source->get_entry( 'kaŋo', 'zxx', 'r' ), 'display_upstream_not_json' );
    }

    // -----------------------------------------------------------------------
    // §8 — Success envelope
    // -----------------------------------------------------------------------

    public function test_success_envelope_is_read_and_the_entry_is_normalised(): void {
        $source = $this->source( [ $this->json_response( $this->fixture( 'entry-success.json' ) ) ] );

        $result = $source->get_entry( 'kaŋo', 'zxx', 'reader-ref' );

        $this->assertIsArray( $result );
        $this->assertSame( 'kaŋo', $result['entry']['slug'] );
        $this->assertSame( 'kaŋo', $result['entry']['headword'] );
        $this->assertSame( 'zxx', $result['entry']['language'] );
        $this->assertSame( 'noun', $result['entry']['part_of_speech'] );
        $this->assertCount( 1, $result['entry']['examples'] );
        $this->assertSame( 'example-audit-reference', $result['meta']['audit_reference'] );
    }

    public function test_the_success_key_is_success_not_ok(): void {
        $source = $this->source( [ $this->json_response( '{"ok":true,"data":{"entry":{"slug":"a","header_word":"a","language":"zxx"}}}' ) ] );

        $this->assertWpError( $source->get_entry( 'a', 'zxx', 'r' ), 'display_upstream_malformed' );
    }

    public function test_language_names_come_from_upstream_including_a_code_used_as_its_own_name(): void {
        $source = $this->source( [ $this->json_response( $this->fixture( 'languages-success.json' ) ) ] );

        $result = $source->get_languages();

        $this->assertSame(
            [
                [
                    'code' => 'zxx',
                    'name' => 'zxx',
                ],
                [
                    'code' => 'mis',
                    'name' => 'Example Reported Name',
                ],
            ],
            $result['languages']
        );
    }

    public function test_search_results_are_normalised_and_counts_are_not_invented(): void {
        $source = $this->source( [ $this->json_response( $this->fixture( 'search-success.json' ) ) ] );

        $result = $source->search( 'ka', 'zxx', 20, 'reader-ref' );

        $this->assertCount( 2, $result['results'] );
        $this->assertSame( 'kaŋo', $result['results'][0]['headword'] );
        $this->assertFalse( $result['meta']['is_suggestion'] );
    }

    public function test_word_of_day_carries_its_date(): void {
        $source = $this->source( [ $this->json_response( $this->fixture( 'word-of-day-success.json' ) ) ] );

        $result = $source->get_word_of_day( 'zxx', 'reader-ref' );

        $this->assertSame( '2026-09-06', $result['date'] );
        $this->assertSame( 'kaŋo', $result['entry']['slug'] );
    }

    public function test_domains_are_normalised(): void {
        $source = $this->source( [ $this->json_response( $this->fixture( 'domains-success.json' ) ) ] );

        $result = $source->get_domains( 'zxx' );

        $this->assertSame( 'dom-one', $result['domains'][0]['code'] );
        $this->assertSame( 'Example Domain Name', $result['domains'][1]['name'] );
    }

    // -----------------------------------------------------------------------
    // §8 — Error envelope
    // -----------------------------------------------------------------------

    public function test_error_envelope_maps_to_wp_error_without_translation(): void {
        $source = $this->source( [ $this->json_response( $this->fixture( 'error-not-found.json' ), 404 ) ] );

        $result = $source->get_entry( 'missing', 'zxx', 'r' );

        $this->assertWpError( $result, 'not_found' );
        $this->assertSame( 404, $result->get_error_data()['status'] );
    }

    public function test_budget_exceeded_is_a_courteous_error_not_a_blank_page(): void {
        $source = $this->source( [ $this->json_response( $this->fixture( 'error-budget-exceeded.json' ), 429 ) ] );

        $result = $source->search( 'ka', 'zxx', 20, 'r' );

        $this->assertWpError( $result, 'budget_exceeded' );
        $this->assertSame( 429, $result->get_error_data()['status'] );
        $this->assertNotSame( '', $result->get_error_message() );
    }

    public function test_a_forbidden_response_never_names_the_scope_it_wanted(): void {
        $body   = '{"code":"forbidden","message":"This credential is not permitted on this route.","data":{"status":403}}';
        $source = $this->source( [ $this->json_response( $body, 403 ) ] );

        $result = $source->get_entry( 'kaŋo', 'zxx', 'r' );

        $this->assertWpError( $result, 'forbidden' );
        $this->assertStringNotContainsString( 'display', strtolower( $result->get_error_message() ) );
        $this->assertStringNotContainsString( 'scope', strtolower( $result->get_error_message() ) );
    }

    // -----------------------------------------------------------------------
    // §8 — Same slug, two languages
    // -----------------------------------------------------------------------

    public function test_the_same_slug_in_two_languages_is_disambiguated_by_the_explicit_language(): void {
        $source = $this->source(
            [
                $this->json_response( $this->fixture( 'entry-success.json' ) ),
                $this->json_response( $this->fixture( 'entry-other-language.json' ) ),
            ]
        );

        $first  = $source->get_entry( 'kaŋo', 'zxx', 'r' );
        $second = $source->get_entry( 'kaŋo', 'mis', 'r' );

        $this->assertStringContainsString( 'lang=zxx', $this->node_calls[0]['url'] );
        $this->assertStringContainsString( 'lang=mis', $this->node_calls[1]['url'] );
        $this->assertSame( 'zxx', $first['entry']['language'] );
        $this->assertSame( 'mis', $second['entry']['language'] );
        $this->assertNotSame( $first['entry']['headword'], $second['entry']['headword'] );
    }

    // -----------------------------------------------------------------------
    // §8 — Rights-filtered fields
    // -----------------------------------------------------------------------

    public function test_a_withheld_field_is_absent_and_the_entry_still_renders(): void {
        $source = $this->source( [ $this->json_response( $this->fixture( 'entry-rights-filtered.json' ) ) ] );

        $result = $source->get_entry( 'ɗaa', 'zxx', 'r' );

        $this->assertSame( 'ɗaa', $result['entry']['headword'] );
        $this->assertNull( $result['entry']['audio_url'] );
        $this->assertNull( $result['entry']['image_url'] );
        $this->assertSame( '', $result['entry']['definition'] );
    }

    // -----------------------------------------------------------------------
    // §8 — Authentication
    // -----------------------------------------------------------------------

    public function test_the_client_assertion_matches_the_identity_spec(): void {
        $source = $this->source( [ $this->json_response( $this->fixture( 'languages-success.json' ) ) ] );
        $source->get_languages();

        $body = $this->identity_calls[0]['args']['body'];

        $this->assertSame( 'client_credentials', $body['grant_type'] );
        $this->assertSame( 'test-client-id', $body['client_id'] );
        $this->assertSame( 'dictionary', $body['audience'] );
        $this->assertSame(
            'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            $body['client_assertion_type']
        );
        $this->assertSame(
            'application/x-www-form-urlencoded',
            $this->identity_calls[0]['args']['headers']['Content-Type']
        );

        [ $header_b64, $claims_b64, $signature_b64 ] = explode( '.', $body['client_assertion'] );

        $header = json_decode( self::base64url_decode( $header_b64 ), true );
        $claims = json_decode( self::base64url_decode( $claims_b64 ), true );

        $this->assertSame( 'RS256', $header['alg'] );
        $this->assertSame( 'JWT', $header['typ'] );
        $this->assertSame( 'test-kid', $header['kid'] );

        $this->assertSame( 'test-client-id', $claims['iss'] );
        $this->assertSame( 'test-client-id', $claims['sub'] );
        // `aud` is the token endpoint URL EXACTLY — never the requested audience.
        $this->assertSame( 'https://identity.invalid/oauth2/token', $claims['aud'] );
        $this->assertNotSame( 'dictionary', $claims['aud'] );
        $this->assertLessThanOrEqual( 60, $claims['exp'] - $claims['iat'] );
        $this->assertLessThanOrEqual( time() + 5, $claims['iat'] );
        $this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $claims['jti'] );

        $verified = openssl_verify(
            $header_b64 . '.' . $claims_b64,
            self::base64url_decode( $signature_b64 ),
            self::$public_key,
            OPENSSL_ALGO_SHA256
        );
        $this->assertSame( 1, $verified );
    }

    public function test_every_assertion_carries_a_fresh_jti(): void {
        $source = $this->source(
            [
                $this->json_response( $this->fixture( 'languages-success.json' ) ),
                $this->json_response( $this->fixture( 'error-unauthorized.json' ), 401 ),
                $this->json_response( $this->fixture( 'languages-success.json' ) ),
            ]
        );

        $source->get_languages();
        $source->get_languages();

        $this->assertCount( 2, $this->identity_calls );

        $jtis = array_map(
            static function ( array $call ): string {
                $claims = json_decode(
                    self::base64url_decode( explode( '.', $call['args']['body']['client_assertion'] )[1] ),
                    true
                );
                return (string) $claims['jti'];
            },
            $this->identity_calls
        );

        $this->assertNotSame( $jtis[0], $jtis[1] );
    }

    public function test_the_token_is_presented_as_a_bearer_credential(): void {
        $source = $this->source( [ $this->json_response( $this->fixture( 'languages-success.json' ) ) ] );
        $source->get_languages();

        $this->assertSame(
            'Bearer test-access-token-1',
            $this->node_calls[0]['args']['headers']['Authorization']
        );
    }

    public function test_a_cached_token_is_reused_rather_than_reminted(): void {
        $cache  = new ArrayTokenCache();
        $config = new DisplayConfig();
        $http   = new NodeHttpClient(
            $config,
            new IdentityMachineTokenClient( $config, $cache, $this->identity_transport() ),
            $this->node_transport()
        );
        $source = new LiveJsonDisplaySource( $http );

        $this->node_queue = [
            $this->json_response( $this->fixture( 'languages-success.json' ) ),
            $this->json_response( $this->fixture( 'domains-success.json' ) ),
        ];

        $source->get_languages();
        $source->get_domains( 'zxx' );

        $this->assertCount( 1, $this->identity_calls );
        $this->assertCount( 2, $this->node_calls );
    }

    // -----------------------------------------------------------------------
    // §8 — Token refresh, and the second 401
    // -----------------------------------------------------------------------

    public function test_a_401_triggers_exactly_one_refresh_and_exactly_one_retry(): void {
        $source = $this->source(
            [
                $this->json_response( $this->fixture( 'error-unauthorized.json' ), 401 ),
                $this->json_response( $this->fixture( 'entry-success.json' ) ),
            ]
        );

        $result = $source->get_entry( 'kaŋo', 'zxx', 'r' );

        $this->assertIsArray( $result );
        $this->assertCount( 2, $this->node_calls );
        $this->assertCount( 2, $this->identity_calls );
        $this->assertSame(
            'Bearer test-access-token-2',
            $this->node_calls[1]['args']['headers']['Authorization']
        );
    }

    public function test_a_second_401_is_a_controlled_error_with_no_third_attempt(): void {
        $source = $this->source(
            [
                $this->json_response( $this->fixture( 'error-unauthorized.json' ), 401 ),
                $this->json_response( $this->fixture( 'error-unauthorized.json' ), 401 ),
                $this->json_response( $this->fixture( 'entry-success.json' ) ),
            ]
        );

        $result = $source->get_entry( 'kaŋo', 'zxx', 'r' );

        $this->assertWpError( $result, 'display_upstream_unauthorized' );
        $this->assertCount( 2, $this->node_calls );
    }

    // -----------------------------------------------------------------------
    // §8 — Timeout, unavailability, malformed JSON, oversized body
    // -----------------------------------------------------------------------

    public function test_a_timeout_is_a_controlled_error(): void {
        $source = $this->source( [ new \WP_Error( 'http_request_failed', 'Operation timed out after 8000 milliseconds' ) ] );

        $result = $source->get_entry( 'kaŋo', 'zxx', 'r' );

        $this->assertWpError( $result, 'display_upstream_unavailable' );
        $this->assertSame( 503, $result->get_error_data()['status'] );
    }

    public function test_an_unreachable_node_is_a_controlled_error_that_names_no_host(): void {
        $source = $this->source( [ new \WP_Error( 'http_request_failed', 'Could not resolve host: node.invalid' ) ] );

        $result = $source->get_languages();

        $this->assertWpError( $result, 'display_upstream_unavailable' );
        $this->assertStringNotContainsString( 'node.invalid', $result->get_error_message() );
    }

    public function test_malformed_json_is_a_controlled_error(): void {
        $source = $this->source( [ $this->json_response( '{"success":true,"data":' ) ] );

        $this->assertWpError( $source->get_languages(), 'display_upstream_malformed' );
    }

    public function test_an_oversized_response_is_refused(): void {
        add_filter( 'sparxstar_dictionary_node_max_bytes', static fn(): int => 512 );

        $huge   = (string) wp_json_encode(
            [
                'success' => true,
                'data'    => [ 'languages' => array_fill( 0, 200, [ 'code' => 'zxx', 'name' => 'zxx' ] ) ],
            ]
        );
        $source = $this->source( [ $this->json_response( $huge ) ] );

        $result = $source->get_languages();

        $this->assertWpError( $result, 'display_upstream_too_large' );
        $this->assertSame( 512 + 1, $this->node_calls[0]['args']['limit_response_size'] );
    }

    public function test_a_payload_that_is_json_but_not_the_expected_schema_is_refused(): void {
        $source = $this->source( [ $this->json_response( '{"success":true,"data":{"entry":{"header_word":"a"}}}' ) ] );

        $this->assertWpError( $source->get_entry( 'a', 'zxx', 'r' ), 'display_upstream_schema' );
    }

    // -----------------------------------------------------------------------
    // §4.3 — Reader metering
    // -----------------------------------------------------------------------

    public function test_entry_bearing_requests_carry_the_reader_reference(): void {
        $source = $this->source(
            [
                $this->json_response( $this->fixture( 'entry-success.json' ) ),
                $this->json_response( $this->fixture( 'search-success.json' ) ),
                $this->json_response( $this->fixture( 'word-of-day-success.json' ) ),
            ]
        );

        $source->get_entry( 'kaŋo', 'zxx', 'opaque-reader-ref' );
        $source->search( 'ka', 'zxx', 20, 'opaque-reader-ref' );
        $source->get_word_of_day( 'zxx', 'opaque-reader-ref' );

        foreach ( $this->node_calls as $call ) {
            $this->assertSame( 'opaque-reader-ref', $call['args']['headers'][ NodeHttpClient::READER_REF_HEADER ] );
        }
    }

    public function test_the_language_list_carries_no_reader_reference(): void {
        $source = $this->source( [ $this->json_response( $this->fixture( 'languages-success.json' ) ) ] );
        $source->get_languages();

        $this->assertArrayNotHasKey(
            NodeHttpClient::READER_REF_HEADER,
            $this->node_calls[0]['args']['headers']
        );
    }

    public function test_the_reader_reference_is_opaque_and_stable_for_one_reader(): void {
        $_COOKIE[ ReaderRef::COOKIE ] = str_repeat( 'a1', 16 );

        $reader = new ReaderRef( new DisplayConfig() );
        $value  = $reader->current();

        $this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $value );
        $this->assertNotSame( $_COOKIE[ ReaderRef::COOKIE ], $value );
        $this->assertSame( $value, ( new ReaderRef( new DisplayConfig() ) )->current() );

        unset( $_COOKIE[ ReaderRef::COOKIE ] );
    }

    public function test_two_readers_get_different_references(): void {
        $_COOKIE[ ReaderRef::COOKIE ] = str_repeat( 'a1', 16 );
        $first                        = ( new ReaderRef( new DisplayConfig() ) )->current();

        $_COOKIE[ ReaderRef::COOKIE ] = str_repeat( 'b2', 16 );
        $second                       = ( new ReaderRef( new DisplayConfig() ) )->current();

        $this->assertNotSame( $first, $second );

        unset( $_COOKIE[ ReaderRef::COOKIE ] );
    }

    // -----------------------------------------------------------------------
    // §4.2 — Configuration and the private key's location
    // -----------------------------------------------------------------------

    public function test_a_fully_configured_deployment_is_ready(): void {
        $this->assertTrue( ( new DisplayConfig() )->is_ready() );
    }

    public function test_a_key_inside_the_plugin_directory_is_refused(): void {
        $inside = self::$plugin_dir . 'private.key';
        copy( self::$key_path, $inside );

        remove_all_filters( 'sparxstar_dictionary_private_key_path' );
        add_filter( 'sparxstar_dictionary_private_key_path', static fn(): string => $inside );

        $config = new DisplayConfig();

        $this->assertFalse( $config->is_ready() );
        $this->assertNotEmpty( $config->key_problems() );

        unlink( $inside );
    }

    public function test_an_unconfigured_deployment_refuses_to_call_upstream(): void {
        $GLOBALS['__wp_filters'] = [];

        $config = new DisplayConfig();
        $http   = new NodeHttpClient(
            $config,
            new IdentityMachineTokenClient( $config, new ArrayTokenCache(), $this->identity_transport() ),
            $this->node_transport()
        );

        $result = ( new LiveJsonDisplaySource( $http ) )->get_languages();

        $this->assertWpError( $result, 'display_adapter_not_configured' );
        $this->assertCount( 0, $this->node_calls );
        $this->assertCount( 0, $this->identity_calls );
    }

    public function test_a_plain_http_node_url_is_refused(): void {
        remove_all_filters( 'sparxstar_dictionary_node_base_url' );
        add_filter( 'sparxstar_dictionary_node_base_url', static fn(): string => 'http://node.invalid' );

        $this->assertFalse( ( new DisplayConfig() )->is_ready() );
    }

    public function test_the_adapter_is_off_by_default(): void {
        $this->assertFalse( ( new DisplayConfig() )->is_adapter_enabled() );
    }

    // -----------------------------------------------------------------------
    // §6 — The three language modes
    // -----------------------------------------------------------------------

    /** @return array<int,array{code:string,name:string}> */
    private function reported(): array {
        return [
            [
                'code' => 'zxx',
                'name' => 'zxx',
            ],
            [
                'code' => 'mis',
                'name' => 'Example Reported Name',
            ],
        ];
    }

    public function test_single_mode_shows_one_language_and_hides_the_selector(): void {
        update_option(
            DisplayLanguageSettings::OPTION,
            [
                'mode'  => DisplayLanguageSettings::MODE_SINGLE,
                'codes' => [ 'zxx' ],
            ]
        );

        $resolved = ( new DisplayLanguageSettings() )->resolve( $this->reported() );

        $this->assertCount( 1, $resolved['languages'] );
        $this->assertSame( 'zxx', $resolved['default'] );
        $this->assertFalse( $resolved['selector'] );
    }

    public function test_selected_mode_shows_only_the_allowlist(): void {
        update_option(
            DisplayLanguageSettings::OPTION,
            [
                'mode'  => DisplayLanguageSettings::MODE_SELECTED,
                'codes' => [ 'mis' ],
            ]
        );

        $resolved = ( new DisplayLanguageSettings() )->resolve( $this->reported() );

        $this->assertSame( [ [ 'code' => 'mis', 'name' => 'Example Reported Name' ] ], $resolved['languages'] );
        $this->assertTrue( $resolved['selector'] );
    }

    public function test_all_available_mode_shows_every_reported_language(): void {
        update_option(
            DisplayLanguageSettings::OPTION,
            [
                'mode'  => DisplayLanguageSettings::MODE_ALL,
                'codes' => [],
            ]
        );

        $resolved = ( new DisplayLanguageSettings() )->resolve( $this->reported() );

        $this->assertCount( 2, $resolved['languages'] );
        $this->assertTrue( $resolved['selector'] );
    }

    public function test_a_configured_code_the_node_does_not_report_is_rejected_at_save_time(): void {
        $outcome = ( new DisplayLanguageSettings() )->validate(
            [
                'mode'  => DisplayLanguageSettings::MODE_SELECTED,
                'codes' => 'zxx, qqq',
            ],
            [ 'zxx', 'mis' ]
        );

        $this->assertSame( [ 'zxx' ], $outcome['settings']['codes'] );
        $this->assertNotEmpty( $outcome['errors'] );
    }

    public function test_a_language_that_disappears_degrades_to_a_notice_never_to_another_language(): void {
        update_option(
            DisplayLanguageSettings::OPTION,
            [
                'mode'  => DisplayLanguageSettings::MODE_SINGLE,
                'codes' => [ 'zxx' ],
            ]
        );

        $resolved = ( new DisplayLanguageSettings() )->resolve( [ [ 'code' => 'mis', 'name' => 'Example Reported Name' ] ] );

        $this->assertSame( [], $resolved['languages'] );
        $this->assertSame( '', $resolved['default'] );
        $this->assertSame( [ 'zxx' ], $resolved['missing'] );
        $this->assertNotSame( '', $resolved['notice'] );
    }

    public function test_an_unconfigured_language_setting_shows_a_notice_not_a_guess(): void {
        $resolved = ( new DisplayLanguageSettings() )->resolve( $this->reported() );

        $this->assertSame( [], $resolved['languages'] );
        $this->assertNotSame( '', $resolved['notice'] );
    }

    // -----------------------------------------------------------------------
    // Orthography
    // -----------------------------------------------------------------------

    public function test_nfc_normalisation_preserves_west_african_orthography(): void {
        foreach ( [ 'ŋ', 'ɓ', 'ɗ', 'ñ', 'ɲ', 'ʔ', 'kaŋo' ] as $value ) {
            $this->assertSame( $value, DisplayText::nfc( $value ) );
        }
    }

    public function test_a_slug_carrying_african_orthography_survives_validation(): void {
        $this->assertSame( 'kaŋo', DisplayText::slug( 'kaŋo' ) );
        $this->assertSame( 'ɗaa-ɓee', DisplayText::slug( ' ɗaa-ɓee ' ) );
        $this->assertSame( '', DisplayText::slug( 'a/b' ) );
        $this->assertSame( '', DisplayText::slug( 'a?b' ) );
    }

    // -----------------------------------------------------------------------
    // Secrecy boundary
    // -----------------------------------------------------------------------

    public function test_no_error_message_ever_carries_a_secret(): void {
        $sources = [
            $this->source( [ $this->json_response( $this->fixture( 'error-unauthorized.json' ), 401 ), $this->json_response( $this->fixture( 'error-unauthorized.json' ), 401 ) ] ),
        ];

        $messages = [];
        foreach ( $sources as $source ) {
            $result     = $source->get_entry( 'kaŋo', 'zxx', 'r' );
            $messages[] = $result->get_error_message();
        }

        foreach ( $messages as $message ) {
            $this->assertStringNotContainsString( 'node.invalid', $message );
            $this->assertStringNotContainsString( 'identity.invalid', $message );
            $this->assertStringNotContainsString( 'test-access-token', $message );
            $this->assertStringNotContainsString( 'test-client-id', $message );
            $this->assertStringNotContainsString( 'test-kid', $message );
            $this->assertStringNotContainsString( 'BEGIN', $message );
        }
    }

    public function test_the_interface_is_the_only_contact_surface(): void {
        $source = $this->source( [] );
        $this->assertInstanceOf( DictionaryDisplaySourceInterface::class, $source );
    }

    private static function base64url_decode( string $value ): string {
        return (string) base64_decode( strtr( $value, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $value ) % 4 ) % 4 ), true );
    }
}
