<?php
/**
 * Central protection policy for the 3iAtlas Dictionary corpus.
 *
 * Implements the shared decisions of the Asset Protection Specification
 * (docs/dictionary-asset-protection-spec.md): the cutover gate (§1.4), the
 * response caps every caller is subject to (§2), count suppression (§2), and
 * credential redaction for logs (§3).
 *
 * The cutover gate is the single switch the ADR's cutover milestone flips.
 * While it is closed the plugin behaves exactly as it did before this class
 * existed — that is the §1 migration exception, and it is why enforcement can
 * ship before the cutover is scheduled.
 *
 * @package Starisian\Sparxstar\IAtlas\api
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\IAtlas\api;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

/**
 * Sparxstar3IAtlasDictionaryProtection — policy constants and shared helpers.
 *
 * Stateless. Every method is static; nothing here holds a credential value.
 */
final class Sparxstar3IAtlasDictionaryProtection {

    /**
     * Option that records whether the M2M cutover (spec §1.4 step 4) has completed.
     *
     * @var string
     */
    public const CUTOVER_OPTION = 'sparxstar_dict_m2m_cutover';

    /**
     * Maximum search results returned to any caller (spec §2: "search results <= 20").
     *
     * @var int
     */
    public const SEARCH_RESULTS_MAX = 20;

    /**
     * Maximum page size for any list-shaped route (spec §2: "page size <= N").
     *
     * The ADR sets the final number (brief D-2); this is the shipped default.
     *
     * @var int
     */
    public const LIST_PAGE_MAX = 100;

    /**
     * Maximum entries a single game-set request may return.
     *
     * @var int
     */
    public const GAME_SET_MAX = 50;

    /**
     * Granularity for rounded result counts (spec §2: counts "rounded or omitted").
     *
     * @var int
     */
    private const COUNT_BUCKET = 100;

    /**
     * Ceiling above which a rounded count is reported as "many" rather than a number.
     *
     * An exact corpus count is a scraper's progress bar; so is a rounded one once the
     * corpus is small enough for the rounding to be uninformative.
     *
     * @var int
     */
    private const COUNT_CEILING = 1000;

    /**
     * Whether the machine-to-machine cutover has completed (spec §1.4 step 4).
     *
     * Until this returns true the deployed browser app's direct access continues
     * under the documented migration exception: existing credentials keep working,
     * CORS keeps being served, and the §1.1 tripwire stays in monitor-only mode.
     *
     * Settable by the `SPARXSTAR_DICT_M2M_CUTOVER` constant (wp-config.php, so the
     * flip is deploy-reviewed) or the option of the same name, and filterable for
     * tests. Defaults to false — enforcement is opt-in, never accidental.
     *
     * @return bool True once the cutover has completed and enforcement is armed.
     */
    public static function is_cutover_complete(): bool {
        $complete = false;

        if ( defined( 'SPARXSTAR_DICT_M2M_CUTOVER' ) ) {
            $complete = (bool) constant( 'SPARXSTAR_DICT_M2M_CUTOVER' );
        } else {
            $complete = (bool) get_option( self::CUTOVER_OPTION, false );
        }

        /**
         * Filter whether the M2M cutover is complete.
         *
         * @param bool $complete Current cutover state.
         */
        return (bool) apply_filters( 'sparxstar_dict_m2m_cutover_complete', $complete );
    }

    /**
     * Round a result count so it cannot be used as an extraction progress bar (spec §2).
     *
     * Counts below one bucket are reported exactly — a caller who asked for three
     * results learning that three exist reveals nothing. Above the ceiling the count
     * stops being a number at all.
     *
     * @param int $exact The true result count.
     * @return int|string Rounded count, or a "1000+" style string above the ceiling.
     */
    public static function approximate_count( int $exact ): int|string {
        if ( $exact <= 0 ) {
            return 0;
        }

        if ( $exact < self::COUNT_BUCKET ) {
            return $exact;
        }

        if ( $exact >= self::COUNT_CEILING ) {
            return (string) self::COUNT_CEILING . '+';
        }

        return (int) ( floor( $exact / self::COUNT_BUCKET ) * self::COUNT_BUCKET );
    }

    /**
     * Reduce a credential value to a non-reversible short fingerprint for logging (spec §3).
     *
     * Plugin and application logs record credential IDs, never credential values. When a
     * value must be referenced at all (correlating an unknown credential across log lines),
     * this is the only representation permitted.
     *
     * @param string $raw_credential The raw credential value. Never logged by this function.
     * @return string A short, stable, non-reversible fingerprint.
     */
    public static function fingerprint_credential( string $raw_credential ): string {
        if ( '' === $raw_credential ) {
            return 'none';
        }

        return 'sha256:' . substr( hash( 'sha256', $raw_credential ), 0, 12 );
    }

    /**
     * Record a security-relevant event (spec §3).
     *
     * Fires an action so the platform's log shipper can consume structured events, and
     * additionally writes to the PHP error log when debugging is enabled. Callers are
     * responsible for passing already-redacted context: this function does not inspect
     * the context for credential values, because a redaction routine that runs on
     * untrusted shapes gives false confidence.
     *
     * @param string              $severity One of 'info', 'anomaly', 'critical'.
     * @param string              $event    Short machine-readable event slug.
     * @param array<string,mixed> $context  Redacted structured context.
     * @return void
     */
    public static function log_security_event( string $severity, string $event, array $context = array() ): void {
        /**
         * Fires on every dictionary security event.
         *
         * @param string              $severity One of 'info', 'anomaly', 'critical'.
         * @param string              $event    Short machine-readable event slug.
         * @param array<string,mixed> $context  Redacted structured context.
         */
        do_action( 'sparxstar_dict_security_event', $severity, $event, $context );

        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }

        $prefix = 'critical' === $severity ? '[BREACH_DETECTED] ' : '[sparxstar-dict] ';
        $line   = $prefix . $event . ' ' . (string) wp_json_encode( $context );

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only diagnostic; the action above is the production path, and context is redacted by the caller per spec §3.
        error_log( $line );
    }
}
