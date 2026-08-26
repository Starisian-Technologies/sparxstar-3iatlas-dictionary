<?php
/**
 * Rolling unique-entry budget accounting for Dictionary system credentials.
 *
 * Implements spec §1.2: per-system ceilings "expressed primarily as ROLLING
 * UNIQUE-ENTRY BUDGETS (distinct entries served per credential per rolling
 * window — this bounds corpus exposure directly, which is the protected
 * quantity)".
 *
 * Storage is a custom table with a unique index, which the spec names as one of
 * the two acceptable forms. It is required rather than preferred: post meta has
 * no unique index, so insert-if-absent would degrade to a read-then-write race —
 * exactly the race the spec forbids — and an object cache alone is not durable
 * across a flush, while the spec requires the store to survive restarts. See the
 * ADR brief §5 (D-10) for the governance conflict this creates with AGENTS.md and
 * the exception being requested.
 *
 * Counting is atomic: a single INSERT ... ON DUPLICATE KEY UPDATE per batch does
 * the insert-if-absent, so concurrent requests under one credential cannot both
 * observe an entry as new.
 *
 * @package Starisian\Sparxstar\IAtlas\api\auth
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\IAtlas\api\auth;

use Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryProtection;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

/**
 * UniqueEntryBudget — records and bounds distinct entries served per credential.
 */
final class UniqueEntryBudget {

    /**
     * Unsuffixed table name; the wpdb prefix is applied at runtime.
     *
     * @var string
     */
    private const TABLE = 'sparxstar_dict_entry_budget';

    /**
     * Schema version, bumped whenever the table definition changes.
     *
     * @var string
     */
    private const SCHEMA_VERSION = '2';

    /**
     * Option storing the installed schema version.
     *
     * @var string
     */
    public const SCHEMA_OPTION = 'sparxstar_dict_budget_schema';

    /**
     * Cron hook that purges rows older than the rolling window.
     *
     * @var string
     */
    public const PURGE_HOOK = 'sparxstar_dict_purge_entry_budget';

    /**
     * Default rolling window in seconds. The ADR sets the final value (brief D-5).
     *
     * @var int
     */
    private const DEFAULT_WINDOW = 86400;

    /**
     * Default distinct-entry ceiling per credential per window (brief D-5).
     *
     * Deliberately well below the corpus size: a ceiling near the corpus size lets a
     * compromised consumer walk everything in a few windows, which is the outcome
     * this control exists to prevent.
     *
     * @var int
     */
    private const DEFAULT_CEILING = 1000;

    /**
     * Fully qualified table name.
     *
     * @return string The prefixed table name.
     */
    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Length of the rolling window in seconds.
     *
     * @return int Window length, always at least 60 seconds.
     */
    public static function window_seconds(): int {
        /**
         * Filter the rolling unique-entry budget window.
         *
         * @param int $seconds Window length in seconds.
         */
        $seconds = (int) apply_filters( 'sparxstar_dict_budget_window', self::DEFAULT_WINDOW );
        return max( 60, $seconds );
    }

    /**
     * Distinct-entry ceiling for a given credential.
     *
     * @param string              $credential_id The credential identifier.
     * @param array<string,mixed> $credential   The stored credential record.
     * @return int Ceiling for this credential; 0 or less means unlimited.
     */
    public static function ceiling_for( string $credential_id, array $credential = array() ): int {
        $stored = isset( $credential['entry_budget'] ) ? (int) $credential['entry_budget'] : 0;

        // A non-positive stored value falls back to the default rather than meaning
        // "unlimited". Treating 0 as unlimited would turn an operator typo — absint()
        // of a mistyped `--budget` yields 0 — into a credential with no ceiling at all,
        // silently disabling the primary extraction control for that system.
        $ceiling = $stored > 0 ? $stored : self::DEFAULT_CEILING;

        /**
         * Filter the distinct-entry ceiling for one credential.
         *
         * @param int                 $ceiling       Ceiling for this window.
         * @param string              $credential_id The credential identifier.
         * @param array<string,mixed> $credential    The stored credential record.
         */
        return (int) apply_filters( 'sparxstar_dict_budget_ceiling', $ceiling, $credential_id, $credential );
    }

    /**
     * Create or update the budget table.
     *
     * Safe to call repeatedly; dbDelta only applies differences.
     *
     * @return void
     */
    public static function install(): void {
        global $wpdb;

        $table           = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // PRIMARY KEY across (credential_id, entry_id) is the unique index the spec
        // requires: it is what makes insert-if-absent atomic rather than a race.
        $sql = "CREATE TABLE {$table} (
            credential_id VARCHAR(64) NOT NULL,
            entry_id BIGINT UNSIGNED NOT NULL,
            last_served BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY  (credential_id, entry_id),
            KEY last_served (last_served)
        ) {$charset_collate};";

        dbDelta( $sql );

