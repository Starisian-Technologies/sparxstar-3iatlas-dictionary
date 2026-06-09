<?php

declare(strict_types=1);

/**
 * REST API controller for the 3iAtlas Dictionary.
 *
 * @package Starisian\Sparxstar\IAtlas\api
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

namespace Starisian\Sparxstar\IAtlas\api;

use Starisian\Sparxstar\IAtlas\api\auth\ApiKeyAuth;
use Starisian\Sparxstar\IAtlas\api\auth\DictionaryAuthResolver;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

final class Sparxstar3IAtlasDictionaryRestApi {

    use Sparxstar3IAtlasRateLimitTrait;

    public const REST_NAMESPACE = 'sparxstar/v1/dictionary';
    private const CPT           = 'aiwa-cpt-dictionary';
    private const RATE_LIMIT    = 100;
    private const RATE_WINDOW   = 900;

    public function register_hooks(): void {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function register_rest_routes(): void {
        // Public endpoint — mints ephemeral page tokens.
        register_rest_route(
            self::REST_NAMESPACE,
            '/page-token',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'handle_page_token' ),
                'permission_callback' => '__return_true',
            )
        );

        // Browse-or-consumer endpoints — accept ephemeral token OR API key.
        $browse_routes = array(
            array( 'GET', '/lookup', 'handle_lookup' ),
            array( 'GET', '/search', 'handle_search' ),
            array( 'GET', '/languages', 'handle_languages' ),
            array( 'GET', '/domains', 'handle_domains' ),
            array( 'GET', '/game-set', 'handle_game_set' ),
            array( 'GET', '/word-of-day', 'handle_word_of_day' ),
        );

        foreach ( $browse_routes as $route ) {
            register_rest_route(
                self::REST_NAMESPACE,
                $route[1],
                array(
                    'methods'             => $route[0],
                    'callback'            => array( $this, $route[2] ),
                    'permission_callback' => array( $this, 'permission_browse_or_consumer' ),
                )
            );
        }

        // Consumer-only endpoint — requires API key; ephemeral tokens are rejected with 403.
        register_rest_route(
            self::REST_NAMESPACE,
            '/wordlist',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'handle_wordlist' ),
                'permission_callback' => array( $this, 'permission_consumer_only' ),
            )
        );

        /**
         * @deprecated June 2026 — retired per 3IATLAS-IDENTITY-AND-GAME-SERVICES-DECISION-v1.0 §6.2.
         * Progress sync moves to the SPARXSTAR Game Service (RLC engine). No client may be
         * built against this endpoint. Route removal is scheduled after the Game Service
         * intake is live. Do not extend, do not document publicly, do not remove yet.
         */
        register_rest_route(
            self::REST_NAMESPACE,
            '/progress/sync',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_progress_sync' ),
                'permission_callback' => array( $this, 'permission_helios' ),
            )
        );
    }

    /**
     * Permission callback: ephemeral token OR API key required.
     * Returns true on success, WP_Error (401/403/429) on failure.
     *
     * @param \WP_REST_Request $request The incoming request.
     * @return true|\WP_Error
     */
    public function permission_browse_or_consumer( \WP_REST_Request $request ): bool|\WP_Error {
        $resolver = new DictionaryAuthResolver();
        $result   = $resolver->resolve( $request );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Attach auth context so handlers can access credential type and per-key quota.
        $request->set_param( '_auth_context', $result );
        return true;
    }

    /**
     * Permission callback: API key ONLY.
     *
     * Calls ApiKeyAuth directly rather than through the composite resolver — the
     * resolver prefers a valid page token, which would cause /wordlist to return 403
     * even when a valid API key is also present in the request.
     *
     * @param \WP_REST_Request $request The incoming request.
     * @return true|\WP_Error
     */
    public function permission_consumer_only( \WP_REST_Request $request ): bool|\WP_Error {
        $has_api_key = '' !== trim( (string) $request->get_header( 'X-Api-Key' ) );

        if ( $has_api_key ) {
            $result = ( new ApiKeyAuth() )->resolve( $request );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            $request->set_param( '_auth_context', $result );
            return true;
        }

        if ( '' !== trim( (string) $request->get_header( 'X-Page-Token' ) ) ) {
            return new \WP_Error(
                'forbidden',
                __( 'This endpoint requires an API key. Ephemeral page tokens are not accepted here.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 403 )
            );
        }

        return new \WP_Error(
            'no_credentials',
            __( 'Authentication required. Provide X-Api-Key.', 'sparxstar-3iatlas-dictionary' ),
            array( 'status' => 401 )
        );
    }

    public function permission_open(): bool {
        return true;
    }

    /**
     * Mint and return a new ephemeral page token.
     *
     * Public endpoint with same-origin referer check and per-IP rate limiting.
     *
     * @param \WP_REST_Request $request The incoming request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_page_token( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        // Same-origin Referer check. Empty Referer is intentionally allowed — direct API
        // calls and privacy-protected browsers omit it; the IP rate limit is the backstop.
        $referer = trim( (string) $request->get_header( 'Referer' ) );
        if ( '' !== $referer ) {
            $referer_host  = (string) parse_url( $referer, PHP_URL_HOST );
            $site_host     = (string) parse_url( site_url(), PHP_URL_HOST );
            if ( '' !== $referer_host && $referer_host !== $site_host ) {
                return new \WP_Error(
                    'forbidden',
                    __( 'Cross-origin page token requests are not allowed.', 'sparxstar-3iatlas-dictionary' ),
                    array( 'status' => 403 )
                );
            }
        }

        // Per-IP rate limit.
        if ( ! $this->check_rate_limit() ) {
            return $this->rate_limit_error();
        }

        if ( ! defined( 'SPARXSTAR_DICT_PAGE_SECRET' ) ) {
            return new \WP_Error(
                'configuration_error',
                __( 'SPARXSTAR_DICT_PAGE_SECRET is not defined. Please add it to wp-config.php.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 500 )
            );
        }

        $token     = $this->mint_ephemeral_token();
        $expires   = time() + 3600;

        $response = new \WP_REST_Response(
            array(
                'success' => true,
                'data'    => array(
                    'token'      => $token,
                    'expires_at' => $expires,
                ),
                'meta'    => array(),
            ),
            200
        );
        $response->header( 'Cache-Control', 'no-store' );
        return $response;
    }

    /**
     * Mint an HMAC-SHA256 ephemeral page token.
     *
     * Format: base64url(JSON {iat,exp,scope}) . '.' . hex(HMAC-SHA256)
     *
     * @return string The signed token string.
     */
    private function mint_ephemeral_token(): string {
        $now     = time();
        $payload = array(
            'iat'   => $now,
            'exp'   => $now + 3600,
            'scope' => 'browse',
        );

        $encoded_payload = rtrim( strtr( base64_encode( (string) wp_json_encode( $payload ) ), '+/', '-_' ), '=' );
        $secret          = (string) constant( 'SPARXSTAR_DICT_PAGE_SECRET' );
        $signature       = hash_hmac( 'sha256', $encoded_payload, $secret );

        return $encoded_payload . '.' . $signature;
    }

    private function parse_bearer_token( string $authorization_header ): ?string {
        $authorization_header = trim( $authorization_header );

        if ( '' === $authorization_header ) {
            return null;
        }

        if ( 1 !== preg_match( '/^Bearer\s+(\S+)$/i', $authorization_header, $matches ) ) {
            return null;
        }

        return $matches[1];
    }

    public function permission_helios( \WP_REST_Request $request ): bool {
        // TODO: Replace with Helios token introspection when available.
        $auth  = $request->get_header( 'Authorization' );
        $token = $this->parse_bearer_token( $auth );

        // Temporary guard: require a bearer token and an elevated WP capability.
        return null !== $token && is_user_logged_in() && current_user_can( 'edit_posts' );
    }

    private function rate_limit_error(): \WP_Error {
        return new \WP_Error(
            'rate_limited',
            'Too many requests. Retry after 15 minutes.',
            array(
                'status'  => 429,
                'headers' => array( 'Retry-After' => (string) self::RATE_WINDOW ),
            )
        );
    }

    private function cached_response( array $data, int $max_age = 3600 ): \WP_REST_Response {
        $data['meta'] = $data['meta'] ?? array();
        $response     = new \WP_REST_Response( $data, 200 );
        $response->header( 'Cache-Control', 'public, max-age=' . $max_age );
        $response->header( 'X-RateLimit-Remaining', (string) $this->get_rate_limit_remaining() );
        return $response;
    }

    private function if_none_match_contains( string $if_none_match, string $etag_value ): bool {
        $if_none_match = trim( $if_none_match );
        if ( '' === $if_none_match ) {
            return false;
        }

        if ( '*' === $if_none_match ) {
            return true;
        }

        $candidates = array_map( 'trim', explode( ',', $if_none_match ) );
        foreach ( $candidates as $candidate ) {
            if ( '' === $candidate ) {
                continue;
            }
            $normalized = preg_replace( '/^W\//', '', $candidate );
            if ( is_string( $normalized ) && $normalized === $etag_value ) {
                return true;
            }
        }

        return false;
    }

    private static function domain_code_from_slug( string $slug ): string {
        if ( 1 === preg_match( '/-([0-9]+(?:\.[0-9]+)*)$/', $slug, $matches ) ) {
            return $matches[1];
        }

        return '';
    }

    private function build_entry( int $post_id, bool $include_audio = true ): array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return array();
        }

        $lang_terms   = wp_get_object_terms( $post_id, 'starmus_tax_language', array( 'fields' => 'slugs' ) );
        $domain_terms = wp_get_object_terms( $post_id, 'aiwa_domain', array( 'fields' => 'names' ) );
        $pos_terms    = wp_get_object_terms( $post_id, 'starmus_part_of_speech', array( 'fields' => 'names' ) );

        $syn_posts = get_field( 'aiwa_synonyms', $post_id );
        $ant_posts = get_field( 'aiwa_antonyms', $post_id );
        $synonyms  = is_array( $syn_posts )
            ? array_map(
                static fn( $entry ): string => $entry instanceof \WP_Post ? $entry->post_title : '',
                $syn_posts
            )
            : array();
        $antonyms  = is_array( $ant_posts )
            ? array_map(
                static fn( $entry ): string => $entry instanceof \WP_Post ? $entry->post_title : '',
                $ant_posts
            )
            : array();
        $synonyms  = array_values( array_filter( $synonyms, static fn( string $value ): bool => '' !== $value ) );
        $antonyms  = array_values( array_filter( $antonyms, static fn( string $value ): bool => '' !== $value ) );

        $sentences_raw = get_field( 'aiwa_example_sentences', $post_id );
        $sentences     = array();
        if ( is_array( $sentences_raw ) ) {
            foreach ( $sentences_raw as $row ) {
                $sentences[] = array(
                    'sentence'       => (string) ( $row['aiwa_sentence_example'] ?? '' ),
                    'ipa'            => (string) ( $row['aiwa_sentence_ipa'] ?? '' ),
                    'phonetic'       => (string) ( $row['aiwa_sentence_phonetic'] ?? '' ),
                    'translation_en' => (string) ( $row['aiwa_sentence_english'] ?? '' ),
                    'translation_fr' => (string) ( $row['aiwa_sentence_french'] ?? '' ),
                );
            }
        }

        $entry = array(
            'uuid'              => (string) get_field( 'aiwa_entry_uuid', $post_id ),
            'headword'          => $post->post_title,
            'slug'              => $post->post_name,
            'definition'        => (string) get_field( 'aiwa_extract', $post_id ),
            'translation_en'    => (string) get_field( 'aiwa_translation_english', $post_id ),
            'translation_fr'    => (string) get_field( 'aiwa_translation_french', $post_id ),
            'ipa'               => (string) get_field( 'aiwa_ipa_pronunciation', $post_id ),
            'phonetic'          => (string) get_field( 'aiwa_phonetic', $post_id ),
            'part_of_speech'    => ! is_wp_error( $pos_terms ) && ! empty( $pos_terms ) ? $pos_terms[0] : '',
            'language'          => ! is_wp_error( $lang_terms ) && ! empty( $lang_terms ) ? $lang_terms[0] : '',
            'domain'            => ! is_wp_error( $domain_terms ) && ! empty( $domain_terms ) ? $domain_terms[0] : '',
            'origin'            => (string) get_field( 'aiwa_origin', $post_id ),
            'synonyms'          => $synonyms,
            'antonyms'          => $antonyms,
            'example_sentences' => $sentences,
        );

        if ( $include_audio ) {
            $audio              = get_field( 'aiwa_audio_file', $post_id );
            $entry['audio_url'] = is_array( $audio ) ? ( $audio['url'] ?? null ) : ( $audio ?: null );
        }

        return $entry;
    }

    public function handle_lookup( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        // TODO: Replace with Helios token introspection when available.
        if ( ! $this->check_rate_limit() ) {
            return $this->rate_limit_error();
        }

        $slug = sanitize_text_field( (string) ( $request->get_param( 'slug' ) ?? '' ) );
        $uuid = sanitize_text_field( (string) ( $request->get_param( 'uuid' ) ?? '' ) );

        if ( '' === $slug && '' === $uuid ) {
            return new \WP_Error( 'missing_param', 'slug or uuid is required.', array( 'status' => 400 ) );
        }

        if ( '' !== $slug ) {
            $post = get_page_by_path( $slug, OBJECT, self::CPT );
        } else {
            $posts = get_posts(
                array(
                    'post_type'      => self::CPT,
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'meta_query'     => array(
                        array(
                            'key'     => 'aiwa_entry_uuid',
                            'value'   => $uuid,
                            'compare' => '=',
                        ),
                    ),
                )
            );
            $post  = $posts[0] ?? null;
        }

        if ( ! $post instanceof \WP_Post ) {
            return new \WP_Error( 'not_found', 'Entry not found.', array( 'status' => 404 ) );
        }

        $include_audio = filter_var( $request->get_param( 'include_audio' ) ?? false, FILTER_VALIDATE_BOOLEAN );

        return $this->cached_response(
            array(
                'success' => true,
                'data'    => array( 'word' => $this->build_entry( $post->ID, $include_audio ) ),
                'meta'    => array(),
            )
        );
    }

    public function handle_search( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        // TODO: Replace with Helios token introspection when available.
        if ( ! $this->check_rate_limit() ) {
            return $this->rate_limit_error();
        }

        $q        = sanitize_text_field( (string) ( $request->get_param( 'q' ) ?? '' ) );
        $lang     = sanitize_text_field( (string) ( $request->get_param( 'lang_source' ) ?? '' ) );
        $per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ?? 20 ) ) );
        $page     = max( 1, absint( $request->get_param( 'page' ) ?? 1 ) );

        if ( wp_strlen( $q ) < 2 ) {
            return new \WP_Error( 'query_too_short', 'q must be at least 2 characters.', array( 'status' => 400 ) );
        }

        $args = array(
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            's'              => $q,
        );

        if ( '' !== $lang ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'starmus_tax_language',
                    'field'    => 'slug',
                    'terms'    => $lang,
                ),
            );
        }

        $query = new \WP_Query( $args );
        $items = array();

        foreach ( $query->posts as $post ) {
            if ( ! $post instanceof \WP_Post ) {
                continue;
            }
            $lang_terms = wp_get_object_terms( $post->ID, 'starmus_tax_language', array( 'fields' => 'slugs' ) );
            $items[]    = array(
                'uuid'           => (string) get_field( 'aiwa_entry_uuid', $post->ID ),
                'headword'       => $post->post_title,
                'slug'           => $post->post_name,
                'definition'     => (string) get_field( 'aiwa_extract', $post->ID ),
                'translation_en' => (string) get_field( 'aiwa_translation_english', $post->ID ),
                'ipa'            => (string) get_field( 'aiwa_ipa_pronunciation', $post->ID ),
                'language'       => ! is_wp_error( $lang_terms ) && ! empty( $lang_terms ) ? $lang_terms[0] : '',
            );
        }

        return $this->cached_response(
            array(
                'success' => true,
                'data'    => array( 'results' => $items ),
                'meta'    => array(
                    'total'    => (int) $query->found_posts,
                    'page'     => $page,
                    'per_page' => $per_page,
                ),
            )
        );
    }

    public function handle_wordlist( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        // TODO: Replace with Helios token introspection when available.
        if ( ! $this->check_rate_limit() ) {
            return $this->rate_limit_error();
        }

        $lang     = sanitize_text_field( (string) ( $request->get_param( 'lang_source' ) ?? '' ) );
        $per_page = min( 2000, max( 1, absint( $request->get_param( 'per_page' ) ?? 1000 ) ) );
        $page     = max( 1, absint( $request->get_param( 'page' ) ?? 1 ) );

        $args = array(
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'no_found_rows'  => false,
        );

        if ( '' !== $lang ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'starmus_tax_language',
                    'field'    => 'slug',
                    'terms'    => $lang,
                ),
            );
        }

        $query    = new \WP_Query( $args );
        $words    = array();
        $post_ids = array();

        foreach ( $query->posts as $post ) {
            if ( $post instanceof \WP_Post ) {
                $post_ids[] = $post->ID;
            }
        }

        $language_map = array();
        if ( ! empty( $post_ids ) ) {
            $language_terms = wp_get_object_terms( $post_ids, 'starmus_tax_language', array( 'fields' => 'all_with_object_id' ) );
            if ( ! is_wp_error( $language_terms ) && is_array( $language_terms ) ) {
                foreach ( $language_terms as $language_term ) {
                    if ( ! isset( $language_term->object_id ) || ! isset( $language_term->slug ) ) {
                        continue;
                    }
                    $object_id = (int) $language_term->object_id;
                    if ( ! isset( $language_map[ $object_id ] ) ) {
                        $language_map[ $object_id ] = (string) $language_term->slug;
                    }
                }
            }
        }

        foreach ( $query->posts as $post ) {
            if ( ! $post instanceof \WP_Post ) {
                continue;
            }

            $words[] = array(
                'headword' => $post->post_title,
                'slug'     => $post->post_name,
                'uuid'     => (string) get_post_meta( $post->ID, 'aiwa_entry_uuid', true ),
                'language' => $language_map[ $post->ID ] ?? '',
            );
        }

        $payload = array(
            'success' => true,
            'data'    => array( 'words' => $words ),
            'meta'    => array(
                'total'    => (int) $query->found_posts,
                'page'     => $page,
                'per_page' => $per_page,
            ),
        );

        $response = $this->cached_response( $payload, 3600 );
        $response->header( 'Cache-Control', 'private, no-cache' );

        $word_uuids    = array_column( $words, 'uuid' );
        $etag          = md5( $lang . ':' . $page . ':' . $per_page . ':' . (string) $query->found_posts . ':' . implode( ',', $word_uuids ) );
        $etag_value    = '"' . $etag . '"';
        $if_none_match = trim( (string) $request->get_header( 'If-None-Match' ) );
        if ( $this->if_none_match_contains( $if_none_match, $etag_value ) ) {
            $not_modified = new \WP_REST_Response( null, 304 );
            $not_modified->header( 'Cache-Control', 'private, no-store' );
            $not_modified->header( 'ETag', $etag_value );
            return $not_modified;
        }
        $response->header( 'ETag', $etag_value );

        return $response;
    }

    public function handle_languages( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        // TODO: Replace with Helios token introspection when available.
        if ( ! $this->check_rate_limit() ) {
            return $this->rate_limit_error();
        }

        $terms = get_terms(
            array(
                'taxonomy'   => 'starmus_tax_language',
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            )
        );

        if ( is_wp_error( $terms ) ) {
            return new \WP_Error( 'taxonomy_error', 'Failed to retrieve languages.', array( 'status' => 500 ) );
        }

        $languages = array_map(
            static fn( \WP_Term $term ): array => array(
                'slug'  => $term->slug,
                'name'  => $term->name,
                'count' => (int) $term->count,
            ),
            $terms
        );

        return $this->cached_response(
            array(
                'success' => true,
                'data'    => array( 'languages' => $languages ),
            ),
            604800
        );
    }

    public function handle_domains( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        // TODO: Replace with Helios token introspection when available.
        if ( ! $this->check_rate_limit() ) {
            return $this->rate_limit_error();
        }

        $lang    = sanitize_text_field( (string) ( $request->get_param( 'lang_source' ) ?? '' ) );
        $domains = array();

        if ( '' !== $lang ) {
            global $wpdb;
            $last_error_before = $wpdb->last_error;

            $query = $wpdb->prepare(
                "
                SELECT domain_terms.slug, domain_terms.name, COUNT(DISTINCT posts.ID) AS count
                FROM {$wpdb->posts} AS posts
                INNER JOIN {$wpdb->term_relationships} AS domain_relationships
                    ON domain_relationships.object_id = posts.ID
                INNER JOIN {$wpdb->term_taxonomy} AS domain_taxonomy
                    ON domain_taxonomy.term_taxonomy_id = domain_relationships.term_taxonomy_id
                    AND domain_taxonomy.taxonomy = %s
                INNER JOIN {$wpdb->terms} AS domain_terms
                    ON domain_terms.term_id = domain_taxonomy.term_id
                INNER JOIN {$wpdb->term_relationships} AS language_relationships
                    ON language_relationships.object_id = posts.ID
                INNER JOIN {$wpdb->term_taxonomy} AS language_taxonomy
                    ON language_taxonomy.term_taxonomy_id = language_relationships.term_taxonomy_id
                    AND language_taxonomy.taxonomy = %s
                INNER JOIN {$wpdb->terms} AS language_terms
                    ON language_terms.term_id = language_taxonomy.term_id
                WHERE posts.post_type = %s
                    AND posts.post_status = %s
                    AND language_terms.slug = %s
                GROUP BY domain_terms.term_id, domain_terms.slug, domain_terms.name
                ORDER BY domain_terms.name ASC
                ",
                'aiwa_domain',
                'starmus_tax_language',
                self::CPT,
                'publish',
                $lang
            );

            $term_rows = $wpdb->get_results( $query );

            if ( '' !== $wpdb->last_error && $wpdb->last_error !== $last_error_before ) {
                return new \WP_Error( 'taxonomy_error', 'Failed to retrieve domains.', array( 'status' => 500 ) );
            }

            $domains = array_map(
                static fn( object $term_row ): array => array(
                    'slug'  => (string) $term_row->slug,
                    'name'  => (string) $term_row->name,
                    'code'  => self::domain_code_from_slug( (string) $term_row->slug ),
                    'count' => (int) $term_row->count,
                ),
                is_array( $term_rows ) ? $term_rows : array()
            );
        } else {
            $terms = get_terms(
                array(
                    'taxonomy'   => 'aiwa_domain',
                    'hide_empty' => true,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                )
            );

            if ( is_wp_error( $terms ) ) {
                return new \WP_Error( 'taxonomy_error', 'Failed to retrieve domains.', array( 'status' => 500 ) );
            }

            $domains = array_map(
                static fn( \WP_Term $term ): array => array(
                    'slug'  => $term->slug,
                    'name'  => $term->name,
                    'code'  => self::domain_code_from_slug( $term->slug ),
                    'count' => (int) $term->count,
                ),
                $terms
            );
        }
        return $this->cached_response(
            array(
                'success' => true,
                'data'    => array( 'domains' => $domains ),
            )
        );
    }

    public function handle_game_set( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        // TODO: Replace with Helios token introspection when available.
        if ( ! $this->check_rate_limit() ) {
            return $this->rate_limit_error();
        }

        $lang          = sanitize_text_field( (string) ( $request->get_param( 'lang_source' ) ?? '' ) );
        $domain        = sanitize_text_field( (string) ( $request->get_param( 'domain' ) ?? '' ) );
        $limit         = min( 50, max( 1, absint( $request->get_param( 'limit' ) ?? 20 ) ) );
        $include_audio = filter_var( $request->get_param( 'include_audio' ), FILTER_VALIDATE_BOOLEAN );

        if ( '' === $lang ) {
            return new \WP_Error( 'missing_param', 'lang_source is required.', array( 'status' => 400 ) );
        }

        $tax_query = array(
            array(
                'taxonomy' => 'starmus_tax_language',
                'field'    => 'slug',
                'terms'    => $lang,
            ),
        );

        if ( '' !== $domain ) {
            $tax_query[]           = array(
                'taxonomy' => 'aiwa_domain',
                'field'    => 'slug',
                'terms'    => $domain,
            );
            $tax_query['relation'] = 'AND';
        }

        $seed = hash( 'sha256', gmdate( 'Y-m-d' ) . '|' . $lang . '|' . $domain );
        // Fetch extra candidates because entries missing required game fields are filtered out after query.
        $batch_size       = min( 200, $limit * 4 );
        $candidate_ids    = array();
        $total_candidates = 0;

        global $wpdb;

        if ( '' !== $domain ) {
            $count_sql = $wpdb->prepare(
                "SELECT COUNT(DISTINCT posts.ID)
                FROM {$wpdb->posts} posts
                INNER JOIN {$wpdb->term_relationships} rel_lang ON posts.ID = rel_lang.object_id
                INNER JOIN {$wpdb->term_taxonomy} tax_lang ON rel_lang.term_taxonomy_id = tax_lang.term_taxonomy_id
                INNER JOIN {$wpdb->terms} terms_lang ON tax_lang.term_id = terms_lang.term_id
                INNER JOIN {$wpdb->term_relationships} rel_domain ON posts.ID = rel_domain.object_id
                INNER JOIN {$wpdb->term_taxonomy} tax_domain ON rel_domain.term_taxonomy_id = tax_domain.term_taxonomy_id
                INNER JOIN {$wpdb->terms} terms_domain ON tax_domain.term_id = terms_domain.term_id
                WHERE posts.post_type = %s
                    AND posts.post_status = %s
                    AND tax_lang.taxonomy = %s
                    AND terms_lang.slug = %s
                    AND tax_domain.taxonomy = %s
                    AND terms_domain.slug = %s",
                self::CPT,
                'publish',
                'starmus_tax_language',
                $lang,
                'aiwa_domain',
                $domain
            );
        } else {
            $count_sql = $wpdb->prepare(
                "SELECT COUNT(DISTINCT posts.ID)
                FROM {$wpdb->posts} posts
                INNER JOIN {$wpdb->term_relationships} rel_lang ON posts.ID = rel_lang.object_id
                INNER JOIN {$wpdb->term_taxonomy} tax_lang ON rel_lang.term_taxonomy_id = tax_lang.term_taxonomy_id
                INNER JOIN {$wpdb->terms} terms_lang ON tax_lang.term_id = terms_lang.term_id
                WHERE posts.post_type = %s
                    AND posts.post_status = %s
                    AND tax_lang.taxonomy = %s
                    AND terms_lang.slug = %s",
                self::CPT,
                'publish',
                'starmus_tax_language',
                $lang
            );
        }

        $total_candidates = (int) $wpdb->get_var( $count_sql );

        if ( $total_candidates > 0 ) {
            // Use 8 hex chars (~32 bits) to keep deterministic offsets portable across PHP platforms.
            $start_offset = (int) ( hexdec( substr( $seed, 0, 8 ) ) % $total_candidates );

            $candidate_ids = get_posts(
                array(
                    'post_type'      => self::CPT,
                    'post_status'    => 'publish',
                    'posts_per_page' => $batch_size,
                    'offset'         => $start_offset,
                    'fields'         => 'ids',
                    'orderby'        => 'ID',
                    'order'          => 'ASC',
                    'tax_query'      => $tax_query,
                    'no_found_rows'  => true,
                )
            );

            if ( count( $candidate_ids ) < $batch_size && $start_offset > 0 ) {
                $remaining     = $batch_size - count( $candidate_ids );
                $candidate_ids = array_merge(
                    $candidate_ids,
                    get_posts(
                        array(
                            'post_type'      => self::CPT,
                            'post_status'    => 'publish',
                            'posts_per_page' => $remaining,
                            'offset'         => 0,
                            'fields'         => 'ids',
                            'orderby'        => 'ID',
                            'order'          => 'ASC',
                            'tax_query'      => $tax_query,
                            'no_found_rows'  => true,
                        )
                    )
                );
            }
        }

        $candidate_ids = array_values( array_unique( array_map( 'absint', $candidate_ids ) ) );
        $posts         = empty( $candidate_ids )
            ? array()
            : get_posts(
                array(
                    'post_type'      => self::CPT,
                    'post_status'    => 'publish',
                    'posts_per_page' => count( $candidate_ids ),
                    'post__in'       => $candidate_ids,
                    'orderby'        => 'post__in',
                    'no_found_rows'  => true,
                )
            );

        $words = array();
        foreach ( $posts as $post ) {
            if ( ! $post instanceof \WP_Post ) {
                continue;
            }

            if ( count( $words ) >= $limit ) {
                break;
            }

            $translation_en = (string) get_field( 'aiwa_translation_english', $post->ID );
            $ipa            = (string) get_field( 'aiwa_ipa_pronunciation', $post->ID );

            if ( '' === $post->post_title || '' === $translation_en || '' === $ipa ) {
                continue;
            }

            $lang_terms    = wp_get_object_terms( $post->ID, 'starmus_tax_language', array( 'fields' => 'slugs' ) );
            $domain_terms  = wp_get_object_terms( $post->ID, 'aiwa_domain', array( 'fields' => 'names' ) );
            $sentences_raw = get_field( 'aiwa_example_sentences', $post->ID );
            $example       = is_array( $sentences_raw ) && ! empty( $sentences_raw )
                ? array(
                    'sentence'       => (string) ( $sentences_raw[0]['aiwa_sentence_example'] ?? '' ),
                    'translation_en' => (string) ( $sentences_raw[0]['aiwa_sentence_english'] ?? '' ),
                )
                : null;

            $word = array(
                'uuid'           => (string) get_field( 'aiwa_entry_uuid', $post->ID ),
                'headword'       => $post->post_title,
                'ipa'            => $ipa,
                'phonetic'       => (string) get_field( 'aiwa_phonetic', $post->ID ),
                'translation_en' => $translation_en,
                'translation_fr' => (string) get_field( 'aiwa_translation_french', $post->ID ),
                'part_of_speech' => '',
                'domain'         => ! is_wp_error( $domain_terms ) && ! empty( $domain_terms ) ? $domain_terms[0] : '',
                'language'       => ! is_wp_error( $lang_terms ) && ! empty( $lang_terms ) ? $lang_terms[0] : '',
                'example'        => $example,
                'audio_url'      => null,
            );

            if ( $include_audio ) {
                $audio             = get_field( 'aiwa_audio_file', $post->ID );
                $word['audio_url'] = is_array( $audio ) ? ( $audio['url'] ?? null ) : ( $audio ?: null );
            }

            $words[] = $word;
        }

        $payload = array(
            'success' => true,
            'data'    => array( 'words' => $words ),
            'meta'    => array(
                'total'         => count( $words ),
                'lang_source'   => $lang,
                'domain'        => $domain,
                'include_audio' => $include_audio,
            ),
        );

        // The set is deterministic per calendar day for a given lang/domain, so it is safe to cache.
        $response      = $this->cached_response( $payload, 3600 );
        $word_uuids    = array_column( $words, 'uuid' );
        $etag          = md5( $seed . ':' . $limit . ':' . ( $include_audio ? '1' : '0' ) . ':' . implode( ',', $word_uuids ) );
        $etag_value    = '"' . $etag . '"';
        $if_none_match = trim( (string) $request->get_header( 'If-None-Match' ) );
        if ( $this->if_none_match_contains( $if_none_match, $etag_value ) ) {
            $not_modified = new \WP_REST_Response( null, 304 );
            $not_modified->header( 'Cache-Control', 'public, max-age=3600' );
            $not_modified->header( 'ETag', $etag_value );
            return $not_modified;
        }
        $response->header( 'ETag', $etag_value );

        return $response;
    }

    public function handle_word_of_day( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        // TODO: Replace with Helios token introspection when available.
        if ( ! $this->check_rate_limit() ) {
            return $this->rate_limit_error();
        }

        $today     = gmdate( 'Y-m-d' );
        $cache_key = 'sparx_3iatlas_dict_wod_' . $today;
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $this->cached_response(
                array(
                    'success' => true,
                    'data'    => array(
                        'word' => $cached,
                        'date' => $today,
                    ),
                ),
                3600
            );
        }

        $total = wp_count_posts( self::CPT )->publish ?? 0;
        if ( 0 === (int) $total ) {
            return new \WP_Error( 'no_entries', 'No dictionary entries available.', array( 'status' => 404 ) );
        }

        $hash   = hash( 'sha256', $today );
        $offset = (int) ( hexdec( substr( $hash, 0, 7 ) ) % (int) $total );

        $posts = get_posts(
            array(
                'post_type'      => self::CPT,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'offset'         => $offset,
                'orderby'        => 'ID',
                'order'          => 'ASC',
            )
        );

        if ( empty( $posts ) || ! $posts[0] instanceof \WP_Post ) {
            return new \WP_Error( 'not_found', 'Could not select word of the day.', array( 'status' => 404 ) );
        }

        $entry = $this->build_entry( $posts[0]->ID );
        set_transient( $cache_key, $entry, 3600 );

        return $this->cached_response(
            array(
                'success' => true,
                'data'    => array(
                    'word' => $entry,
                    'date' => $today,
                ),
            ),
            3600
        );
    }

    /**
     * @deprecated June 2026 — retired per 3IATLAS-IDENTITY-AND-GAME-SERVICES-DECISION-v1.0 §6.2.
     * Progress sync moves to the SPARXSTAR Game Service (RLC engine). No client may be
     * built against this endpoint. Route removal is scheduled after the Game Service
     * intake is live. Do not extend, do not document publicly, do not remove yet.
     *
     * @param \WP_REST_Request $request The incoming request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_progress_sync( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        // TODO: Replace with Helios token introspection when available.
        if ( ! $this->check_rate_limit() ) {
            return $this->rate_limit_error();
        }

        $body   = $request->get_json_params();
        $events = $body['events'] ?? null;

        if ( ! is_array( $events ) ) {
            return new \WP_Error( 'invalid_payload', 'events must be a JSON array.', array( 'status' => 400 ) );
        }

        $user_id    = get_current_user_id();
        $accepted   = 0;
        $failed     = 0;
        $duplicates = 0;

        // Idempotency ledger: keys are word_uuid|game|domain|type|ts. Retried batches are
        // skipped. Deduplication only applies when a timestamp is present — without one the
        // key is not specific enough to distinguish legitimate repeat events of the same type.
        // TODO: Replace with Helios-identified persistent ledger when token introspection lands.
        $seen_key = 'sparx_3iatlas_dict_sync_seen_' . $user_id;
        $seen     = get_transient( $seen_key );
        if ( ! is_array( $seen ) ) {
            $seen = array();
        }

        foreach ( $events as $event ) {
            if ( ! is_array( $event ) ) {
                ++$failed;
                continue;
            }

            $type    = sanitize_text_field( (string) ( $event['type'] ?? '' ) );
            $word_id = sanitize_text_field( (string) ( $event['word_uuid'] ?? '' ) );
            $game    = sanitize_text_field( (string) ( $event['game'] ?? '' ) );
            $domain  = sanitize_text_field( (string) ( $event['domain'] ?? '' ) );
            $ts      = sanitize_text_field( (string) ( $event['ts'] ?? '' ) );

            if ( '' === $type ) {
                ++$failed;
                continue;
            }

            $dedupe_key = $word_id . '|' . $game . '|' . $domain . '|' . $type . '|' . $ts;
            if ( '' !== $ts && isset( $seen[ $dedupe_key ] ) ) {
                ++$duplicates;
                continue;
            }

            switch ( $type ) {
                case 'aiwa_game_word_correct':
                    do_action( 'aiwa_game_word_correct', $user_id, $word_id, $game );
                    break;
                case 'aiwa_game_listen_write_correct':
                    do_action( 'aiwa_game_listen_write_correct', $user_id, $word_id );
                    break;
                case 'aiwa_game_session_complete':
                    do_action( 'aiwa_game_session_complete', $user_id, $domain );
                    break;
                case 'aiwa_game_domain_mastered':
                    do_action( 'aiwa_game_domain_mastered', $user_id, $domain );
                    break;
                case 'aiwa_game_streak_3':
                    do_action( 'aiwa_game_streak_3', $user_id );
                    break;
                case 'aiwa_game_new_word_practiced':
                    do_action( 'aiwa_game_new_word_practiced', $user_id, $word_id );
                    break;
                case 'aiwa_game_return_visit':
                    do_action( 'aiwa_game_return_visit', $user_id );
                    break;
                default:
                    ++$failed;
                    continue 2;
            }

            // Record only valid, processed events that carry a timestamp.
            if ( '' !== $ts ) {
                $seen[ $dedupe_key ] = 1;
            }
            ++$accepted;
        }

        // Cap the ledger so it cannot grow unbounded, then persist for a day to cover retries.
        if ( count( $seen ) > 2000 ) {
            $seen = array_slice( $seen, -2000, null, true );
        }
        set_transient( $seen_key, $seen, DAY_IN_SECONDS );

        return new \WP_REST_Response(
            array(
                'success' => true,
                'data'    => array(
                    // Point totals are awarded by myCred listeners; this controller does not compute them.
                    'xp_awarded'       => 0,
                    'gold_awarded'     => 0,
                    'events_processed' => $accepted,
                ),
                'meta'    => array(
                    'failed'     => $failed,
                    'duplicates' => $duplicates,
                ),
            ),
            200
        );
    }
}
