<?php

declare(strict_types=1);

/**
 * Regression tests for the Dictionary Asset Protection implementation.
 *
 * Covers the negative cases spec §9 step 2 asks for by name — "over-cap requests,
 * unbounded list attempts -> 4xx" — plus the cutover gate (§1.4), the system
 * credential contract (§1.2), count suppression (§2), and credential redaction (§3).
 *
 * @group asset-protection
 *
 * @package Starisian\Sparxstar\IAtlas\tests
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

require_once __DIR__ . '/../../src/api/Sparxstar3IAtlasDictionaryProtection.php';
require_once __DIR__ . '/../../src/api/auth/AuthContext.php';
require_once __DIR__ . '/../../src/api/auth/DictionaryAuthInterface.php';
require_once __DIR__ . '/../../src/api/auth/EphemeralTokenAuth.php';
require_once __DIR__ . '/../../src/api/auth/ApiKeyAuth.php';
require_once __DIR__ . '/../../src/api/auth/UniqueEntryBudget.php';
require_once __DIR__ . '/../../src/api/auth/SystemCredentialAuth.php';
require_once __DIR__ . '/../../src/api/auth/DictionaryAuthResolver.php';
require_once __DIR__ . '/../../src/api/Sparxstar3IAtlasRateLimitTrait.php';
require_once __DIR__ . '/../../src/api/Sparxstar3IAtlasDictionaryRestApi.php';
require_once __DIR__ . '/../../src/api/Sparxstar3IAtlasDictionaryTripwire.php';

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryProtection as Protection;
use Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryRestApi;
use Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryTripwire;
use Starisian\Sparxstar\IAtlas\api\auth\DictionaryAuthResolver;
use Starisian\Sparxstar\IAtlas\api\auth\SystemCredentialAuth;
use Starisian\Sparxstar\IAtlas\api\auth\UniqueEntryBudget;

/**
 * @group asset-protection
 */
