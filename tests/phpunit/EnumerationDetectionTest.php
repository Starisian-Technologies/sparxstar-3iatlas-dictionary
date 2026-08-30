<?php

declare(strict_types=1);

/**
 * Regression tests for the §3 enumeration-signature detection.
 *
 * Covers the signatures the detector can measure without a database, the fail-safe
 * behaviour when accounting cannot answer, and the redaction guarantee §3 makes about
 * what an alert may carry.
 *
 * @group asset-protection
 * @group enumeration
 *
 * @package Starisian\Sparxstar\IAtlas\tests
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

require_once __DIR__ . '/../../src/api/Sparxstar3IAtlasDictionaryProtection.php';
require_once __DIR__ . '/../../src/api/auth/UniqueEntryBudget.php';
require_once __DIR__ . '/../../src/api/Sparxstar3IAtlasDictionaryEnumerationDetector.php';

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryEnumerationDetector as Detector;
use Starisian\Sparxstar\IAtlas\api\auth\UniqueEntryBudget;

/**
 * @group enumeration
 */
final class EnumerationDetectionTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['__wp_options_store'] = [];
        $GLOBALS['__wp_actions_fired'] = [];
    }

    /**
     * Security events fired during the current test.
     *
     * @return array<int,array{severity:string,event:string,context:array<string,mixed>}>
     */
    private function security_events(): array {
        $events = [];

        foreach ( $GLOBALS['__wp_actions_fired'] as $fired ) {
            if ( 'sparxstar_dict_security_event' !== $fired['tag'] ) {
                continue;
            }
            $events[] = [
                'severity' => (string) ( $fired['args'][0] ?? '' ),
                'event'    => (string) ( $fired['args'][1] ?? '' ),
                'context'  => (array) ( $fired['args'][2] ?? [] ),
            ];
        }

        return $events;
    }

    // -------------------------------------------------------------------------
    // Sustained consumption near the ceiling.
    // -------------------------------------------------------------------------

    public function test_near_cap_signature_fires_at_the_threshold(): void {
        // 80% of 1000 is 800.
        $reported = Detector::inspect( 'esu-sky', 800, 1000 );

        $this->assertContains( 'sustained_near_cap', $reported );
    }

    public function test_near_cap_signature_is_silent_below_the_threshold(): void {
        $this->assertSame( [], Detector::inspect( 'esu-sky', 799, 1000 ) );
        $this->assertSame( [], $this->security_events() );
    }

    public function test_near_cap_fires_well_before_the_ceiling_stops_the_caller(): void {
        // The point of the signature: it must fire while the walk is still running,
        // not when the ceiling has already refused the caller.
        $reported = Detector::inspect( 'esu-sky', 900, 1000 );

        $this->assertContains( 'sustained_near_cap', $reported );
        $this->assertLessThan( 1000, 900, 'Signature fires below the ceiling, not at it.' );
    }

    public function test_unlimited_or_unset_ceiling_reports_nothing(): void {
        // A non-positive ceiling means no ceiling is in force; a percentage of it is
        // meaningless and must not be invented.
        $this->assertSame( [], Detector::inspect( 'esu-sky', 100000, 0 ) );
        $this->assertSame( [], Detector::inspect( 'esu-sky', 100000, -1 ) );
    }

    // -------------------------------------------------------------------------
    // Fail-safe: silence when the accounting cannot answer.
    // -------------------------------------------------------------------------

    public function test_signatures_needing_accounting_stay_silent_when_it_is_unavailable(): void {
        // With no budget table, count_recent() reports -1 (unknown). Reporting an
        // unmeasured request as either safe or hostile would be invented, so the
        // velocity and per-IP signatures must simply not speak.
        $this->assertFalse( UniqueEntryBudget::is_installed() );
        $this->assertSame( -1, UniqueEntryBudget::count_recent( 'esu-sky', 600 ) );

        $reported = Detector::inspect( 'esu-sky', 10, 1000, '198.51.100.7' );

        $this->assertNotContains( 'breadth_first_coverage', $reported );
        $this->assertNotContains( 'distinct_entries_per_ip', $reported );
    }

    public function test_invalid_input_reports_nothing(): void {
        $this->assertSame( [], Detector::inspect( '', 900, 1000 ) );
        $this->assertSame( [], Detector::inspect( 'esu-sky', -1, 1000 ) );
    }

    // -------------------------------------------------------------------------
    // Spec §3 — what an alert is allowed to carry.
    // -------------------------------------------------------------------------

    public function test_alert_names_its_signature_and_points_at_the_runbook(): void {
        Detector::inspect( 'esu-sky', 900, 1000 );

        $events = $this->security_events();

        $this->assertCount( 1, $events );
        $this->assertSame( 'anomaly', $events[0]['severity'] );
        $this->assertSame( 'enumeration_signature_detected', $events[0]['event'] );
        $this->assertSame( 'sustained_near_cap', $events[0]['context']['signature'] );
        $this->assertSame(
            'docs/dictionary-enumeration-response-runbook.md',
            $events[0]['context']['runbook'],
            'An alert with no route to the runbook makes the on-call engineer hunt for it.'
        );
    }

    public function test_alert_carries_the_credential_id_for_attribution(): void {
        Detector::inspect( 'esu-sky', 900, 1000 );

        $events = $this->security_events();

        // §1.2: per-system credentials exist so a leak names the system that lost it.
        $this->assertSame( 'esu-sky', $events[0]['context']['credential_id'] );
    }

    public function test_no_alert_context_carries_a_raw_credential_value(): void {
        Detector::inspect( 'esu-sky', 900, 1000 );

        $encoded = wp_json_encode( $this->security_events() );

        // §3: plugin logs record credential IDs only.
        $this->assertStringNotContainsString( 'Bearer', (string) $encoded );
        $this->assertStringNotContainsString( 'secret', (string) $encoded );
    }

    // -------------------------------------------------------------------------
    // The IP accounting key.
    // -------------------------------------------------------------------------

    public function test_ip_key_is_namespaced_and_non_reversible(): void {
        $ip  = '198.51.100.7';
        $key = UniqueEntryBudget::ip_key( $ip );

        $this->assertStringStartsWith( 'ip:', $key );
        $this->assertStringNotContainsString( $ip, $key, 'The address itself must not be stored.' );
        $this->assertSame( $key, UniqueEntryBudget::ip_key( $ip ), 'Stable for the same address.' );
        $this->assertNotSame( $key, UniqueEntryBudget::ip_key( '203.0.113.9' ) );
    }

    public function test_ip_key_cannot_collide_with_a_credential_id(): void {
        // Credential IDs pass through sanitize_key(), which strips the colon, so no
        // credential ID can ever occupy the ip: namespace in the shared table.
        $this->assertSame( 'ipesu-sky', sanitize_key( 'ip:esu-sky' ) );
        $this->assertStringStartsWith( 'ip:', UniqueEntryBudget::ip_key( '198.51.100.7' ) );
    }

    public function test_ip_key_is_empty_for_an_unknown_source(): void {
        $this->assertSame( '', UniqueEntryBudget::ip_key( '' ) );
    }

    public function test_ip_key_fits_the_credential_id_column(): void {
        // credential_id is VARCHAR(64); a truncated key that collided would merge two
        // sources' accounting into one.
        $this->assertLessThanOrEqual( 64, strlen( UniqueEntryBudget::ip_key( '198.51.100.7' ) ) );
    }
}
