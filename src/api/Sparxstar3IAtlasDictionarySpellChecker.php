<?php

declare(strict_types=1);

/**
 * Batch spell checker for the 3iAtlas Dictionary.
 *
 * Validity is a union across the ENTIRE multilingual corpus — a word is valid
 * if it exists in ANY language the dictionary holds, not just the caller's
 * declared/primary language. `lang_source` is a RANKING SIGNAL ONLY: at equal
 * edit distance, suggestions in the caller's declared language are ranked
 * ahead of suggestions from other languages. It must never be reintroduced as
 * a validity filter or a query scope on this endpoint — doing so is a product
 * decision that needs to be made explicitly again, not silently reverted to.
 *
 * Suggestion-only: this endpoint never autocorrects. It returns ranked
 * candidates, each carrying its source language as metadata; the caller
 * decides what (if anything) to do with them.
 *
 * Found-and-fixed (this pass): the request body field was previously read as
 * `lang`, while every client and the documented contract used `lang_source` —
 * meaning language scoping silently never engaged in production (the value
 * was always empty string). Fixed to read `lang_source`, now repurposed as
 * described above.
 *
 * @package Starisian\Sparxstar\IAtlas\api
 * @license Starisian Technologies Proprietary License (STPL)
 */

namespace Starisian\Sparxstar\IAtlas\api;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

final class Sparxstar3IAtlasDictionarySpellChecker {

    use Sparxstar3IAtlasRateLimitTrait;

    /** REST namespace shared by dictionary API routes. */
    private const REST_NAMESPACE = 'sparxstar/v1/dictionary';
    /** Dictionary custom post type slug. */
    private const CPT = 'aiwa-cpt-dictionary';
    /** Hard cap for words validated per request. */
    private const MAX_WORDS = 100;
    /** Max fuzzy suggestions returned per invalid word. */
    private const MAX_SUGGESTIONS = 5;
    /**
     * Candidate pool size pulled from WordPress's native search before
     * edit-distance re-ranking. Kept cheap on purpose: candidate recall is
     * bounded by whatever `s` surfaces as a match at all, not by corpus size.
     */
    private const FUZZY_CANDIDATE_POOL = 40;
    /** Public request budget per rate-limit window. */
    private const RATE_LIMIT = 100;
    /** Rate-limit window size in seconds (15 minutes). */
    private const RATE_WINDOW = 900;

