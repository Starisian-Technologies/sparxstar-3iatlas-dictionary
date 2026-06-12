<?php

declare(strict_types=1);

/**
 * Word proposal endpoint — dispatches user-submitted lexical proposals to ESU
 * via the IngestManifest v0.1 seam contract. Falls back to a local quarantine
 * queue when ESU is not yet configured.
 *
 * Architecture references:
 *   - IngestManifest v0.1 seam contract (communication door / evidence face)
 *   - Event Contract v0.1 (reward face — artifact_submitted, droppable)
 *   - ADR-011: Unconditional capture — deny nothing, quarantine pending enrichment
 *   - INV-005 (amended): "rejected" means not admitted to governed store, not destroyed
 *   - OQ-015: location_ref is null until place registry minting authority is resolved
 *   - INV-010: participant_token is opaque in-memory token, never an account_id
 *
 * @package Starisian\Sparxstar\IAtlas\api
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

namespace Starisian\Sparxstar\IAtlas\api;

use Starisian\Sparxstar\IAtlas\api\auth\DictionaryAuthResolver;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

final class Sparxstar3IAtlasDictionaryProposals {

    use Sparxstar3IAtlasRateLimitTrait;

    private const REST_NAMESPACE       = 'sparxstar/v1/dictionary';
    private const QUARANTINE_OPTION    = 'sparxstar_proposal_queue';
    private const QUARANTINE_CAP       = 500;
    private const RATE_LIMIT           = 10;
    private const RATE_WINDOW          = 900;

    public function register_hooks(): void {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function register_rest_routes(): void {
        register_rest_route(
            self::REST_NAMESPACE,
            '/proposals',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_proposal' ),
                'permission_callback' => array( $this, 'permission_browse_or_consumer' ),
                'args'                => array(
                    'headword'       => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => static function ( $value ): bool {
                            return is_string( $value ) && mb_strlen( trim( $value ) ) >= 1 && mb_strlen( trim( $value ) ) <= 200;
                        },
                    ),
                    'language'       => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => static function ( $value ): bool {
                            // BCP 47 tag or slug — allow letters, digits, hyphens, underscores
                            return is_string( $value ) && preg_match( '/^[a-zA-Z0-9_\-]{1,35}$/', trim( $value ) ) === 1;
                        },
                    ),
                    'gloss_en'       => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => static function ( $value ): bool {
                            return mb_strlen( trim( $value ) ) <= 500;
                        },
                    ),
                    'example'        => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
                        'validate_callback' => static function ( $value ): bool {
                            return mb_strlen( trim( $value ) ) <= 1000;
                        },
                    ),
                    'dialect_region' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => static function ( $value ): bool {
                            return mb_strlen( trim( $value ) ) <= 200;
                        },
                    ),
                    'notes'          => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
                        'validate_callback' => static function ( $value ): bool {
                            return mb_strlen( trim( $value ) ) <= 2000;
                        },
                    ),
                    'participant_token' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'session_id'     => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => static function ( $value ): bool {
                            return mb_strlen( trim( $value ) ) <= 64;
                        },
                    ),
                ),
            )
        );
    }

    /**
     * Permission callback: ephemeral token OR API key required.
     */
    public function permission_browse_or_consumer( \WP_REST_Request $request ): bool|\WP_Error {
        $resolver = new DictionaryAuthResolver();
        $result   = $resolver->resolve( $request );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $request->set_param( '_auth_context', $result );
        return true;
    }

    /**
     * Handle POST /proposals.
     *
     * Constructs an IngestManifest v0.1 payload and dispatches it to ESU
     * (SPARXSTAR_ESU_ENDPOINT). If ESU is not configured, the manifest is
     * quarantined locally per ADR-011 (deny nothing, quarantine pending enrichment).
     *
     * A non-blocking reward event (artifact_submitted) is fired to the Game
     * Service if SPARXSTAR_GAME_SERVICE_URL is defined. Reward events are
     * droppable — failure is silent.
     */
    public function handle_proposal( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        if ( ! $this->check_rate_limit() ) {
            return new \WP_Error(
                'rate_limited',
                __( 'Too many proposals. Please wait before submitting again.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 429 )
            );
        }

        $headword       = trim( (string) $request->get_param( 'headword' ) );
        $language       = trim( (string) $request->get_param( 'language' ) );
        $gloss_en       = trim( (string) ( $request->get_param( 'gloss_en' ) ?? '' ) );
        $example        = trim( (string) ( $request->get_param( 'example' ) ?? '' ) );
        $dialect_region = trim( (string) ( $request->get_param( 'dialect_region' ) ?? '' ) );
        $notes          = trim( (string) ( $request->get_param( 'notes' ) ?? '' ) );
        $participant_token = trim( (string) ( $request->get_param( 'participant_token' ) ?? '' ) );
        $session_id        = trim( (string) ( $request->get_param( 'session_id' ) ?? '' ) );

        // IngestManifest v0.1 — Communication Door / Evidence Face
        // contributor_ref and consent_ref are null — Helios not yet live.
        // location_ref is null — OQ-015 (place registry minting authority) pending.
        // retention_class defaults to ephemeral (fail-closed — no vault consent in this flow).
        $upload_uuid = wp_generate_uuid4();
        $manifest    = array(
            'schema'              => 'IngestManifest/v0.1',
            'upload_uuid'         => $upload_uuid,
            'source_type'         => 'field',
            'door'                => 'communication',
            'language_bcp47'      => $language,
            'retention_class'     => 'ephemeral',
            'consent_ref'         => null,
            'contributor_ref'     => null,
            'observed_at'         => gmdate( 'c' ),
            'location_ref'        => null,
            'provided_transcript' => $headword,
            'acoustic_context'    => 'word_proposal',
            'metadata'            => array_filter( array(
                'gloss_en'       => $gloss_en ?: null,
                'example'        => $example ?: null,
                'dialect_region' => $dialect_region ?: null,
                'notes'          => $notes ?: null,
            ) ),
        );

        $dispatched = $this->dispatch_to_esu( $manifest );

        // Fire reward event — droppable, non-blocking (INV-011: signal routes concern, record carries measurement).
        $this->fire_reward_event( array(
            'event_type'        => 'artifact_submitted',
            'source_tool'       => 'dictionary',
            'participant_token' => $participant_token ?: null,
            'session_id'        => $session_id ?: null,
            'game_type'         => null,
            'occurred_at'       => gmdate( 'c' ),
            'payload'           => array(
                'artifact_type' => 'word_proposal',
                'language'      => $language,
            ),
        ) );

        return new \WP_REST_Response(
            array(
                'success' => true,
                'data'    => array(
                    'upload_uuid' => $upload_uuid,
                    'queued'      => ! $dispatched,
                ),
            ),
            202
        );
    }

    /**
     * Dispatch an IngestManifest to ESU.
     *
     * Returns true if ESU accepted the manifest (2xx), false if ESU is not
     * configured or the request failed. On failure the manifest is quarantined
     * locally (ADR-011 — deny nothing, quarantine pending enrichment).
     *
     * @param array<string,mixed> $manifest
     * @return bool True if ESU accepted; false if quarantined locally.
     */
    private function dispatch_to_esu( array $manifest ): bool {
        $esu_endpoint = defined( 'SPARXSTAR_ESU_ENDPOINT' ) ? rtrim( (string) constant( 'SPARXSTAR_ESU_ENDPOINT' ), '/' ) : '';

        if ( '' === $esu_endpoint ) {
            $this->quarantine_locally( $manifest );
            return false;
        }

        $response = wp_remote_post(
            $esu_endpoint . '/ingest',
            array(
                'timeout'     => 5,
                'blocking'    => true,
                'body'        => wp_json_encode( $manifest ),
                'headers'     => array( 'Content-Type' => 'application/json' ),
                'data_format' => 'body',
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->quarantine_locally( $manifest );
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            $this->quarantine_locally( $manifest );
            return false;
        }

        return true;
    }

    /**
     * Store a manifest in the local quarantine queue.
     *
     * The queue is capped at QUARANTINE_CAP entries. When the cap is reached
     * the oldest entry is dropped (FIFO). This is a temporary hold — once ESU
     * is live, a WP-CLI command can flush the queue.
     *
     * @param array<string,mixed> $manifest
     */
    private function quarantine_locally( array $manifest ): void {
        $queue   = get_option( self::QUARANTINE_OPTION, array() );
        $queue[] = array(
            'manifest'    => $manifest,
            'quarantined_at' => gmdate( 'c' ),
        );

        if ( count( $queue ) > self::QUARANTINE_CAP ) {
            $queue = array_slice( $queue, -self::QUARANTINE_CAP );
        }

        update_option( self::QUARANTINE_OPTION, $queue, false );
    }

    /**
     * Fire a non-blocking reward event to the Game Service.
     *
     * Per Event Contract v0.1: events are droppable. If the Game Service URL
     * is not configured or the request fails, this is silent.
     *
     * @param array<string,mixed> $event
     */
    private function fire_reward_event( array $event ): void {
        $game_service_url = defined( 'SPARXSTAR_GAME_SERVICE_URL' )
            ? rtrim( (string) constant( 'SPARXSTAR_GAME_SERVICE_URL' ), '/' )
            : '';

        if ( '' === $game_service_url ) {
            return;
        }

        wp_remote_post(
            $game_service_url . '/events/batch',
            array(
                'timeout'     => 1,
                'blocking'    => false,
                'body'        => wp_json_encode( array( 'events' => array( $event ) ) ),
                'headers'     => array( 'Content-Type' => 'application/json' ),
                'data_format' => 'body',
            )
        );
    }

}
