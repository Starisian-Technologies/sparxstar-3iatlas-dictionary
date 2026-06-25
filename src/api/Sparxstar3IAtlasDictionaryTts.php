<?php

declare(strict_types=1);

/**
 * Server-side Twi TTS endpoint using the Kasanoma Piper model.
 *
 * Route: GET /wp-json/sparxstar/v1/dictionary/pronounce?word=<headword>
 *
 * Returns audio/wav binary. Synthesis runs once per unique headword; subsequent
 * requests are served from a file cache (default: wp-content/uploads/sparxstar-tts-cache/).
 *
 * Required wp-config.php constants:
 *   SPARX_3IATLAS_PIPER_BINARY  — absolute path to the standalone Piper binary
 *   SPARX_3IATLAS_PIPER_MODEL   — absolute path to the Kasanoma .onnx model file
 *
 * Optional wp-config.php constants:
 *   SPARX_3IATLAS_TTS_CACHE_DIR — absolute path to the wav cache directory
 *                                  (default: {wp-content/uploads}/sparxstar-tts-cache/)
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

final class Sparxstar3IAtlasDictionaryTts {

    /** REST namespace shared with the main API class. */
    private const REST_NAMESPACE = 'sparxstar/v1/dictionary';

    /** Maximum headword length accepted (bytes). Anything longer is rejected. */
    private const MAX_WORD_BYTES = 300;

    /** Piper synthesis timeout in seconds. */
    private const SYNTHESIS_TIMEOUT = 30;

    /** Cache-Control max-age for served wav responses (7 days). */
    private const CACHE_MAX_AGE = 604800;

    /** Pending wav bytes to emit via serve_wav_response(). */
    /** Pending wav bytes to emit via serve_wav_response(). */
    private ?string $pending_wav = null;

    /** Whether the pending wav was served from cache (for X-TTS-Cache header). */
    private bool $pending_cached = false;

    public function register_hooks(): void {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function register_rest_routes(): void {
        register_rest_route(
            self::REST_NAMESPACE,
            '/pronounce',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'handle_pronounce' ),
                'permission_callback' => array( $this, 'permission_browse' ),
                'args'                => array(
                    'word' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => array( $this, 'sanitize_word' ),
                        'validate_callback' => array( $this, 'validate_word' ),
                    ),
                ),
            )
        );
    }

    /**
     * Permission callback: ephemeral page token or API key required (browse scope).
     */
    public function permission_browse( \WP_REST_Request $request ): bool|\WP_Error {
        $resolver = new DictionaryAuthResolver();
        $result   = $resolver->resolve( $request );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $request->set_param( '_auth_context', $result );
        return true;
    }

    /**
     * Strips tags, trims, and enforces UTF-8 on the headword.
     */
    public function sanitize_word( string $value ): string {
        return mb_substr( trim( wp_strip_all_tags( $value ) ), 0, self::MAX_WORD_BYTES, 'UTF-8' );
    }

    /**
     * Rejects empty headwords and overly long inputs.
     */
    public function validate_word( mixed $value ): bool|\WP_Error {
        $s = (string) $value;
        if ( '' === trim( $s ) ) {
            return new \WP_Error( 'invalid_word', 'word parameter must not be empty', array( 'status' => 400 ) );
        }
        if ( strlen( $s ) > self::MAX_WORD_BYTES ) {
            return new \WP_Error( 'word_too_long', 'word parameter exceeds maximum length', array( 'status' => 400 ) );
        }
        return true;
    }

    /**
     * Handles GET /pronounce?word=<headword>.
     *
     * Checks the file cache first; synthesizes via Piper on miss; returns audio/wav.
     */
    public function handle_pronounce( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $word = (string) $request->get_param( 'word' );

        // --- Resolve configuration ---
        $config_error = $this->check_config();
        if ( is_wp_error( $config_error ) ) {
            return $config_error;
        }

        $piper_binary = (string) constant( 'SPARX_3IATLAS_PIPER_BINARY' );
        $model_path   = (string) constant( 'SPARX_3IATLAS_PIPER_MODEL' );
        $cache_dir    = $this->resolve_cache_dir();

        // --- Ensure cache directory exists ---
        if ( ! wp_mkdir_p( $cache_dir ) ) {
            return new \WP_Error(
                'tts_cache_dir',
                'TTS cache directory could not be created: ' . $cache_dir,
                array( 'status' => 500 )
            );
        }

        // --- Cache key: SHA-256 of the exact headword (case-sensitive, preserving diacritics) ---
        $cache_key  = hash( 'sha256', $word );
        $cache_file = trailingslashit( $cache_dir ) . $cache_key . '.wav';

        // --- Cache hit ---
        if ( file_exists( $cache_file ) && filesize( $cache_file ) > 0 ) {
            $wav = file_get_contents( $cache_file );
            if ( false !== $wav ) {
                return $this->queue_wav_response( $wav, true );
            }
        }

        // --- Cache miss: synthesize ---
        $wav = $this->synthesize( $word, $piper_binary, $model_path, $cache_file );
        if ( is_wp_error( $wav ) ) {
            return $wav;
        }

        return $this->queue_wav_response( $wav, false );
    }

    // -------------------------------------------------------------------------
    // Private: synthesis
    // -------------------------------------------------------------------------

    /**
     * Runs the Piper binary, writing output directly to $cache_file.
     * Uses proc_open with an array command to avoid any shell interpretation.
     *
     * @param string $word        UTF-8 headword; piped to Piper's stdin.
     * @param string $binary      Absolute path to the piper executable.
     * @param string $model       Absolute path to the .onnx model file.
     * @param string $cache_file  Destination wav file path (written by Piper).
     * @return string|\WP_Error   Raw wav bytes on success; WP_Error on failure.
     */
    private function synthesize(
        string $word,
        string $binary,
        string $model,
        string $cache_file
    ): string|\WP_Error {
        if ( ! is_executable( $binary ) ) {
            return new \WP_Error(
                'piper_not_executable',
                'Piper binary is not executable: ' . $binary,
                array( 'status' => 503 )
            );
        }

        if ( ! file_exists( $model ) ) {
            return new \WP_Error(
                'piper_model_missing',
                'Piper model file not found: ' . $model,
                array( 'status' => 503 )
            );
        }

        // Piper also requires a matching <model>.onnx.json config alongside the .onnx.
        $model_json = $model . '.json';
        if ( ! file_exists( $model_json ) ) {
            return new \WP_Error(
                'piper_model_json_missing',
                'Piper model config not found: ' . $model_json,
                array( 'status' => 503 )
            );
        }

        // Array-form proc_open: no shell is involved, arguments are passed verbatim.
        $cmd = array(
            $binary,
            '--model',
            $model,
            '--output_file',
            $cache_file,
        );

        $descriptors = array(
            0 => array( 'pipe', 'r' ), // stdin
            1 => array( 'pipe', 'w' ), // stdout (unused with --output_file)
            2 => array( 'pipe', 'w' ), // stderr (error detection)
        );

        $pipes   = array();
        $process = proc_open( $cmd, $descriptors, $pipes );

        if ( ! is_resource( $process ) ) {
            return new \WP_Error( 'piper_start_failed', 'Could not start Piper process', array( 'status' => 503 ) );
        }

        // Feed the headword to Piper's stdin (no trailing newline needed).
        fwrite( $pipes[0], $word );
        fclose( $pipes[0] );

        // Read stdout (usually empty with --output_file) and stderr under a timeout.
        stream_set_timeout( $pipes[1], self::SYNTHESIS_TIMEOUT );
        stream_set_timeout( $pipes[2], self::SYNTHESIS_TIMEOUT );
        fclose( $pipes[1] );
        $stderr = stream_get_contents( $pipes[2] );
        fclose( $pipes[2] );

        $exit_code = proc_close( $process );

        if ( 0 !== $exit_code ) {
            // Clean up any partial output Piper may have written.
            if ( file_exists( $cache_file ) ) {
                @unlink( $cache_file );
            }
            return new \WP_Error(
                'piper_synthesis_failed',
                sprintf( 'Piper exited with code %d.', $exit_code ),
                array( 'status' => 502 )
            );
        }

        if ( ! file_exists( $cache_file ) || filesize( $cache_file ) === 0 ) {
            return new \WP_Error( 'piper_empty_output', 'Piper produced no audio output.', array( 'status' => 502 ) );
        }

        $wav = file_get_contents( $cache_file );
        if ( false === $wav ) {
            return new \WP_Error( 'tts_read_error', 'Could not read synthesized audio file.', array( 'status' => 500 ) );
        }

        return $wav;
    }

    // -------------------------------------------------------------------------
    // Private: binary response helpers
    // -------------------------------------------------------------------------

    /**
     * Stores $wav and registers the rest_pre_serve_request filter so WordPress
     * emits audio/wav bytes instead of JSON-encoding a null body.
     *
     * @param string $wav    Raw wav bytes.
     * @param bool   $cached Whether this was a cache hit (surfaced in response header).
     */
    private function queue_wav_response( string $wav, bool $cached ): \WP_REST_Response {
        $this->pending_wav    = $wav;
        $this->pending_cached = $cached;
        add_filter( 'rest_pre_serve_request', array( $this, 'serve_wav_response' ), 10, 3 );
        return new \WP_REST_Response( null, 200 );
    }

    /**
     * rest_pre_serve_request filter — outputs wav bytes and signals that the
     * request has been fully served.
     *
     * @param bool              $served  Whether the request has already been served.
     * @param \WP_HTTP_Response $result  The response object (unused).
     * @param \WP_REST_Request  $request The current request (unused).
     * @return bool Always true (request is now served).
     */
    public function serve_wav_response( bool $served, \WP_HTTP_Response $result, \WP_REST_Request $request ): bool {
        if ( null === $this->pending_wav ) {
            return $served;
        }

        $wav    = $this->pending_wav;
        $cached = $this->pending_cached ?? false;

        $this->pending_wav    = null;
        $this->pending_cached = null;

        remove_filter( 'rest_pre_serve_request', array( $this, 'serve_wav_response' ), 10 );

        status_header( 200 );
        header( 'Content-Type: audio/wav' );
        header( 'Content-Length: ' . strlen( $wav ) );
        header( 'Cache-Control: public, max-age=' . self::CACHE_MAX_AGE );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-TTS-Cache: ' . ( $cached ? 'HIT' : 'MISS' ) );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $wav;

        return true;
    }

    // -------------------------------------------------------------------------
    // Private: configuration helpers
    // -------------------------------------------------------------------------

    /**
     * Verifies the required wp-config constants are set.
     *
     * @return null|\WP_Error Null if configuration is valid.
     */
    private function check_config(): ?\WP_Error {
        if ( ! defined( 'SPARX_3IATLAS_PIPER_BINARY' ) || '' === (string) constant( 'SPARX_3IATLAS_PIPER_BINARY' ) ) {
            return new \WP_Error(
                'tts_not_configured',
                'SPARX_3IATLAS_PIPER_BINARY is not defined in wp-config.php.',
                array( 'status' => 503 )
            );
        }
        if ( ! defined( 'SPARX_3IATLAS_PIPER_MODEL' ) || '' === (string) constant( 'SPARX_3IATLAS_PIPER_MODEL' ) ) {
            return new \WP_Error(
                'tts_not_configured',
                'SPARX_3IATLAS_PIPER_MODEL is not defined in wp-config.php.',
                array( 'status' => 503 )
            );
        }
        return null;
    }

    /**
     * Returns the wav cache directory, preferring the SPARX_3IATLAS_TTS_CACHE_DIR
     * constant and falling back to {uploads}/sparxstar-tts-cache/.
     */
    private function resolve_cache_dir(): string {
        if ( defined( 'SPARX_3IATLAS_TTS_CACHE_DIR' ) && '' !== (string) constant( 'SPARX_3IATLAS_TTS_CACHE_DIR' ) ) {
            return rtrim( (string) constant( 'SPARX_3IATLAS_TTS_CACHE_DIR' ), '/\\' );
        }
        $uploads = wp_upload_dir();
        return rtrim( $uploads['basedir'], '/\\' ) . '/sparxstar-tts-cache';
    }
}
