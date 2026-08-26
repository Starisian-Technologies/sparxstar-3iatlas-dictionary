<?php
/**
 * Browser-origin tripwire for the Dictionary REST routes.
 *
 * Implements spec §1.1. The severities are the spec's, including its honesty about
 * what a preflight actually proves: "a preflight proves an ATTEMPT, not a leak,
 * since any webpage or scanner can cause one without holding any credential."
 *
 * Arming (spec §1.1 arming rule, read against §1.4 step 3):
 *
 * - `Origin` alone is an ANOMALY only once the cutover has completed. Before
 *   cutover the deployed browser app legitimately calls this route, so alerting on
 *   it "would page on every player". Before cutover these requests are still
 *   counted, because §1.4 step 3 requires the absence of browser traffic to be
 *   "observed, not assumed" and this counter is that measurement.
 * - `Origin` on a request bearing a VALID SYSTEM CREDENTIAL is a PRIORITY_1
 *   critical indicator immediately, in either state. This case cannot fire on
 *   legitimate player traffic: the browser app holds ephemeral page tokens, never
 *   a system credential, so arming it early costs no false pages and closes the
 *   window in which a burned credential would otherwise go unreported. It remains
 *   an indicator and not proof — server-side HTTP libraries can emit Origin.
 *
 * @package Starisian\Sparxstar\IAtlas\api
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\IAtlas\api;

use Starisian\Sparxstar\IAtlas\api\auth\SystemCredentialAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

/**
 * Sparxstar3IAtlasDictionaryTripwire — observes and reports browser-origin access.
 */
final class Sparxstar3IAtlasDictionaryTripwire {

    /**
     * REST route prefix this tripwire watches.
     *
     * Mirrors the nginx route regex named in spec §1.1,
     * `~^/wp-json/sparxstar/v1/dictionary(?:/|$)`, expressed as a WP REST route.
     *
     * @var string
     */
    private const ROUTE_PREFIX = '/sparxstar/v1/dictionary';

    /**
     * Option holding the monitor-only observation counts, keyed by UTC date.
     *
     * Deliberately an OPTION and not a transient. The ADR's cutover criterion (brief
     * D-3) is a run of consecutive days with zero observed browser-origin traffic, so
     * this is the one dataset the whole enforcement flip rests on. A transient on an
     * object-cache-backed site can evaporate on a cache flush, which would silently
     * MANUFACTURE a zero-observation day and let a cache restart satisfy the criterion.
     * An option row survives that.
     *
     * @var string
     */
    private const OBSERVATION_OPTION = 'sparxstar_dict_tripwire_observations';

    /**
     * Days of observation history retained.
     *
     * @var int
     */
    private const OBSERVATION_RETENTION_DAYS = 30;

    /**
     * Register the tripwire.
     *
     * @return void
     */
    public function register_hooks(): void {
        add_filter( 'rest_pre_dispatch', array( $this, 'inspect_request' ), 5, 3 );
    }

    /**
     * Inspect an incoming REST request for browser-origin indicators.
     *
     * Never alters the response: the tripwire reports, it does not block. Blocking is
     * the credential layer's job (spec §2, "the WordPress plugin is AUTHORITATIVE for
     * credential validation").
     *
     * @param mixed            $result  Pre-dispatch result, returned untouched.
     * @param \WP_REST_Server  $server  REST server instance, required by the filter signature.
     * @param \WP_REST_Request $request The incoming request.
     * @return mixed The unmodified $result.
     */
    public function inspect_request( $result, $server, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- $server is required by the rest_pre_dispatch filter signature.
        if ( ! $request instanceof \WP_REST_Request ) {
            return $result;
        }

        if ( ! self::is_dictionary_route( (string) $request->get_route() ) ) {
            return $result;
        }

        $origin     = trim( (string) $request->get_header( 'Origin' ) );
        $is_options = 'OPTIONS' === $request->get_method();

        if ( '' === $origin && ! $is_options ) {
            return $result;
        }

        $this->record_observation();

        $context = array(
            'route'  => $request->get_route(),
            'method' => $request->get_method(),
            'origin' => $origin,
        );

        // PRIORITY_1: a browser origin on a request that carries a real system
        // credential. Armed in both states — see the class docblock.
        if ( $this->has_valid_system_credential( $request ) ) {
            $context['indicator'] = 'PRIORITY_1';
            $context['posture']   = 'treat_credential_as_burned';

            Sparxstar3IAtlasDictionaryProtection::log_security_event(
                'critical',
                'system_credential_presented_with_browser_origin',
                $context
            );

            return $result;
        }

        // Origin alone. Anomaly once armed; a counted observation before that.
        if ( Sparxstar3IAtlasDictionaryProtection::is_cutover_complete() ) {
            Sparxstar3IAtlasDictionaryProtection::log_security_event(
                'anomaly',
                'browser_origin_on_dictionary_route',
                $context
            );

            return $result;
        }

        Sparxstar3IAtlasDictionaryProtection::log_security_event(
            'info',
            'tripwire_observation_monitor_only',
            $context
        );

        return $result;
    }