final class AssetProtectionTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['__wp_options_store'] = [];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a request stub carrying the given headers.
     *
     * @param array<string,string> $headers Header name => value.
     */
    private function request( array $headers = [] ): \WP_REST_Request {
        $request = new \WP_REST_Request();
        foreach ( $headers as $name => $value ) {
            $request->set_header( $name, $value );
        }
        return $request;
    }

    /**
     * Register a system credential and return its plaintext secret.
     */
    private function register_credential( string $id, bool $active = true, ?int $budget = null ): string {
        $secret = bin2hex( random_bytes( 16 ) );
        $record = [
            'credential_id' => $id,
            'label'         => $id,
            'secret_hash'   => hash( 'sha256', $secret ),
            'active'        => $active,
        ];
        if ( null !== $budget ) {
            $record['entry_budget'] = $budget;
        }
        SystemCredentialAuth::save( [ $record ] );
        return $secret;
    }

    /**
     * Read the HTTP status off a WP_Error stub.
     */
    private function status_of( \WP_Error $error ): int {
        $data = $error->get_error_data();
        return (int) ( $data['status'] ?? 0 );
    }

    /**
     * Invoke the REST controller's private capped_param().
     *
     * @param array<string,int> $caps Keys: default, max, legacy_default, legacy_max.
     */
    private function capped_param( \WP_REST_Request $request, string $param, array $caps ): mixed {
        $api    = new Sparxstar3IAtlasDictionaryRestApi();
        $method = ( new \ReflectionClass( $api ) )->getMethod( 'capped_param' );
        $method->setAccessible( true );
        return $method->invoke( $api, $request, $param, $caps );
    }

    /**
     * Invoke the REST controller's private meta_count().
     *
     * @return array<string,int|string>
     */
    private function meta_count( int $exact ): array {
        $api    = new Sparxstar3IAtlasDictionaryRestApi();
        $method = ( new \ReflectionClass( $api ) )->getMethod( 'meta_count' );
        $method->setAccessible( true );
        return $method->invoke( $api, $exact );
    }

    /**
     * The wordlist cap profile: legacy 2000/1000, target 100.
     *
     * @return array<string,int>
     */
    private function wordlist_caps(): array {
        return array(
            'default'        => Protection::LIST_PAGE_MAX,
            'max'            => Protection::LIST_PAGE_MAX,
            'legacy_default' => 1000,
            'legacy_max'     => 2000,
        );
    }

    /**
     * Arm the cutover so target-state behaviour applies.
     */
    private function arm_cutover(): void {
        update_option( Protection::CUTOVER_OPTION, true );
    }

    // -------------------------------------------------------------------------
    // Spec §9 step 2 — over-cap requests and unbounded list attempts must 4xx,
    // but only from the cutover onward (§1.4). Before it, the legacy response
    // contract is preserved byte for byte so no deployed client breaks.
    // -------------------------------------------------------------------------

    public function test_over_cap_per_page_is_refused_with_400_at_target_state(): void {
        $this->arm_cutover();

        $request = $this->request();
        $request->set_param( 'per_page', 500 );

        $result = $this->capped_param( $request, 'per_page', $this->wordlist_caps() );

        $this->assertInstanceOf( \WP_Error::class, $result, 'An over-cap per_page must be refused.' );
        $this->assertSame( 'over_cap', $result->get_error_code() );
        $this->assertSame( 400, $this->status_of( $result ) );
    }

    public function test_unbounded_list_attempt_is_refused_with_400_at_target_state(): void {
        $this->arm_cutover();

        // The pre-spec /wordlist accepted per_page up to 2000. That is the unbounded
        // list attempt §1.5 prohibits; at target state it must 4xx, not be clamped.
        $request = $this->request();
        $request->set_param( 'per_page', 2000 );

        $result = $this->capped_param( $request, 'per_page', $this->wordlist_caps() );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 400, $this->status_of( $result ) );
    }

    public function test_over_cap_is_refused_not_silently_clamped_at_target_state(): void {
        // ADR brief D-2: a silent clamp answers "what is the cap?" for free.
        $this->arm_cutover();

        $request = $this->request();
        $request->set_param( 'per_page', Protection::LIST_PAGE_MAX + 1 );

        $result = $this->capped_param( $request, 'per_page', $this->wordlist_caps() );

        $this->assertNotSame( Protection::LIST_PAGE_MAX, $result );
        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_negative_and_zero_are_refused_with_400_at_target_state(): void {
        $this->arm_cutover();

        foreach ( array( 0, -1 ) as $value ) {
            $request = $this->request();
            $request->set_param( 'per_page', $value );

            $result = $this->capped_param( $request, 'per_page', $this->wordlist_caps() );

            $this->assertInstanceOf( \WP_Error::class, $result, "Value {$value} must be refused." );
            $this->assertSame( 400, $this->status_of( $result ) );
        }
    }

    public function test_non_numeric_is_refused_with_400_at_target_state(): void {
        $this->arm_cutover();

        $request = $this->request();
        $request->set_param( 'per_page', 'all' );

        $result = $this->capped_param( $request, 'per_page', $this->wordlist_caps() );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 400, $this->status_of( $result ) );
    }

    // --- Before cutover: the legacy contract, unchanged. This is what keeps the
    // --- promise that no deployed client breaks on merge (§1.4).

    public function test_legacy_cap_is_honoured_before_cutover(): void {
        // A consumer asking for 1000 got 1000 before this PR and must still get it.
        $request = $this->request();
        $request->set_param( 'per_page', 1000 );

        $this->assertSame( 1000, $this->capped_param( $request, 'per_page', $this->wordlist_caps() ) );
    }

    public function test_legacy_default_is_honoured_before_cutover(): void {
        $this->assertSame( 1000, $this->capped_param( $this->request(), 'per_page', $this->wordlist_caps() ) );
    }

    public function test_over_legacy_cap_clamps_rather_than_refusing_before_cutover(): void {
        $request = $this->request();
        $request->set_param( 'per_page', 5000 );

        // Legacy behaviour clamped to 2000; it must not start returning 4xx.
        $this->assertSame( 2000, $this->capped_param( $request, 'per_page', $this->wordlist_caps() ) );
    }

    public function test_legacy_absint_coercion_is_preserved_before_cutover(): void {
        // absint( -1 ) is 1. That was the pre-spec behaviour and it is not a leak
        // (fewer rows, not more), so it is preserved until the cutover refuses it.
        $request = $this->request();
        $request->set_param( 'per_page', -1 );

        $this->assertSame( 1, $this->capped_param( $request, 'per_page', $this->wordlist_caps() ) );
    }

    public function test_target_default_applies_after_cutover(): void {
        $this->arm_cutover();

        $this->assertSame(
            Protection::LIST_PAGE_MAX,
            $this->capped_param( $this->request(), 'per_page', $this->wordlist_caps() )
        );
    }

    public function test_within_cap_value_is_accepted_in_either_state(): void {
        $request = $this->request();
        $request->set_param( 'per_page', 10 );
        $this->assertSame( 10, $this->capped_param( $request, 'per_page', $this->wordlist_caps() ) );

        $this->arm_cutover();

        $request2 = $this->request();
        $request2->set_param( 'per_page', 10 );
        $this->assertSame( 10, $this->capped_param( $request2, 'per_page', $this->wordlist_caps() ) );
    }

    public function test_search_cap_matches_the_spec_stated_twenty(): void {
        // Spec §2 states this number literally: "search results <= 20".
        $this->assertSame( 20, Protection::SEARCH_RESULTS_MAX );
    }

    // -------------------------------------------------------------------------
    // Spec §2 — count suppression.
    // -------------------------------------------------------------------------

    public function test_large_counts_are_not_reported_exactly(): void {
        $this->assertNotSame( 4175, Protection::approximate_count( 4175 ) );
        $this->assertSame( '1000+', Protection::approximate_count( 4175 ) );
    }

    public function test_meta_reports_exact_total_before_cutover(): void {
        // The legacy response contract: consumers reading meta.total keep working.
        $this->assertSame( array( 'total' => 4175 ), $this->meta_count( 4175 ) );
    }

    public function test_meta_suppresses_the_count_after_cutover(): void {
        $this->arm_cutover();

        $meta = $this->meta_count( 4175 );

        $this->assertArrayNotHasKey( 'total', $meta );
        $this->assertSame( '1000+', $meta['total_approx'] );
    }

    public function test_mid_range_counts_are_rounded_down_to_a_bucket(): void {
        $this->assertSame( 400, Protection::approximate_count( 487 ) );
        $this->assertSame( 100, Protection::approximate_count( 100 ) );
    }

    public function test_small_counts_stay_exact(): void {
        // A caller who asked for three results learning three exist reveals nothing.
        $this->assertSame( 3, Protection::approximate_count( 3 ) );
        $this->assertSame( 0, Protection::approximate_count( 0 ) );
        $this->assertSame( 0, Protection::approximate_count( -5 ) );
    }

    // -------------------------------------------------------------------------
    // Spec §3 — credential values never appear in logs.
    // -------------------------------------------------------------------------

    public function test_fingerprint_never_contains_the_credential(): void {
        $secret      = 'super-secret-system-credential-value';
        $fingerprint = Protection::fingerprint_credential( $secret );

        $this->assertStringNotContainsString( $secret, $fingerprint );
        $this->assertStringStartsWith( 'sha256:', $fingerprint );
        $this->assertSame( 19, strlen( $fingerprint ), 'Fingerprint is a short prefix, not a full hash.' );
    }

    public function test_fingerprint_is_stable_and_distinguishing(): void {
        $this->assertSame(
            Protection::fingerprint_credential( 'a' ),
            Protection::fingerprint_credential( 'a' )
        );
        $this->assertNotSame(
            Protection::fingerprint_credential( 'a' ),
            Protection::fingerprint_credential( 'b' )
        );
        $this->assertSame( 'none', Protection::fingerprint_credential( '' ) );
    }

    // -------------------------------------------------------------------------
    // Spec §1.4 — the cutover gate defaults closed.
    // -------------------------------------------------------------------------

    public function test_cutover_defaults_to_incomplete(): void {
        // Enforcement must never arm by accident: the migration exception is the default.
        $this->assertFalse( Protection::is_cutover_complete() );
    }

    public function test_cutover_option_arms_enforcement(): void {
        update_option( Protection::CUTOVER_OPTION, true );
        $this->assertTrue( Protection::is_cutover_complete() );
    }

    // -------------------------------------------------------------------------
    // Spec §1.2 — per-system credentials.
    // -------------------------------------------------------------------------

    public function test_bearer_parsing( ): void {
        $this->assertSame( 'abc123', SystemCredentialAuth::parse_bearer( 'Bearer abc123' ) );
        $this->assertSame( 'abc123', SystemCredentialAuth::parse_bearer( 'bearer   abc123' ) );
        $this->assertSame( '', SystemCredentialAuth::parse_bearer( 'abc123' ) );
        $this->assertSame( '', SystemCredentialAuth::parse_bearer( 'Basic abc123' ) );
        $this->assertSame( '', SystemCredentialAuth::parse_bearer( '' ) );
    }

    public function test_missing_credential_returns_401(): void {
        $result = ( new SystemCredentialAuth() )->resolve( $this->request() );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'missing_system_credential', $result->get_error_code() );
        $this->assertSame( 401, $this->status_of( $result ) );
    }

    public function test_unknown_credential_returns_403(): void {
        $this->register_credential( 'esu-sky' );

        $result = ( new SystemCredentialAuth() )->resolve(
            $this->request( [ 'Authorization' => 'Bearer not-a-real-secret' ] )
        );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 403, $this->status_of( $result ) );
    }

    public function test_revoked_credential_returns_403(): void {
        $secret = $this->register_credential( 'esu-sky', false );

        $result = ( new SystemCredentialAuth() )->resolve(
            $this->request( [ 'Authorization' => 'Bearer ' . $secret ] )
        );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'revoked_system_credential', $result->get_error_code() );
        $this->assertSame( 403, $this->status_of( $result ) );
    }

    public function test_valid_credential_resolves_with_attributable_id(): void {
        $secret = $this->register_credential( 'esu-sky' );

        $result = ( new SystemCredentialAuth() )->resolve(
            $this->request( [ 'Authorization' => 'Bearer ' . $secret ] )
        );

        $this->assertNotInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'system', $result->credential_type );
        // Attribution is the whole point of per-system credentials (§1.2).
        $this->assertSame( 'esu-sky', $result->key_id );
    }

    public function test_credentials_are_stored_hashed_never_in_plaintext(): void {
        $secret = $this->register_credential( 'esu-sky' );
        $stored = wp_json_encode( get_option( SystemCredentialAuth::CREDENTIALS_OPTION ) );

        $this->assertStringNotContainsString( $secret, (string) $stored );
    }

    public function test_each_system_gets_its_own_credential(): void {
        // A shared "THE token" is on the spec's Rejected list: two systems must not
        // resolve to the same identity.
        $esu_secret = bin2hex( random_bytes( 16 ) );
        $rlc_secret = bin2hex( random_bytes( 16 ) );

        SystemCredentialAuth::save(
            [
                [
                    'credential_id' => 'esu-sky',
                    'secret_hash'   => hash( 'sha256', $esu_secret ),
                    'active'        => true,
                ],
                [
                    'credential_id' => 'rlc-game-node',
                    'secret_hash'   => hash( 'sha256', $rlc_secret ),
                    'active'        => true,
                ],
            ]
        );

        $auth = new SystemCredentialAuth();

        $esu = $auth->resolve( $this->request( [ 'Authorization' => 'Bearer ' . $esu_secret ] ) );
        $rlc = $auth->resolve( $this->request( [ 'Authorization' => 'Bearer ' . $rlc_secret ] ) );

        $this->assertSame( 'esu-sky', $esu->key_id );
        $this->assertSame( 'rlc-game-node', $rlc->key_id );
    }

    // -------------------------------------------------------------------------
    // Spec §1.1 / §1.4 — retired credentials stop working at cutover.
    // -------------------------------------------------------------------------

    public function test_api_key_still_accepted_before_cutover(): void {
        // The migration exception: enforcing M2M early would lock out every player.
        $resolver = new DictionaryAuthResolver();
        $result   = $resolver->resolve( $this->request( [ 'X-Api-Key' => 'whatever' ] ) );

        // Reaches ApiKeyAuth (which rejects this unknown key with 403) rather than
        // being refused outright as a retired credential.
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertNotSame( 'system_credential_required', $result->get_error_code() );
    }

    public function test_api_key_is_refused_after_cutover(): void {
        update_option( Protection::CUTOVER_OPTION, true );

        $resolver = new DictionaryAuthResolver();
        $result   = $resolver->resolve( $this->request( [ 'X-Api-Key' => 'whatever' ] ) );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'system_credential_required', $result->get_error_code() );
        $this->assertSame( 401, $this->status_of( $result ) );
    }

    public function test_page_token_is_refused_after_cutover(): void {
        update_option( Protection::CUTOVER_OPTION, true );

        $resolver = new DictionaryAuthResolver();
        $result   = $resolver->resolve( $this->request( [ 'X-Page-Token' => 'anything.deadbeef' ] ) );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'system_credential_required', $result->get_error_code() );
    }

    public function test_system_credential_still_works_after_cutover(): void {
        $secret = $this->register_credential( 'esu-sky' );
        update_option( Protection::CUTOVER_OPTION, true );

        $resolver = new DictionaryAuthResolver();
        $result   = $resolver->resolve( $this->request( [ 'Authorization' => 'Bearer ' . $secret ] ) );

        $this->assertNotInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'esu-sky', $result->key_id );
    }

    public function test_no_credentials_returns_401_in_either_state(): void {
        $resolver = new DictionaryAuthResolver();

        $before = $resolver->resolve( $this->request() );
        $this->assertInstanceOf( \WP_Error::class, $before );
        $this->assertSame( 401, $this->status_of( $before ) );

        update_option( Protection::CUTOVER_OPTION, true );

        $after = $resolver->resolve( $this->request() );
        $this->assertInstanceOf( \WP_Error::class, $after );
        $this->assertSame( 401, $this->status_of( $after ) );
    }

    // -------------------------------------------------------------------------
    // Spec §1.2 — budget shape.
    // -------------------------------------------------------------------------

    public function test_budget_window_has_a_sane_floor(): void {
        $this->assertGreaterThanOrEqual( 60, UniqueEntryBudget::window_seconds() );
    }

    public function test_ceiling_defaults_well_below_corpus_size(): void {
        // ~4,175 live entries. A ceiling at or near that lets one compromised
        // consumer walk the corpus inside a window, which defeats the control.
        $this->assertLessThan( 4175, UniqueEntryBudget::ceiling_for( 'esu-sky' ) );
    }

    public function test_ceiling_is_per_credential(): void {
        $this->assertSame( 250, UniqueEntryBudget::ceiling_for( 'esu-sky', [ 'entry_budget' => 250 ] ) );
    }

    public function test_budget_accounting_is_inert_without_its_table(): void {
        // No schema option set, so the store reports "unavailable" rather than
        // silently reporting a zero count that would read as "budget not used".
        $this->assertFalse( UniqueEntryBudget::is_installed() );
        $this->assertSame( -1, UniqueEntryBudget::record( 'esu-sky', [ 1, 2, 3 ] ) );
    }

    // -------------------------------------------------------------------------
    // Route boundary — the matcher must mirror the nginx `(?:/|$)` regex.
    // -------------------------------------------------------------------------

    public function test_route_matcher_respects_the_namespace_boundary(): void {
        $method = ( new \ReflectionClass( Sparxstar3IAtlasDictionaryTripwire::class ) )
            ->getMethod( 'is_dictionary_route' );
        $method->setAccessible( true );

        $this->assertTrue( $method->invoke( null, '/sparxstar/v1/dictionary' ) );
        $this->assertTrue( $method->invoke( null, '/sparxstar/v1/dictionary/lookup' ) );

        // A bare prefix match would accept these; the boundary check must not.
        $this->assertFalse( $method->invoke( null, '/sparxstar/v1/dictionary-evil' ) );
        $this->assertFalse( $method->invoke( null, '/sparxstar/v1/dictionaryx' ) );
        $this->assertFalse( $method->invoke( null, '/wp/v2/posts' ) );
    }

    // -------------------------------------------------------------------------
    // Cutover evidence (ADR brief D-3) must be durable, not cache-backed.
    // -------------------------------------------------------------------------

    public function test_observation_history_is_option_backed_and_starts_empty(): void {
        $this->assertSame( array(), Sparxstar3IAtlasDictionaryTripwire::observation_history() );
        $this->assertSame( 0, Sparxstar3IAtlasDictionaryTripwire::observations_for( '2026-08-26' ) );
    }

    public function test_observations_survive_a_transient_flush(): void {
        // Simulates an object-cache flush: transients are gone, the evidence is not.
        // A transient-backed counter would report zero here and manufacture a
        // zero-observation day, satisfying the cutover criterion falsely.
        update_option(
            'sparxstar_dict_tripwire_observations',
            array(
                '2026-08-25' => 4,
                '2026-08-26' => 7,
            )
        );

        $this->assertSame( 7, Sparxstar3IAtlasDictionaryTripwire::observations_for( '2026-08-26' ) );
        $this->assertSame( 4, Sparxstar3IAtlasDictionaryTripwire::observations_for( '2026-08-25' ) );
        $this->assertSame(
            array(
                '2026-08-25' => 4,
                '2026-08-26' => 7,
            ),
            Sparxstar3IAtlasDictionaryTripwire::observation_history()
        );
    }
}