    /**
     * Register WordPress hooks for spell-check route initialization.
     */
    public function register_hooks(): void {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Register the public spell-checking endpoint.
     */
    public function register_rest_routes(): void {
        register_rest_route(
            self::REST_NAMESPACE,
            '/spell',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_spell' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Validate a word list and provide corpus-wide validity plus ranked,
     * language-tagged suggestions.
     */
    public function handle_spell( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        // TODO: Replace with Helios token introspection when available.
        if ( ! $this->check_rate_limit() ) {
            return new \WP_Error(
                'rate_limited',
                'Too many requests. Retry after 15 minutes.',
                array(
                    'status'  => 429,
                    'headers' => array(
                        'Retry-After' => (string) self::RATE_WINDOW,
                    ),
                )
            );
        }

        global $wpdb;

        if ( ! $wpdb instanceof \wpdb ) {
            return new \WP_Error(
                'database_unavailable',
                'Dictionary service is temporarily unavailable.',
                array( 'status' => 503 )
            );
        }

        $body = $request->get_json_params();

        if ( ! is_array( $body ) ) {
            return new \WP_Error( 'invalid_payload', 'Invalid JSON payload.', array( 'status' => 400 ) );
        }

        // `lang_source` is a ranking signal only — see class docblock. It is
        // NEVER used to filter/scope which entries are considered valid or
        // which candidates are eligible as suggestions.
        $lang_source = sanitize_text_field( (string) ( $body['lang_source'] ?? '' ) );
        $words       = $body['words'] ?? null;

        if ( ! is_array( $words ) || empty( $words ) ) {
            return new \WP_Error( 'invalid_payload', 'words must be a non-empty array.', array( 'status' => 400 ) );
        }

        $normalized_words = array();

        foreach ( array_slice( $words, 0, self::MAX_WORDS ) as $word ) {
            if ( ! is_scalar( $word ) ) {
                return new \WP_Error( 'invalid_payload', 'Each item in words must be a scalar value.', array( 'status' => 400 ) );
            }

            $normalized_words[] = sanitize_text_field( (string) $word );
        }

        $results       = array();
        $checked_words = array();

        foreach ( $normalized_words as $word ) {
            $word = trim( $word );
            if ( '' === $word ) {
                continue;
            }

            if ( ! isset( $checked_words[ $word ] ) ) {
                // Exact match is corpus-wide — no language filter. See class docblock.
                $exact    = $this->find_exact_word_post( $word );
                $valid    = $exact instanceof \WP_Post;
                $language = $valid ? $this->language_slug_for_post( $exact->ID ) : '';

                $suggestions = $valid ? array() : $this->find_fuzzy_suggestions( $word, $lang_source );

                $checked_words[ $word ] = array(
                    'valid'       => $valid,
                    'language'    => $language,
                    'suggestions' => $suggestions,
                );
            }

            $results[] = array(
                'word'        => $word,
                'valid'       => $checked_words[ $word ]['valid'],
                'language'    => $checked_words[ $word ]['language'],
                'suggestions' => $checked_words[ $word ]['suggestions'],
            );
        }

        return new \WP_REST_Response(
            array(
                'success' => true,
                'results' => $results,
                'data'    => array(
                    'results' => $results,
                ),
                'meta'    => array(
                    'total'    => count( $results ),
                    'page'     => 1,
                    'per_page' => count( $results ),
                ),
            ),
            200
        );
    }

    /**
     * Find a single published dictionary post that exactly matches a word,
     * regardless of language. Validity is a corpus-wide union — see class
     * docblock. Do not reintroduce a taxonomy filter here.
     */
    private function find_exact_word_post( string $word ): ?\WP_Post {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT ID
            FROM {$wpdb->posts}
            WHERE post_type = %s
                AND post_status = %s
                AND post_title = %s
            ORDER BY ID ASC
            LIMIT 1",
            self::CPT,
            'publish',
            $word
        );

        $post_id = (int) $wpdb->get_var( $query );
        if ( $post_id <= 0 ) {
            return null;
        }

        $post = get_post( $post_id );
        return $post instanceof \WP_Post ? $post : null;
    }

    /**
     * Resolve the source-language slug for a single dictionary post, for
     * attaching as metadata on a matched word or a suggestion candidate.
     */
    private function language_slug_for_post( int $post_id ): string {
        // get_the_terms() (unlike wp_get_object_terms()) is backed by WordPress's
        // object cache — this is called once per uniquely-valid word in a request
        // (up to MAX_WORDS), so caching avoids up to 100 uncached direct queries.
        $lang_terms = get_the_terms( $post_id, 'starmus_tax_language' );
        if ( is_wp_error( $lang_terms ) || empty( $lang_terms ) ) {
            return '';
        }
        $first_term = reset( $lang_terms );
        return $first_term instanceof \WP_Term ? $first_term->slug : '';
    }

    /**
     * Find and rank fuzzy suggestions for a misspelled word across the full
     * multilingual corpus.
     *
     * Candidate generation stays cheap regardless of corpus size: WordPress's
     * native `s` search narrows the corpus to self::FUZZY_CANDIDATE_POOL
     * likely matches (no language filter), and those candidates are then
     * re-ranked by real edit distance. Recall is bounded by whatever WP's
     * search surfaces as a candidate at all — this is a deliberate v1
     * trade-off for a small, limited-release corpus, not a precomputed
     * full-corpus index.
     *
     * Ranking: edit distance ascending, then — at equal distance — candidates
     * whose language matches `$lang_source` sort first (ranking tie-break
     * only, never a filter), then post ID ascending for determinism.
     *
     * @return array<int, array{word:string,language:string,distance:int,frequency:null}>
     */
    private function find_fuzzy_suggestions( string $word, string $lang_source ): array {
        // Length guard: a 1-character `s` search degrades to a near-universal
        // `LIKE '%x%'` match (DB load), and Levenshtein is O(n*m) — an
        // unbounded-length word is a CPU-exhaustion vector. Neither case
        // yields a useful suggestion anyway, so skip both cheaply.
        $length = mb_strlen( $word, 'UTF-8' );
        if ( $length < 2 || $length > 50 ) {
            return array();
        }

        $fuzzy = get_posts(
            array(
                'post_type'      => self::CPT,
                'post_status'    => 'publish',
                'posts_per_page' => self::FUZZY_CANDIDATE_POOL,
                's'              => $word,
            )
        );

        if ( empty( $fuzzy ) ) {
            return array();
        }

        $post_ids = wp_list_pluck( $fuzzy, 'ID' );
        $lang_map = array();

        $terms = wp_get_object_terms( $post_ids, 'starmus_tax_language', array( 'fields' => 'all_with_object_id' ) );
        if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
            foreach ( $terms as $term ) {
                if ( ! isset( $term->object_id ) ) {
                    continue;
                }
                $object_id = (int) $term->object_id;
                if ( ! isset( $lang_map[ $object_id ] ) ) {
                    $lang_map[ $object_id ] = (string) $term->slug;
                }
            }
        }

        $needle     = mb_strtolower( $word, 'UTF-8' );
        $candidates = array();

        foreach ( $fuzzy as $post ) {
            if ( ! $post instanceof \WP_Post ) {
                continue;
            }

            $candidates[] = array(
                'word'      => $post->post_title,
                'language'  => $lang_map[ $post->ID ] ?? '',
                'distance'  => self::utf8_levenshtein( $needle, mb_strtolower( $post->post_title, 'UTF-8' ) ),
                'frequency' => null,
                'post_id'   => $post->ID,
            );
        }

        usort(
            $candidates,
            static function ( array $a, array $b ) use ( $lang_source ): int {
                if ( $a['distance'] !== $b['distance'] ) {
                    return $a['distance'] <=> $b['distance'];
                }

                $a_is_primary = ( '' !== $lang_source && $lang_source === $a['language'] ) ? 0 : 1;
                $b_is_primary = ( '' !== $lang_source && $lang_source === $b['language'] ) ? 0 : 1;
                if ( $a_is_primary !== $b_is_primary ) {
                    return $a_is_primary <=> $b_is_primary;
                }

                return $a['post_id'] <=> $b['post_id'];
            }
        );

        $top = array_slice( $candidates, 0, self::MAX_SUGGESTIONS );

        return array_map(
            static function ( array $candidate ): array {
                unset( $candidate['post_id'] );
                return $candidate;
            },
            $top
        );
    }

    /**
     * UTF-8-safe Levenshtein edit distance.
     *
     * PHP's built-in levenshtein() operates byte-wise and is explicitly
     * documented as not binary/multi-byte safe — it would miscount any
     * multi-byte character (e.g. Yorùbá's diacritics) as multiple edits.
     * That is unacceptable for a multilingual corpus, so distance is computed
     * here over an array of Unicode code points instead of raw bytes.
     */
    private static function utf8_levenshtein( string $a, string $b ): int {
        if ( $a === $b ) {
            return 0;
        }

        $a_chars = mb_str_split( $a, 1, 'UTF-8' );
        $b_chars = mb_str_split( $b, 1, 'UTF-8' );
        $a_len   = count( $a_chars );
        $b_len   = count( $b_chars );

        if ( 0 === $a_len ) {
            return $b_len;
        }
        if ( 0 === $b_len ) {
            return $a_len;
        }

        $previous_row = range( 0, $b_len );

        for ( $i = 0; $i < $a_len; $i++ ) {
            $current_row = array( $i + 1 );

            for ( $j = 0; $j < $b_len; $j++ ) {
                $insert_cost   = $current_row[ $j ] + 1;
                $delete_cost   = $previous_row[ $j + 1 ] + 1;
                $replace_cost  = $previous_row[ $j ] + ( $a_chars[ $i ] === $b_chars[ $j ] ? 0 : 1 );
                $current_row[] = min( $insert_cost, $delete_cost, $replace_cost );
            }

            $previous_row = $current_row;
        }

        return $previous_row[ $b_len ];
    }
}
