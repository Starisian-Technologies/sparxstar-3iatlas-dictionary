<?php

declare(strict_types=1);

/**
 * CORS handler tests for the 3iAtlas Dictionary REST API.
 *
 * Tests 11–13: OPTIONS preflight, non-allowlisted origin rejection, and
 * Expose-Headers verification.
 *
 * WP stubs are provided by bootstrap.php. Tests run without a live WordPress
 * install (wp-env). Tests are skipped when a full WordPress environment is detected.
 *
 * @group dictionary-auth
 *
 * @package Starisian\Sparxstar\IAtlas\tests
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

require_once __DIR__ . '/../../src/api/Sparxstar3IAtlasDictionaryRestApi.php';
require_once __DIR__ . '/../../src/api/Sparxstar3IAtlasDictionaryCors.php';

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryCors;

/**
 * @group dictionary-auth
 */
final class CorsTest extends TestCase {

    private bool $wp_available;

    protected function setUp(): void {
        parent::setUp();
        $this->wp_available = function_exists( 'register_post_type' );
        // Reset the global options store between tests.
        $GLOBALS['__wp_options_store'] = [];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function make_cors(): Sparxstar3IAtlasDictionaryCors {
        return new Sparxstar3IAtlasDictionaryCors();
    }

    /**
     * @param array<string,string> $headers
     */
    private function make_request(
        string $method = 'GET',
        string $route = '/sparxstar/v1/dictionary/lookup',
        array $headers = []
    ): \WP_REST_Request {
        $request = new \WP_REST_Request();
        $request->set_method( $method );
        $request->set_route( $route );
        foreach ( $headers as $key => $value ) {
            $request->set_header( $key, $value );
        }
        return $request;
    }

    private function set_allowlist( array $origins ): void {
        $GLOBALS['__wp_options_store']['aiwa_dict_cors_origins'] = $origins;
    }

    // -------------------------------------------------------------------------
    // Test 11: OPTIONS preflight from allowlisted origin → 200 with correct headers.
    // -------------------------------------------------------------------------

    /**
     * @test
     * Test 11 — OPTIONS preflight from allowlisted origin returns 200 and correct CORS headers.
     */
    public function test_options_preflight_allowlisted_origin_returns_200(): void {
        if ( $this->wp_available ) {
            $this->markTestSkipped( 'Skipping: test uses WP stubs; skipped when a full WordPress environment is available.' );
        }

        $this->set_allowlist( [ 'https://example.com' ] );
        $cors    = $this->make_cors();
        $request = $this->make_request(
            'OPTIONS',
            '/sparxstar/v1/dictionary/game-set',
            [ 'Origin' => 'https://example.com' ]
        );

        $result = $cors->handle_options_request( true, [], $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
        $this->assertSame( 200, $result->get_status() );

        $headers = $result->get_headers();
        $this->assertArrayHasKey( 'Access-Control-Allow-Origin', $headers );
        $this->assertSame( 'https://example.com', $headers['Access-Control-Allow-Origin'] );
        $this->assertArrayHasKey( 'Access-Control-Allow-Methods', $headers );
        $this->assertStringContainsString( 'OPTIONS', $headers['Access-Control-Allow-Methods'] );
        $this->assertArrayHasKey( 'Access-Control-Allow-Headers', $headers );
        $this->assertStringContainsString( 'X-Page-Token', $headers['Access-Control-Allow-Headers'] );
        $this->assertStringContainsString( 'X-Api-Key', $headers['Access-Control-Allow-Headers'] );
        $this->assertArrayHasKey( 'Access-Control-Expose-Headers', $headers );
        $this->assertStringContainsString( 'ETag', $headers['Access-Control-Expose-Headers'] );
        $this->assertStringContainsString( 'X-RateLimit-Remaining', $headers['Access-Control-Expose-Headers'] );
        $this->assertArrayHasKey( 'Access-Control-Max-Age', $headers );
        $this->assertSame( '86400', $headers['Access-Control-Max-Age'] );

        // Critical: Access-Control-Allow-Credentials must NEVER be emitted.
        $this->assertArrayNotHasKey( 'Access-Control-Allow-Credentials', $headers );
    }

    // -------------------------------------------------------------------------
    // Test 12: Non-allowlisted origin → no Access-Control-Allow-Origin header.
    // -------------------------------------------------------------------------

    /**
     * @test
     * Test 12 — Non-allowlisted origin does not get CORS headers.
     */
    public function test_non_allowlisted_origin_gets_no_cors_headers(): void {
        if ( $this->wp_available ) {
            $this->markTestSkipped( 'Skipping: test uses WP stubs; skipped when a full WordPress environment is available.' );
        }

        $this->set_allowlist( [ 'https://example.com' ] );
        $cors    = $this->make_cors();
        $request = $this->make_request(
            'OPTIONS',
            '/sparxstar/v1/dictionary/lookup',
            [ 'Origin' => 'https://evil.com' ]
        );

        $result = $cors->handle_options_request( true, [], $request );

        // Non-allowlisted origin: handler must return the original $response unchanged.
        $this->assertTrue( $result, 'Non-allowlisted origin must not produce a CORS response.' );
    }

    /**
     * @test
     * Test 12b — add_cors_headers on a non-dictionary route leaves $served unchanged.
     */
    public function test_cors_headers_not_added_to_non_dictionary_routes(): void {
        if ( $this->wp_available ) {
            $this->markTestSkipped( 'Skipping: test uses WP stubs; skipped when a full WordPress environment is available.' );
        }

        $this->set_allowlist( [ 'https://example.com' ] );
        $cors     = $this->make_cors();
        $request  = $this->make_request( 'GET', '/wp/v2/posts', [ 'Origin' => 'https://example.com' ] );
        $response = new \WP_REST_Response( [ 'data' => 'test' ], 200 );
        $server   = new \WP_REST_Server();

        $served = $cors->add_cors_headers( false, $response, $request, $server );
        $this->assertFalse( $served, 'Non-dictionary route must pass through $served unchanged.' );
        $this->assertArrayNotHasKey( 'Access-Control-Allow-Origin', $response->get_headers() );
    }

    // -------------------------------------------------------------------------
    // Test 13: GET response exposes ETag and X-RateLimit-Remaining via Expose-Headers.
    // -------------------------------------------------------------------------

    /**
     * @test
     * Test 13 — GET from allowlisted origin includes Expose-Headers with ETag and X-RateLimit-Remaining.
     */
    public function test_get_response_has_expose_headers(): void {
        if ( $this->wp_available ) {
            $this->markTestSkipped( 'Skipping: test uses WP stubs; skipped when a full WordPress environment is available.' );
        }

        $this->set_allowlist( [ 'https://sparxstar.app' ] );
        $cors     = $this->make_cors();
        $request  = $this->make_request(
            'GET',
            '/sparxstar/v1/dictionary/wordlist',
            [ 'Origin' => 'https://sparxstar.app' ]
        );
        $response = new \WP_REST_Response( [ 'success' => true ], 200 );
        $server   = new \WP_REST_Server();

        $cors->add_cors_headers( false, $response, $request, $server );
        $headers = $response->get_headers();

        $this->assertArrayHasKey( 'Access-Control-Expose-Headers', $headers );
        $expose = $headers['Access-Control-Expose-Headers'];
        $this->assertStringContainsString( 'ETag', $expose );
        $this->assertStringContainsString( 'X-RateLimit-Remaining', $expose );
        $this->assertStringContainsString( 'Retry-After', $expose );

        // Vary: Origin must be present.
        $this->assertArrayHasKey( 'Vary', $headers );
        $this->assertStringContainsString( 'Origin', $headers['Vary'] );

        // Access-Control-Allow-Credentials must never be emitted.
        $this->assertArrayNotHasKey( 'Access-Control-Allow-Credentials', $headers );
    }

    /**
     * @test
     * Test 13b — Empty origin does not trigger CORS headers even on a dictionary route.
     */
    public function test_empty_origin_does_not_trigger_cors(): void {
        if ( $this->wp_available ) {
            $this->markTestSkipped( 'Skipping: test uses WP stubs; skipped when a full WordPress environment is available.' );
        }

        $this->set_allowlist( [ 'https://example.com' ] );
        $cors     = $this->make_cors();
        $request  = $this->make_request( 'GET', '/sparxstar/v1/dictionary/languages' );
        $response = new \WP_REST_Response( null, 200 );
        $server   = new \WP_REST_Server();

        $cors->add_cors_headers( false, $response, $request, $server );
        $this->assertArrayNotHasKey( 'Access-Control-Allow-Origin', $response->get_headers() );
    }
}