        // Record the schema as installed ONLY after confirming the table exists.
        // dbDelta() reports nothing useful on failure, and is_installed() trusts this
        // option; marking it unconditionally would let a failed creation look like
        // working accounting, which is the one direction a security control must never
        // fail. See fail-closed handling in record().
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema verification on a custom protection table; caching a schema probe would defeat its purpose.
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

        if ( $exists !== $table ) {
            delete_option( self::SCHEMA_OPTION );
            return;
        }

        update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
    }

    /**
     * Whether the budget table is present and usable.
     *
     * @return bool True when the table exists.
     */
    public static function is_installed(): bool {
        return self::SCHEMA_VERSION === (string) get_option( self::SCHEMA_OPTION, '' );
    }

    /**
     * Record entries as served under a credential and return the window total.
     *
     * Records first, then reports the resulting distinct-entry count. Recording before
     * checking is deliberate: it keeps the insert atomic, and it errs toward tighter
     * enforcement, because an over-budget batch is counted even though it is refused.
     * The alternative — count, decide, then record — is the read-then-write race the
     * spec rules out.
     *
     * @param string         $credential_id The credential identifier.
     * @param array<int,int> $entry_ids     Post IDs served by this request.
     * @return int Distinct entries served under this credential within the window,
     *             or -1 when accounting is unavailable.
     */
    public static function record( string $credential_id, array $entry_ids ): int {
        global $wpdb;

        if ( '' === $credential_id || ! self::is_installed() ) {
            return -1;
        }

        $entry_ids = array_values( array_unique( array_filter( array_map( 'absint', $entry_ids ) ) ) );
        $now       = time();
        $cutoff    = $now - self::window_seconds();
        $table     = self::table_name();

        if ( ! empty( $entry_ids ) ) {
            $rows   = array();
            $values = array();
            foreach ( $entry_ids as $entry_id ) {
                $rows[]   = '(%s, %d, %d)';
                $values[] = $credential_id;
                $values[] = $entry_id;
                $values[] = $now;
            }

            // Every touch advances last_served, so an entry counts for a full window
            // from its MOST RECENT service. Keying the window off first service instead
            // would drop an entry served at hour 0 and again at hour 23 out of the count
            // at hour 24, even though it was served an hour ago — freeing budget the
            // caller has not stopped using, which is an undercount in the one direction
            // that matters.
            $sql = "INSERT INTO {$table} (credential_id, entry_id, last_served) VALUES "
                . implode( ', ', $rows )
                . ' ON DUPLICATE KEY UPDATE last_served = %d';

            $values[] = $now;

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom protection table; placeholders are generated, every value is passed through prepare(), and the count must be uncached to be a security control.
            $written = $wpdb->query( $wpdb->prepare( $sql, $values ) );

            // A failed write means the accounting is unknown, not zero. Reporting it as
            // unavailable lets the caller fail closed at target state.
            if ( false === $written ) {
                return -1;
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom protection table; a cached budget count would be trivially defeated by concurrency.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE credential_id = %s AND last_served >= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is derived from $wpdb->prefix, not from input.
                $credential_id,
                $cutoff
            )
        );

        // get_var() returns null on a query error. Casting that to 0 would read as
        // "nothing served yet" and hand the caller an unbounded budget.
        if ( null === $count ) {
            return -1;
        }

        return (int) $count;
    }

    /**
     * Delete rows that have aged out of the rolling window.
     *
     * Only ever deletes strictly outside the live window, so a purge can never
     * discount an entry that is still being counted (spec §1.2).
     *
     * @return int Number of rows removed.
     */
    public static function purge(): int {
        global $wpdb;

        if ( ! self::is_installed() ) {
            return 0;
        }

        $table  = self::table_name();
        $cutoff = time() - self::window_seconds();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled maintenance on a custom protection table.
        $removed = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE last_served < %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is derived from $wpdb->prefix, not from input.
                $cutoff
            )
        );

        return (int) $removed;
    }

    /**
     * Register the scheduled purge.
     *
     * @return void
     */
    public static function register_hooks(): void {
        add_action( self::PURGE_HOOK, array( self::class, 'purge' ) );

        if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK );
        }
    }

    /**
     * Report a credential that has exhausted its budget (spec §3: "exhausts its
     * unique-entry budget in minutes and alarms").
     *
     * @param string $credential_id The credential identifier.
     * @param int    $served        Distinct entries served in the window.
     * @param int    $ceiling       The credential's ceiling.
     * @return void
     */
    public static function alarm_exhausted( string $credential_id, int $served, int $ceiling ): void {
        Sparxstar3IAtlasDictionaryProtection::log_security_event(
            'anomaly',
            'unique_entry_budget_exhausted',
            array(
                'credential_id' => $credential_id,
                'served'        => $served,
                'ceiling'       => $ceiling,
                'window'        => self::window_seconds(),
            )
        );
    }
}