    /**
     * Whether a REST route is inside the Dictionary namespace, respecting its boundary.
     *
     * A bare prefix match would also match `/sparxstar/v1/dictionary-evil`, which is the
     * prefix-bypass the nginx round settled with `(?:/|$)`. This keeps the PHP matcher
     * semantically identical to the regex spec §1.1 says it mirrors.
     *
     * @param string $route The REST route.
     * @return bool True when the route is the namespace root or a child of it.
     */
    private static function is_dictionary_route( string $route ): bool {
        if ( self::ROUTE_PREFIX === $route ) {
            return true;
        }

        return str_starts_with( $route, self::ROUTE_PREFIX . '/' );
    }

    /**
     * Whether the request carries a credential that verifies against the registry.
     *
     * The credential value itself is never retained or logged by this check.
     *
     * @param \WP_REST_Request $request The incoming request.
     * @return bool True when a valid, active system credential is present.
     */
    private function has_valid_system_credential( \WP_REST_Request $request ): bool {
        $secret = SystemCredentialAuth::parse_bearer( (string) $request->get_header( 'Authorization' ) );

        if ( '' === $secret ) {
            return false;
        }

        $record = SystemCredentialAuth::find_by_secret( $secret );

        return null !== $record && true === ( $record['active'] ?? false );
    }

    /**
     * Increment today's browser-origin observation counter.
     *
     * This counter is the evidence spec §1.4 step 3 asks for: the cutover may only
     * proceed once it has been observed at zero, rather than assumed to be.
     *
     * @return void
     */
    private function record_observation(): void {
        $today  = gmdate( 'Y-m-d' );
        $counts = get_option( self::OBSERVATION_OPTION, array() );

        if ( ! is_array( $counts ) ) {
            $counts = array();
        }

        $counts[ $today ] = ( isset( $counts[ $today ] ) ? (int) $counts[ $today ] : 0 ) + 1;

        // Keep a bounded window of history. Trimming by key order is safe because the
        // keys are ISO dates, which sort chronologically as strings.
        if ( count( $counts ) > self::OBSERVATION_RETENTION_DAYS ) {
            ksort( $counts );
            $counts = array_slice( $counts, -self::OBSERVATION_RETENTION_DAYS, null, true );
        }

        update_option( self::OBSERVATION_OPTION, $counts, false );
    }

    /**
     * Read the browser-origin observation count for a given UTC day.
     *
     * @param string $day Date in Y-m-d form. Defaults to today (UTC).
     * @return int Observations recorded that day.
     */
    public static function observations_for( string $day = '' ): int {
        $day    = '' !== $day ? $day : gmdate( 'Y-m-d' );
        $counts = get_option( self::OBSERVATION_OPTION, array() );

        if ( ! is_array( $counts ) || ! isset( $counts[ $day ] ) ) {
            return 0;
        }

        return (int) $counts[ $day ];
    }

    /**
     * All retained observation counts, keyed by UTC date.
     *
     * This is the evidence the ADR's cutover criterion (brief D-3) is assessed against:
     * a day absent from this map is a day with no recorded browser-origin traffic.
     *
     * @return array<string,int> Date => observation count, chronologically ordered.
     */
    public static function observation_history(): array {
        $counts = get_option( self::OBSERVATION_OPTION, array() );

        if ( ! is_array( $counts ) ) {
            return array();
        }

        $counts = array_map( 'intval', $counts );
        ksort( $counts );

        return $counts;
    }
}
