<?php
/**
 * WP-CLI commands for managing Dictionary system credentials.
 *
 * Implements the operational half of spec §1.2: one credential per consuming
 * system, independently rotatable and independently revocable, so that a leak
 * names the system that lost it and a response touches only that system.
 *
 * The plaintext secret is printed exactly once, at generation or rotation. It is
 * never stored, never logged, and cannot be recovered — only its SHA-256 hash is
 * persisted (spec §1.2, §3).
 *
 * @package Starisian\Sparxstar\IAtlas\cli
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\IAtlas\cli;

use Starisian\Sparxstar\IAtlas\api\auth\SystemCredentialAuth;
use Starisian\Sparxstar\IAtlas\api\auth\UniqueEntryBudget;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

/**
 * Manages 3iAtlas Dictionary system credentials.
 *
 * ## EXAMPLES
 *
 *     wp sparxstar-dict system generate --id=esu-sky --label="ESU (Sky) games broker"
 *     wp sparxstar-dict system list
 *     wp sparxstar-dict system rotate --id=esu-sky
 *     wp sparxstar-dict system revoke --id=esu-sky
 */
class Sparxstar3IAtlasSystemCredentialCliCommands {

    /**
     * Generate a new system credential.
     *
     * Prints the plaintext secret exactly once. Store it in the consuming system's
     * secret manager; it is never recoverable from WordPress.
     *
     * ## OPTIONS
     *
     * --id=<credential-id>
     * : Stable identifier for the consuming system, e.g. esu-sky. Used for
     * attribution in logs and for rotation and revocation.
     *
     * [--label=<label>]
     * : Human-readable description of the consuming system.
     *
     * [--budget=<entries>]
     * : Rolling unique-entry budget per window. Defaults to the plugin default.
     *
     * ## EXAMPLES
     *
     *     wp sparxstar-dict system generate --id=esu-sky --label="ESU (Sky) games broker"
     *
     * @param array<int,string>    $args       Positional arguments (unused).
     * @param array<string,string> $assoc_args Named arguments.
     * @return void
     */
    public function generate( array $args, array $assoc_args ): void {
        unset( $args );

        $credential_id = sanitize_key( (string) ( $assoc_args['id'] ?? '' ) );

        if ( '' === $credential_id ) {
            \WP_CLI::error( 'A --id is required. Use the consuming system name, e.g. --id=esu-sky.' );
        }

        if ( null !== SystemCredentialAuth::find_by_id( $credential_id ) ) {
            \WP_CLI::error( sprintf( 'A credential with id "%s" already exists. Use `rotate` to replace its secret.', $credential_id ) );
        }

        // 32 bytes = 256 bits of entropy. bin2hex is a bijective encoding, so the
        // 64-character output carries exactly those 256 bits — representation does not
        // dilute entropy. (32 hex *characters* would be 128 bits; 32 *bytes* is not.)
        // Provision the §1.2 budget store here rather than at plugin activation: it is
        // the subject of ADR brief D-10, so it is created only when an operator
        // deliberately provisions a consuming system, never as a merge side effect.
        if ( ! UniqueEntryBudget::is_installed() ) {
            UniqueEntryBudget::install();
            \WP_CLI::log( 'Provisioned the unique-entry budget store (spec §1.2).' );
        }

        $secret  = bin2hex( random_bytes( 32 ) );
        $records = SystemCredentialAuth::all();

        $record = array(
            'credential_id' => $credential_id,
            'label'         => sanitize_text_field( (string) ( $assoc_args['label'] ?? $credential_id ) ),
            'secret_hash'   => hash( 'sha256', $secret ),
            'active'        => true,
            'created'       => time(),
            'rotated'       => time(),
        );

        if ( isset( $assoc_args['budget'] ) ) {
            $budget = $assoc_args['budget'];

            // Validated strictly rather than passed through absint(): absint( 'abc' )
            // and absint( '0' ) are both 0, and a 0 ceiling would previously have read
            // as "no limit" — turning a typo into a credential with the primary
            // extraction control switched off.
            if ( ! is_numeric( $budget ) || (int) $budget < 1 ) {
                \WP_CLI::error( '--budget must be a positive integer (distinct entries per rolling window).' );
            }

            $record['entry_budget'] = (int) $budget;
        }

        $records[] = $record;

        // Never print a secret the registry did not actually accept: a failed save
        // would otherwise hand an operator a credential that cannot authenticate.
        if ( ! SystemCredentialAuth::save( $records ) ) {
            \WP_CLI::error( sprintf( 'Failed to persist system credential "%s". No secret was issued.', $credential_id ) );
        }

        \WP_CLI::success( sprintf( 'Created system credential "%s".', $credential_id ) );
        \WP_CLI::line( '' );
        \WP_CLI::line( 'Secret (shown once — store it in the consuming system\'s secret manager):' );
        \WP_CLI::line( '' );
        \WP_CLI::line( '    ' . $secret );
        \WP_CLI::line( '' );
        \WP_CLI::line( 'The consuming system presents it as:' );
        \WP_CLI::line( '' );
        \WP_CLI::line( '    Authorization: Bearer ' . $secret );
        \WP_CLI::line( '' );
        \WP_CLI::warning( 'This value must never appear in browser-delivered code (spec §1.1).' );
    }

