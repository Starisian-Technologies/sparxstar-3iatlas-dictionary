<?php
/**
 * Enumeration-signature detection for the Dictionary API.
 *
 * Implements the alerting half of spec §3: "Alert on enumeration signatures:
 * sequential/near-sequential token walks, breadth-first key coverage, sustained
 * rates near the cap, many distinct entries per IP per hour."
 *
 * This is the control §4 says carries most of the protective weight —
 * "catching the harvest in progress beats proving the copy afterward" — because
 * the §1.2 ceiling only fires once a system has already taken its whole budget,
 * whereas these signatures fire while it is still taking it.
 *
 * Three of the four signatures are implemented here. The fourth,
 * sequential/near-sequential PAGE-TOKEN walks, is deliberately absent: it needs
 * cursor lineage, which arrives with the opaque signed page tokens of §9 step 4.
 * Detecting it would be guesswork until those cursors carry a traceable order,
 * and a signature that cannot actually see what it claims to watch is worse than
 * a documented gap.
 *
 * @package Starisian\Sparxstar\IAtlas\api
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\IAtlas\api;

use Starisian\Sparxstar\IAtlas\api\auth\UniqueEntryBudget;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

/**
 * Sparxstar3IAtlasDictionaryEnumerationDetector — watches for corpus walks in progress.
 */
final class Sparxstar3IAtlasDictionaryEnumerationDetector {

    /**
     * Look-back for the velocity signature, in seconds.
     *
     * @var int
     */
    private const VELOCITY_WINDOW = 600;

    /**
     * Fraction of a credential's whole window budget which, if taken inside the
     * velocity window, indicates a walk rather than product use.
     *
     * @var float
     */
    private const VELOCITY_FRACTION = 0.25;

    /**
     * Fraction of the ceiling at which sustained consumption is reported.
     *
     * @var float
     */
    private const NEAR_CAP_FRACTION = 0.8;

    /**
     * Distinct entries from one source IP in an hour that warrant a look.
     *
     * @var int
     */
    private const IP_HOURLY_DISTINCT = 500;

    /**
     * Transient prefix used to avoid repeating the same alert every request.
     *
     * A transient is correct HERE, unlike the §1.4 cutover evidence, which is an
     * option precisely because losing it would manufacture a false result. Losing a
     * dedup marker costs a duplicate alert; it can never suppress one.
     *
     * @var string
     */
    private const ALERT_DEDUPE_PREFIX = 'sparxstar_dict_enum_alert_';

    /**
     * Seconds an alert of a given kind stays deduplicated.
     *
     * @var int
     */
    private const ALERT_DEDUPE_TTL = 900;

    /**
     * Inspect one served response for enumeration signatures.
     *
     * Reporting only. Refusal is the §1.2 ceiling's job; this exists so a walk is
     * visible long before the ceiling stops it.
     *
     * @param string $credential_id The calling system's credential ID.
     * @param int    $served        Distinct entries served in the credential's window.
     * @param int    $ceiling       The credential's distinct-entry ceiling.
     * @param string $source_ip     Source IP, or an empty string when unavailable.
     * @return array<int,string> Signature slugs reported, for tests and callers.
     */
    public static function inspect( string $credential_id, int $served, int $ceiling, string $source_ip = '' ): array {
        $reported = array();

        if ( '' === $credential_id || $served < 0 ) {
            return $reported;
        }

        if ( self::report_near_cap( $credential_id, $served, $ceiling ) ) {
            $reported[] = 'sustained_near_cap';
        }

        if ( self::report_velocity( $credential_id, $ceiling ) ) {
            $reported[] = 'breadth_first_coverage';
        }

        if ( '' !== $source_ip && self::report_ip_breadth( $source_ip ) ) {
            $reported[] = 'distinct_entries_per_ip';
        }

        return $reported;
    }

