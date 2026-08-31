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
     * Resolve a source IP that can actually be trusted for accounting.
     *
     * The per-IP signature is only meaningful if the caller cannot choose its own
     * bucket. `get_client_ip()` (used for rate limiting) takes `CF-Connecting-IP` or
     * the first `X-Forwarded-For` value whenever a global flag is set, without
     * checking that the peer is a proxy entitled to assert them — so a credential
     * holder who can reach the origin directly could rotate forged headers and split a
     * corpus walk across arbitrary buckets, keeping every bucket under the threshold.
     *
     * The three cases:
     *
     * - No proxy declared: the peer address IS the source. Use it.
     * - Proxy declared AND the peer is on the trusted list: the forwarded header is
     *   asserted by a party entitled to assert it. Use it.
     * - Proxy declared but no trusted list configured: trust cannot be established.
     *   Return nothing rather than a spoofable value — and rather than the proxy's own
     *   address, which would bucket ALL traffic together and make the signature fire
     *   constantly on ordinary load.
     *
     * Deliberately separate from `get_client_ip()`: rate limiting keeps its existing
     * behaviour, so tightening detection cannot change how requests are throttled.
     *
     * @return string A trustworthy source IP, or an empty string when none can be established.
     */
    public static function trusted_source_ip(): string {
        // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders,WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__ -- Validated as an IP on the very next line, and this value is used for security accounting rather than for anything a cache could serve.
        $remote_addr = trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
        $peer        = false !== filter_var( $remote_addr, FILTER_VALIDATE_IP ) ? $remote_addr : '';

        $behind_proxy = defined( 'SPARX_3IATLAS_TRUST_PROXY_HEADERS' )
            && true === constant( 'SPARX_3IATLAS_TRUST_PROXY_HEADERS' );

        if ( ! $behind_proxy ) {
            return $peer;
        }

        if ( '' === $peer || ! self::is_trusted_proxy( $peer ) ) {
            return '';
        }

        // These headers ARE user-controlled, which is the whole point of this method:
        // they are read only after the peer has been confirmed a trusted proxy above,
        // and each candidate is validated with filter_var in the loop below.
        // phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders -- Read behind a trusted-proxy check and validated below.
        $forwarded = array(
            sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '' ) ),
            sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '' ) ),
        );
        // phpcs:enable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders

        foreach ( $forwarded as $header ) {
            if ( '' === $header ) {
                continue;
            }

            $candidate = trim( explode( ',', $header )[0] );
            $valid     = filter_var(
                $candidate,
                FILTER_VALIDATE_IP,
                array( 'flags' => FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE )
            );

            if ( false !== $valid ) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Whether a peer address is a proxy entitled to assert forwarding headers.
     *
     * Configured via the `SPARX_3IATLAS_TRUSTED_PROXIES` constant (array or
     * comma-separated string of exact IPs or CIDR ranges) or the matching filter.
     * Unconfigured means no proxy is trusted, which is what makes the "cannot
     * establish trust" case above reachable rather than theoretical.
     *
     * @param string $peer The connecting peer address.
     * @return bool True when the peer is a configured trusted proxy.
     */
    private static function is_trusted_proxy( string $peer ): bool {
        $configured = defined( 'SPARX_3IATLAS_TRUSTED_PROXIES' )
            ? constant( 'SPARX_3IATLAS_TRUSTED_PROXIES' )
            : array();

        if ( is_string( $configured ) ) {
            $configured = explode( ',', $configured );
        }

        if ( ! is_array( $configured ) ) {
            $configured = array();
        }

        /**
         * Filter the proxies entitled to assert client-IP forwarding headers.
         *
         * @param array<int,string> $configured Exact IPs or CIDR ranges.
         */
        $configured = (array) apply_filters( 'sparxstar_dict_trusted_proxies', $configured );

        foreach ( $configured as $entry ) {
            $entry = trim( (string) $entry );

            if ( '' === $entry ) {
                continue;
            }

            if ( $entry === $peer ) {
                return true;
            }

            if ( str_contains( $entry, '/' ) && self::ip_in_cidr( $peer, $entry ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether an IPv4 address falls inside a CIDR range.
     *
     * IPv6 ranges are matched by exact address only; a partial IPv6 implementation
     * that silently mismatched would be worse than an explicit limitation.
     *
     * @param string $ip   The address to test.
     * @param string $cidr The range, as `network/prefix`.
     * @return bool True when the address is inside the range.
     */
    private static function ip_in_cidr( string $ip, string $cidr ): bool {
        $parts = explode( '/', $cidr, 2 );

        if ( 2 !== count( $parts ) ) {
            return false;
        }

        $network = filter_var( $parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
        $address = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );

        if ( false === $network || false === $address ) {
            return false;
        }

        $prefix = (int) $parts[1];

        if ( $prefix < 0 || $prefix > 32 ) {
            return false;
        }

        if ( 0 === $prefix ) {
            return true;
        }

        $mask = -1 << ( 32 - $prefix );

        return ( ip2long( $address ) & $mask ) === ( ip2long( $network ) & $mask );
    }

    /**
     * Report that per-source accounting could not be written or read.
     *
     * The per-IP signature is the only one that sees a walk spread across several
     * credentials, so its silent absence is itself worth knowing about. Distinct from
     * the signature staying quiet on an unmeasured request: this says the measurement
     * itself is unavailable.
     *
     * @param string $reason Short machine-readable reason slug.
     * @return bool True when an alert was emitted; false when suppressed as a duplicate.
     */
    public static function report_source_accounting_unavailable( string $reason ): bool {
        if ( ! self::should_emit( 'source_accounting_unavailable', $reason ) ) {
            return false;
        }

        Sparxstar3IAtlasDictionaryProtection::log_security_event(
            'anomaly',
            'source_accounting_unavailable',
            array(
                'reason'  => $reason,
                'effect'  => 'distinct_entries_per_ip signature is not running',
                'runbook' => 'docs/dictionary-enumeration-response-runbook.md',
            )
        );

        return true;
    }

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

        // ceil(), not floor(): flooring fires BELOW the documented percentage. With a
        // ceiling of 2, floor( 2 * 0.8 ) is 1, so the "80%" signature would report at
        // 50%. ceil() makes the threshold the first integer that actually reaches the
        // stated fraction.
        $threshold = (int) ceil( $ceiling * self::NEAR_CAP_FRACTION );

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

        // ceil() for the same reason as near-cap, and for a second one: flooring
        // produced a threshold of 0 for any ceiling below 4, and the guard below then
        // disabled this signature entirely for those budgets — silently, on exactly the
        // smallest ceilings where a single entry already exceeds the fraction.
        $threshold = (int) ceil( $ceiling * self::VELOCITY_FRACTION );

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
        if ( ! self::should_emit( $signature, $subject ) ) {
            return false;
        }

        $context['signature'] = $signature;
        $context['runbook']   = 'docs/dictionary-enumeration-response-runbook.md';

        Sparxstar3IAtlasDictionaryProtection::log_security_event(
            'anomaly',
            'enumeration_signature_detected',
            $context
        );

        return true;
    }

    /**
     * Whether an alert of this kind for this subject is due, marking it emitted.
     *
     * @param string $kind    Alert kind slug.
     * @param string $subject Credential ID, source key, or reason the alert is about.
     * @return bool True when the caller should emit.
     */
    private static function should_emit( string $kind, string $subject ): bool {
        $dedupe_key = self::ALERT_DEDUPE_PREFIX . $kind . '_' . md5( $subject );

        if ( false !== get_transient( $dedupe_key ) ) {
            return false;
        }

        set_transient( $dedupe_key, 1, self::ALERT_DEDUPE_TTL );

        return true;
    }
}