    /**
     * List system credentials.
     *
     * Secret hashes are not printed: nothing about a stored credential belongs on a
     * terminal that may be logged.
     *
     * ## EXAMPLES
     *
     *     wp sparxstar-dict system list
     *
     * @param array<int,string>    $args       Positional arguments (unused).
     * @param array<string,string> $assoc_args Named arguments (unused).
     * @return void
     */
    public function list( array $args, array $assoc_args ): void {
        unset( $args, $assoc_args );

        $records = SystemCredentialAuth::all();

        if ( empty( $records ) ) {
            \WP_CLI::line( 'No system credentials are registered.' );
            return;
        }

        $rows = array();

        foreach ( $records as $record ) {
            if ( ! is_array( $record ) ) {
                continue;
            }

            $credential_id = (string) ( $record['credential_id'] ?? '' );

            $rows[] = array(
                'credential_id' => $credential_id,
                'label'         => (string) ( $record['label'] ?? '' ),
                'active'        => ( $record['active'] ?? false ) ? 'yes' : 'revoked',
                'entry_budget'  => (string) UniqueEntryBudget::ceiling_for( $credential_id, $record ),
                'rotated'       => isset( $record['rotated'] ) ? gmdate( 'Y-m-d', (int) $record['rotated'] ) : '',
            );
        }

        \WP_CLI\Utils\format_items( 'table', $rows, array( 'credential_id', 'label', 'active', 'entry_budget', 'rotated' ) );
    }

    /**
     * Replace a credential's secret, keeping its identity and budget.
     *
     * Rotation is per-system by design: no other consuming system is interrupted
     * (spec §1.2).
     *
     * ## OPTIONS
     *
     * --id=<credential-id>
     * : The credential to rotate.
     *
     * ## EXAMPLES
     *
     *     wp sparxstar-dict system rotate --id=esu-sky
     *
     * @param array<int,string>    $args       Positional arguments (unused).
     * @param array<string,string> $assoc_args Named arguments.
     * @return void
     */
    public function rotate( array $args, array $assoc_args ): void {
        unset( $args );

        $credential_id = sanitize_key( (string) ( $assoc_args['id'] ?? '' ) );

        if ( '' === $credential_id ) {
            \WP_CLI::error( 'A --id is required.' );
        }

        $records = SystemCredentialAuth::all();
        // 256 bits of entropy, as in generate() above.
        $secret = bin2hex( random_bytes( 32 ) );
        $found  = false;

        foreach ( $records as $index => $record ) {
            if ( ! is_array( $record ) || (string) ( $record['credential_id'] ?? '' ) !== $credential_id ) {
                continue;
            }

            $records[ $index ]['secret_hash'] = hash( 'sha256', $secret );
            $records[ $index ]['rotated']     = time();
            $records[ $index ]['active']      = true;
            $found                            = true;
        }

        if ( ! $found ) {
            \WP_CLI::error( sprintf( 'No credential with id "%s".', $credential_id ) );
        }

        if ( ! SystemCredentialAuth::save( $records ) ) {
            \WP_CLI::error(
                sprintf(
                    'Failed to persist the rotation of "%s". The PREVIOUS secret is still active; do not deploy a new one.',
                    $credential_id
                )
            );
        }

        \WP_CLI::success( sprintf( 'Rotated system credential "%s".', $credential_id ) );
        \WP_CLI::line( '' );
        \WP_CLI::line( 'New secret (shown once):' );
        \WP_CLI::line( '' );
        \WP_CLI::line( '    ' . $secret );
        \WP_CLI::line( '' );
        \WP_CLI::warning( 'The previous secret stopped working immediately. Deploy this one to the consuming system.' );
    }

    /**
     * Revoke a credential without deleting its record.
     *
     * The record is retained so log lines naming the credential ID stay meaningful
     * for evidence retention (spec §3).
     *
     * ## OPTIONS
     *
     * --id=<credential-id>
     * : The credential to revoke.
     *
     * ## EXAMPLES
     *
     *     wp sparxstar-dict system revoke --id=esu-sky
     *
     * @param array<int,string>    $args       Positional arguments (unused).
     * @param array<string,string> $assoc_args Named arguments.
     * @return void
     */
    public function revoke( array $args, array $assoc_args ): void {
        unset( $args );

        $credential_id = sanitize_key( (string) ( $assoc_args['id'] ?? '' ) );

        if ( '' === $credential_id ) {
            \WP_CLI::error( 'A --id is required.' );
        }

        $records         = SystemCredentialAuth::all();
        $found           = false;
        $already_revoked = true;

        foreach ( $records as $index => $record ) {
            if ( ! is_array( $record ) || (string) ( $record['credential_id'] ?? '' ) !== $credential_id ) {
                continue;
            }

            if ( true === ( $record['active'] ?? false ) ) {
                $already_revoked = false;
            }

            $records[ $index ]['active']  = false;
            $records[ $index ]['revoked'] = time();
            $found                        = true;
        }

        if ( ! $found ) {
            \WP_CLI::error( sprintf( 'No credential with id "%s".', $credential_id ) );
        }

        // A revoke that silently fails to persist leaves a possibly-leaked credential
        // live while reporting otherwise, which is the worst failure mode of the three.
        if ( ! SystemCredentialAuth::save( $records ) && ! $already_revoked ) {
            \WP_CLI::error(
                sprintf(
                    'Failed to persist the revocation of "%s". The credential is STILL ACTIVE.',
                    $credential_id
                )
            );
        }

        \WP_CLI::success( sprintf( 'Revoked system credential "%s".', $credential_id ) );
    }
}