    /**
     * Report a credential sustaining consumption near its ceiling (spec §3).
     *
     * @param string $credential_id The credential ID.
     * @param int    $served        Distinct entries served in the window.
     * @param int    $ceiling       The credential's ceiling.
     * @return bool True when the signature was reported.
     */
    private static function report_near_cap( string $credential_id, int $served, int $ceiling ): bool {
        if ( $ceiling <= 0 ) {
            return false;
        }

        $threshold = (int) floor( $ceiling * self::NEAR_CAP_FRACTION );

        if ( $served < $threshold ) {
            return false;
        }

        return self::report(
            'sustained_near_cap',
            $credential_id,
            array(
                'credential_id' => $credential_id,
                'served'        => $served,
                'ceiling'       => $ceiling,
                'threshold'     => $threshold,
            )
        );
    }

    /**
     * Report a credential taking a wide swathe of new entries very quickly (spec §3).
     *
     * Breadth-first coverage is what a walk looks like from the server: a consumer
     * serving real users re-reads a working set, so its DISTINCT count grows slowly
     * even when its request count does not. A walker's distinct count tracks its
     * request count almost exactly.
     *
     * @param string $credential_id The credential ID.
     * @param int    $ceiling       The credential's ceiling.
     * @return bool True when the signature was reported.
     */
    private static function report_velocity( string $credential_id, int $ceiling ): bool {
        if ( $ceiling <= 0 ) {
            return false;
        }

        $recent = UniqueEntryBudget::count_recent( $credential_id, self::VELOCITY_WINDOW );

        // -1 means the accounting could not answer. Silence is the honest response:
        // reporting an unmeasured request as either safe or hostile would be invented.
        if ( $recent < 0 ) {
            return false;
        }

        $threshold = (int) floor( $ceiling * self::VELOCITY_FRACTION );

        if ( $threshold < 1 || $recent < $threshold ) {
            return false;
        }

        return self::report(
            'breadth_first_coverage',
            $credential_id,
            array(
                'credential_id'  => $credential_id,
                'distinct_seen'  => $recent,
                'within_seconds' => self::VELOCITY_WINDOW,
                'threshold'      => $threshold,
                'ceiling'        => $ceiling,
            )
        );
    }

    /**
     * Report many distinct entries from a single source IP in an hour (spec §3).
     *
     * Keyed on the IP rather than the credential so a walk spread across several
     * credentials from one host is still one visible pattern.
     *
     * @param string $source_ip The source IP address.
     * @return bool True when the signature was reported.
     */
    private static function report_ip_breadth( string $source_ip ): bool {
        $key = UniqueEntryBudget::ip_key( $source_ip );

        if ( '' === $key ) {
            return false;
        }

        $recent = UniqueEntryBudget::count_recent( $key, HOUR_IN_SECONDS );

        if ( $recent < 0 || $recent < self::IP_HOURLY_DISTINCT ) {
            return false;
        }

        return self::report(
            'distinct_entries_per_ip',
            $key,
            array(
                // The key, never the address: this line goes to a log, and §3 keeps
                // the reversible source IP in the request log rather than scattering
                // it through every alert.
                'source_key'    => $key,
                'distinct_seen' => $recent,
                'threshold'     => self::IP_HOURLY_DISTINCT,
            )
        );
    }

    /**
     * Emit one enumeration alert, deduplicated per subject and kind.
     *
     * @param string              $signature The signature slug.
     * @param string              $subject   Credential ID or source key the alert is about.
     * @param array<string,mixed> $context   Redacted structured context.
     * @return bool True when an alert was emitted; false when suppressed as a duplicate.
     */
    private static function report( string $signature, string $subject, array $context ): bool {
        $dedupe_key = self::ALERT_DEDUPE_PREFIX . $signature . '_' . md5( $subject );

        if ( false !== get_transient( $dedupe_key ) ) {
            return false;
        }

        set_transient( $dedupe_key, 1, self::ALERT_DEDUPE_TTL );

        $context['signature'] = $signature;
        $context['runbook']   = 'docs/dictionary-enumeration-response-runbook.md';

        Sparxstar3IAtlasDictionaryProtection::log_security_event(
            'anomaly',
            'enumeration_signature_detected',
            $context
        );

        return true;
    }
}
