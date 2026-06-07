<?php

declare(strict_types=1);

/**
 * WP-CLI command class for the 3iAtlas Dictionary.
 *
 * Provides `wp sparxstar-dict key generate`, `wp sparxstar-dict key list`, and
 * `wp sparxstar-dict key revoke` commands for managing long-lived API keys.
 * Only loaded when WP_CLI is defined and true.
 *
 * @package Starisian\Sparxstar\IAtlas\cli
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

namespace Starisian\Sparxstar\IAtlas\cli;

use Starisian\Sparxstar\IAtlas\api\auth\ApiKeyAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

/**
 * Manages 3iAtlas Dictionary API keys.
 *
 * ## EXAMPLES
 *
 *     wp sparxstar-dict key generate --label=my-client
 *     wp sparxstar-dict key list
 *     wp sparxstar-dict key revoke --label=my-client
 */
class Sparxstar3IAtlasDictionaryCliCommands {

    /** @var int Default daily quota assigned to newly generated keys. */
    private const DEFAULT_DAILY_QUOTA = 10000;

    /**
     * Generate a new API key and store its SHA-256 hash.
     *
     * Prints the plaintext key exactly once — it is never stored and
     * cannot be recovered. The label is used for display and revocation.
     *
     * ## OPTIONS
     *
     * [--label=<name>]
     * : Human-readable label for this key (required).
     *
     * ## EXAMPLES
     *
     *     wp sparxstar-dict key generate --label=partner-acme
     *
     * @param array<int,string>    $args       Positional arguments (unused).
     * @param array<string,string> $assoc_args Named arguments.
     * @return void
     */
    public function generate( array $args, array $assoc_args ): void {
        $label = trim( (string) ( $assoc_args['label'] ?? '' ) );
        if ( '' === $label ) {
            \WP_CLI::error( 'The --label argument is required.' );
            return;
        }

        // Generate 32 cryptographically secure random bytes → 64-char hex string.
        $plaintext_key = bin2hex( random_bytes( 32 ) );
        $key_hash      = hash( 'sha256', $plaintext_key );

        $keys = get_option( ApiKeyAuth::KEYS_OPTION, array() );
        if ( ! is_array( $keys ) ) {
            $keys = array();
        }

        // Guard: refuse duplicate labels.
        foreach ( $keys as $entry ) {
            if ( is_array( $entry ) && ( $entry['label'] ?? '' ) === $label ) {
                \WP_CLI::error( "A key with label \"{$label}\" already exists. Revoke it first." );
                return;
            }
        }

        $keys[] = array(
            'key_hash'    => $key_hash,
            'label'       => $label,
            'daily_quota' => self::DEFAULT_DAILY_QUOTA,
            'active'      => true,
        );

        update_option( ApiKeyAuth::KEYS_OPTION, $keys, false );

        // Only the label is stored/logged — never the plaintext key.
        \WP_CLI::log( '' );
        \WP_CLI::log( \WP_CLI::colorize( '%GNEW API KEY — COPY NOW. This will not be shown again.%n' ) );
        \WP_CLI::log( '' );
        \WP_CLI::log( "Label:       {$label}" );
        \WP_CLI::log( "Key:         {$plaintext_key}" );
        \WP_CLI::log( 'Hash prefix: ' . substr( $key_hash, 0, 8 ) . '...' );
        \WP_CLI::log( '' );
        \WP_CLI::warning( 'Store the key in a secrets manager now. It cannot be retrieved again.' );
        \WP_CLI::log( '' );
        \WP_CLI::success( "API key \"{$label}\" created." );
    }

    /**
     * List all registered API keys.
     *
     * Displays label, hash prefix (first 8 chars), daily quota, and active status.
     * The plaintext key and full hash are never shown.
     *
     * ## EXAMPLES
     *
     *     wp sparxstar-dict key list
     *
     * @param array<int,string>    $args       Positional arguments (unused).
     * @param array<string,string> $assoc_args Named arguments (unused).
     * @return void
     */
    public function list( array $args, array $assoc_args ): void {
        $keys = get_option( ApiKeyAuth::KEYS_OPTION, array() );
        if ( ! is_array( $keys ) || 0 === count( $keys ) ) {
            \WP_CLI::log( 'No API keys registered.' );
            return;
        }

        $rows = array();
        foreach ( $keys as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }
            $hash  = (string) ( $entry['key_hash'] ?? '' );
            $rows[] = array(
                'label'       => (string) ( $entry['label'] ?? '(no label)' ),
                'hash_prefix' => '' !== $hash ? substr( $hash, 0, 8 ) . '...' : '(unknown)',
                'daily_quota' => (int) ( $entry['daily_quota'] ?? self::DEFAULT_DAILY_QUOTA ),
                'active'      => ( $entry['active'] ?? false ) ? 'yes' : 'no',
            );
        }

        \WP_CLI\Utils\format_items( 'table', $rows, array( 'label', 'hash_prefix', 'daily_quota', 'active' ) );
    }

    /**
     * Revoke a key by setting its active flag to false.
     *
     * ## OPTIONS
     *
     * [--label=<name>]
     * : Label of the key to revoke (required).
     *
     * ## EXAMPLES
     *
     *     wp sparxstar-dict key revoke --label=partner-acme
     *
     * @param array<int,string>    $args       Positional arguments (unused).
     * @param array<string,string> $assoc_args Named arguments.
     * @return void
     */
    public function revoke( array $args, array $assoc_args ): void {
        $label = trim( (string) ( $assoc_args['label'] ?? '' ) );
        if ( '' === $label ) {
            \WP_CLI::error( 'The --label argument is required.' );
            return;
        }

        $keys = get_option( ApiKeyAuth::KEYS_OPTION, array() );
        if ( ! is_array( $keys ) ) {
            \WP_CLI::error( 'No API keys found.' );
            return;
        }

        $found = false;
        foreach ( $keys as &$entry ) {
            if ( is_array( $entry ) && ( $entry['label'] ?? '' ) === $label ) {
                $entry['active'] = false;
                $found           = true;
                break;
            }
        }
        unset( $entry );

        if ( ! $found ) {
            \WP_CLI::error( "No key found with label \"{$label}\"." );
            return;
        }

        update_option( ApiKeyAuth::KEYS_OPTION, $keys, false );
        \WP_CLI::success( "API key \"{$label}\" has been revoked." );
    }
}
