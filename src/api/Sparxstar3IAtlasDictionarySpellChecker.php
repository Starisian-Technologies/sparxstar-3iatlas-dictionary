<?php

declare(strict_types=1);

/**
 * Batch spell checker for the 3iAtlas Dictionary.
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

    /** @var string REST namespace shared by dictionary API routes. */
    private const REST_NAMESPACE = 'sparxstar/v1/dictionary';
    /** @var string Dictionary custom post type slug. */
    private const CPT            = 'aiwa-cpt-dictionary';
    /** @var int Hard cap for words validated per request. */
    private const MAX_WORDS      = 100;
    /** @var int Public request budget per rate-limit window. */
    private const RATE_LIMIT     = 100;
    /** @var int Rate-limit window size in seconds (15 minutes). */
    private const RATE_WINDOW    = 900;

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
     * Validate a word list and provide exact-match validity plus suggestions.
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

        $body = $request->get_json_params();

        if ( ! is_array( $body ) ) {
            return new \WP_Error( 'invalid_payload', 'Invalid JSON payload.', array( 'status' => 400 ) );
        }

        $lang  = sanitize_text_field( (string) ( $body['lang'] ?? '' ) );
        $words = $body['words'] ?? null;

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
                $exact = $this->find_exact_word_post( $word, $lang );
                $valid = $exact instanceof \WP_Post;

                if ( $valid && '' !== $lang ) {
                    $lang_terms = wp_get_object_terms( $exact->ID, 'starmus_tax_language', array( 'fields' => 'slugs' ) );
                    $valid      = ! is_wp_error( $lang_terms ) && in_array( $lang, $lang_terms, true );
                }

                $suggestions = array();

                if ( ! $valid ) {
                    $fuzzy_args = array(
                        'post_type'      => self::CPT,
                        'post_status'    => 'publish',
                        'posts_per_page' => 5,
                        's'              => $word,
                    );

                    if ( '' !== $lang ) {
                        $fuzzy_args['tax_query'] = array(
                            array(
                                'taxonomy' => 'starmus_tax_language',
                                'field'    => 'slug',
                                'terms'    => $lang,
                            ),
                        );
                    }

                    $fuzzy       = get_posts( $fuzzy_args );
                    $suggestions = array_map(
                        static fn( \WP_Post $post ): string => $post->post_title,
                        $fuzzy
                    );
                }

                $checked_words[ $word ] = array(
                    'valid'       => $valid,
                    'suggestions' => $suggestions,
                );
            }

            $results[] = array(
                'word'        => $word,
                'valid'       => $checked_words[ $word ]['valid'],
                'suggestions' => $checked_words[ $word ]['suggestions'],
            );
        }

        return new \WP_REST_Response(
            array(
                'success' => true,
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
     * Find a single published dictionary post that exactly matches a word.
     */
    private function find_exact_word_post( string $word, string $lang ): ?\WP_Post {
        global $wpdb;

        if ( ! $wpdb instanceof \wpdb ) {
            return null;
        }

        if ( '' === $lang ) {
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
        } else {
            $query = $wpdb->prepare(
                "SELECT posts.ID
                FROM {$wpdb->posts} posts
                INNER JOIN {$wpdb->term_relationships} term_relationships
                    ON posts.ID = term_relationships.object_id
                INNER JOIN {$wpdb->term_taxonomy} term_taxonomy
                    ON term_relationships.term_taxonomy_id = term_taxonomy.term_taxonomy_id
                INNER JOIN {$wpdb->terms} terms
                    ON term_taxonomy.term_id = terms.term_id
                WHERE posts.post_type = %s
                    AND posts.post_status = %s
                    AND posts.post_title = %s
                    AND term_taxonomy.taxonomy = %s
                    AND terms.slug = %s
                ORDER BY posts.ID ASC
                LIMIT 1",
                self::CPT,
                'publish',
                $word,
                'starmus_tax_language',
                $lang
            );
        }

        $post_id = (int) $wpdb->get_var( $query );
        if ( $post_id <= 0 ) {
            return null;
        }

        $post = get_post( $post_id );
        return $post instanceof \WP_Post ? $post : null;
    }
}
