<?php
/**
 * Sparxstar IAtlas Auto Linker
 *
 * @package   Starisian\Sparxstar\IAtlas
 * @author    Starisian Technolgies (Max Barrett) <support@starisian.com>
 * @license   Starisian Technologies Proprietary License (STPD)
 * @copyright Copyright 2026 Starisian Technologies. All rights reserved.
 * @version   0.8.9
 */

declare( strict_types=1 );

namespace Starisian\Sparxstar\IAtlas\includes;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Class Sparxstar3IAtlasAutoLinker
 * 
 * Automatically links dictionary terms in post content.
 * 
 * @package Starisian\Sparxstar\IAtlas\includes
 */
class Sparxstar3IAtlasAutoLinker {

    // Cache the 12,000 word list for 7 days (Redis/DB).
    // We only clear this if a Dictionary entry is saved.
    private const DICT_LIST_CACHE_TIME = 604800;

    // Cache the processed HTML for a post (Persistent until post update).
    private const POST_CONTENT_CACHE_TIME = 0;

    private const SPARXSTAR_CACHE_KEY = 'sparxstar_3iatlas_dictionary';

    // Max terms per regex pass. Keeps each compiled pattern well under PCRE size limits.
    private const REGEX_CHUNK_SIZE = 200;

    /**
     * Post cache expiry time in seconds.
     *
     * @var int
     */
    private int $post_cache_expires;

    /**
     * Term cache expiry time in seconds.
     *
     * @var int
     */
    private int $term_cache_expires;

    /**
     * Constructor — initialises cache times and registers hooks.
     */
    public function __construct() {
        $this->_set_post_cache_time();
        $this->_set_term_cache_time();
        $this->register_hooks();
    }

    /**
     * Registers WordPress filters and actions.
     *
     * @return void
     */
    private function register_hooks(): void {
        // Run late (priority 20) so other shortcodes/filters process first.
        add_filter( 'the_content', array( $this, 'auto_link_content' ), 20 );

        // Clear specific post cache on update.
        add_action( 'save_post', array( $this, 'clear_post_cache' ) );

        // Clear the GLOBAL word list if a dictionary entry is modified.
        add_action( 'save_post_aiwa-cpt-dictionary', array( $this, 'clear_dictionary_list_cache' ) );
    }

    /**
     * The Main Filter Function
     *
     * @param string $content The post content to process.
     * @return string The processed content with dictionary links.
     */
    public function auto_link_content( string $content ): string {
        // 1. Bail early checks.
        // We want to run on Posts, Pages, and Dictionary Entries.
        // We allow filtering this list via 'sparx_autolink_post_types'.
        $allowed_types = apply_filters( 'sparx_autolink_post_types', array( 'post', 'page', 'aiwa-cpt-dictionary' ) );

        if ( is_admin() || ! is_main_query() || ! is_singular( $allowed_types ) ) {
            return $content;
        }

        global $post;
        
        // 2. Check for Cached Version (Redis/Transient).
        $cached_content = $this->_get_post_cache( $post->ID );

        if ( false !== $cached_content && ! empty( $cached_content ) ) {
            return $cached_content;
        }

        // 3. Get the "Haystack" (The 12,000 dictionary words).
        $terms = $this->get_dictionary_terms();

        if ( empty( $terms ) ) {
            return $content;
        }

        // 4. Perform the "Big Regex" Replacement.
        $processed_content = $this->process_replacements( $content, $terms );

        // 5. Save the result to cache.
        $this->_set_post_cache( $post->ID, $processed_content );
        return $processed_content;
    }

    /**
     * Get all dictionary words and their URLs.
     * Uses get_transient which automatically uses Redis if installed.
     */
    private function get_dictionary_terms(): array {
        $terms = $this->_get_term_cache();

        if ( is_array( $terms ) && ! empty( $terms ) ) {
            return $terms;
        }

        // Fetch IDs only for speed.
        $args = array(
            'post_type'              => 'aiwa-cpt-dictionary',
            'posts_per_page'         => -1,
            'post_status'            => 'publish',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );

        $query = new WP_Query( $args );
        $data  = array();

        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post_id ) {
                $title = get_the_title( $post_id );
                // Only link words > 3 chars to reduce noise ("The", "And").
                if ( strlen( $title ) > 3 ) {
                    $data[ $title ] = get_permalink( $post_id );
                }
            }
        }

        // Sort by length (Longest first) to ensure "Hospitality Management"
        // matches before "Hospitality".
        uksort(
            $data,
            function ( $a, $b ) {
                return strlen( $b ) - strlen( $a );
            } 
        );
        // Cache the full list for 7 days (see DICT_LIST_CACHE_TIME).
        $this->_set_term_cache( $data );

        return $data;
    }

    /**
     * Processes content turning matched terms into hyperlinks.
     *
     * Terms are processed in chunks of REGEX_CHUNK_SIZE to prevent PCRE from
     * failing with "regular expression is too large" when the dictionary is
     * large (e.g. 12,000+ words). Each chunk produces a fresh preg_replace_callback
     * call. Subsequent chunks safely skip already-linked text because group 1 of
     * the pattern matches existing <a> tags and returns them unchanged.
     *
     * Regex (unicode optimized) Explanation per chunk:
     * Group 1: <a ...>...</a>           (Skip existing links)
     * Group 2: <h[1-6] ...>...</h[1-6]> (Skip headings)
     * Group 3: <script ...>...</script>  (Skip scripts)
     * Group 4: <style ...>...</style>    (Skip styles)
     * Group 5: (?<!\p{L})TERM(?!\p{L})  (Match whole words only, Unicode-aware)
     *
     * @param string $content The HTML content to process.
     * @param array  $terms  Associative array of term => URL, sorted longest-first.
     * @return string
     */
    private function process_replacements( string $content, array $terms ): string {
        // Safety check: If no terms, don't run regex.
        if ( empty( $terms ) ) {
            return $content;
        }

        $current_post_id = get_the_ID();

        // Split the full term list into chunks so each compiled regex stays
        // well within PCRE's size limit (avoids "regular expression is too large").
        $chunks = array_chunk( $terms, self::REGEX_CHUNK_SIZE, true );

        foreach ( $chunks as $chunk_index => $chunk ) {
            $escaped_terms = array_map(
                function ( $term ) {
                    return preg_quote( $term, '/' );
                },
                array_keys( $chunk )
            );

            $term_group = implode( '|', $escaped_terms );

            // Group 1-4: Skip tags (A, H1-6, Script, Style).
            // Group 5: The Match (Unicode-aware word boundaries).
            $pattern = '/(<a\b[^>]*>.*?<\/a>)|(<h[1-6]\b[^>]*>.*?<\/h[1-6]>)|(<script\b[^>]*>.*?<\/script>)|(<style\b[^>]*>.*?<\/style>)|((?<!\p{L})(?:' . $term_group . ')(?!\p{L}))/isu';

            // Precompute a lowercase → [original_term, url] map so the callback
            // resolves each match in O(1) instead of scanning all chunk terms.
            $lowercase_map = array();
            foreach ( $chunk as $term => $url ) {
                $lowercase_map[ mb_strtolower( $term, 'UTF-8' ) ] = array(
                    'term' => $term,
                    'url'  => $url,
                );
            }

            $result = preg_replace_callback(
                $pattern,
                function ( $matches ) use ( $lowercase_map, $current_post_id ) {
                    // If groups 1-4 matched (Skip tags), return original text unchanged.
                    if ( ! empty( $matches[1] ) || ! empty( $matches[2] ) || ! empty( $matches[3] ) || ! empty( $matches[4] ) ) {
                        return $matches[0];
                    }

                    // Group 5 matched — a dictionary word.
                    $matched_word = $matches[0];
                    $entry        = $lowercase_map[ mb_strtolower( $matched_word, 'UTF-8' ) ] ?? null;

                    if ( null === $entry ) {
                        return $matched_word; // Fallback (should not be reached).
                    }

                    // Self-reference check: do not link a page to itself.
                    // url_to_postid() is heavy, but the final output is cached
                    // via transient so this only runs once per post.
                    if ( url_to_postid( $entry['url'] ) === $current_post_id ) {
                        return $matched_word;
                    }

                    return sprintf(
                        '<a href="%s" class="aiwa-dictionary-link" title="Define: %s" data-word="%s">%s</a>',
                        esc_url( $entry['url'] ),
                        esc_attr( $entry['term'] ),
                        esc_attr( $entry['term'] ),
                        $matched_word // Preserve original casing.
                    );
                },
                $content
            );

            // If a chunk fails (e.g. PCRE backtrack limit), log and preserve
            // the content as-is for this chunk rather than silently dropping output.
            if ( null === $result ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only logging guarded by WP_DEBUG.
                    error_log( '[Sparxstar 3iAtlas Dictionary]: Auto-linker regex failed on chunk ' . $chunk_index . '. PCRE error code: ' . preg_last_error() );
                }
                continue;
            }

            $content = $result;
        }

        return $content;
    }
    /**
     * Clears the cached linked content for a specific post.
     *
     * @param int $post_id The post ID whose cache should be cleared.
     * @return void
     */
    public function clear_post_cache( int $post_id = 0 ): void {
        if ( $post_id > 0 ) {
            delete_transient( $this->get_post_cache_key( $post_id ) );
        }
    }

    /**
     * Clears the global dictionary term list cache when a dictionary entry is saved.
     *
     * @param int $post_id The post ID that was saved.
     * @return void
     */
    public function clear_dictionary_list_cache( int $post_id = 0 ): void {
        if ( $post_id > 0 ) {
            $this->clear_post_cache( $post_id );
        }
        delete_transient( $this->get_term_cache_key() );
        // Optional: If you update a dictionary word, you might want to clear ALL post caches.
        // But that's expensive. Better to let them expire naturally or clear manually.
    }

    /**
     * Gets cached linked content for a post.
     *
     * @param int    $post_id  The post ID.
     * @param string $taxonomy Optional taxonomy slug.
     * @return mixed The cached content or false if not cached.
     */
    private function _get_post_cache( int $post_id, string $taxonomy = '' ): mixed {
        return get_transient( $this->get_post_cache_key( $post_id, $taxonomy ) );
    }

    /**
     * Stores linked content for a post in the transient cache.
     *
     * @param int    $post_id  The post ID.
     * @param string $content  The processed HTML content.
     * @param string $taxonomy Optional taxonomy slug.
     * @param int    $expires  Cache TTL in seconds.
     * @return void
     */
    private function _set_post_cache( int $post_id, string $content, string $taxonomy = '', int $expires = 0 ): void {
        if ( $expires <= 0 ) {
            $expires = $this->_get_post_cache_time();
        }
        $key = $this->get_post_cache_key( $post_id, $taxonomy );
        set_transient( $key, $content, $expires );
    }

    /**
     * Stores the dictionary term list in the transient cache.
     *
     * @param array  $terms    Associative array of term => URL.
     * @param string $taxonomy Optional taxonomy slug.
     * @param int    $expires  Cache TTL in seconds.
     * @return void
     */
    private function _set_term_cache( array $terms, string $taxonomy = '', int $expires = 0 ): void {
        if ( $expires <= 0 ) {
            $expires = $this->_get_term_cache_time();
        }
        $key = $this->get_term_cache_key( $taxonomy );
        set_transient( $key, $terms, $expires );
    }

    /**
     * Gets the cached dictionary term list.
     *
     * @param string $taxonomy Optional taxonomy slug.
     * @return mixed The cached term array or false if not cached.
     */
    private function _get_term_cache( string $taxonomy = '' ): mixed {
        return get_transient( $this->get_term_cache_key( $taxonomy ) );
    }

    /**
     * Sets the post content cache expiry time from constants or defaults.
     *
     * @param int $time Unused — here for forward compatibility.
     * @return void
     */
    private function _set_post_cache_time( int $time = 0 ): void {
        if ( defined( 'SPARX_3IATLAS_POST_CACHE' ) && SPARX_3IATLAS_POST_CACHE > 0 ) {
            $this->post_cache_expires = SPARX_3IATLAS_POST_CACHE;
        }
        $this->post_cache_expires = self::POST_CONTENT_CACHE_TIME;
    }

    /**
     * Sets the term cache expiry time from constants or defaults.
     *
     * @param int $time Unused — here for forward compatibility.
     * @return void
     */
    private function _set_term_cache_time( int $time = 0 ): void {
        if ( defined( 'SPARX_3IATLAS_TERM_CACHE' ) && SPARX_3IATLAS_TERM_CACHE > 0 ) {
            $this->term_cache_expires = SPARX_3IATLAS_TERM_CACHE;
        }
        $this->term_cache_expires = self::DICT_LIST_CACHE_TIME;
    }

    /**
     * Returns the transient cache key for a specific post.
     *
     * @param string $taxonomy Optional taxonomy slug.
     * @return string The cache key.
     */
    private function get_term_cache_key( string $taxonomy = '' ): string {
        $url       = home_url();
        $version   = defined( 'SPARX_3IATLAS_VERSION' ) ? SPARX_3IATLAS_VERSION : 'v1';
        $cache_key = md5( $url . '_' . $version . '_' . self::SPARXSTAR_CACHE_KEY );
        if ( ! empty( $taxonomy ) ) {
            $key = 'sparx_dictionary_term_' . $taxonomy . '_' . $cache_key;
        } else {
            $key = 'sparx_dictionary_term_' . $cache_key;
        } 
        return $key;
    }

    /**
     * Returns the transient cache key for a specific post's linked content.
     *
     * @param int    $post_id  The post ID.
     * @param string $taxonomy Optional taxonomy slug.
     * @return string The cache key.
     */
    private function get_post_cache_key( int $post_id = 0, string $taxonomy = '' ): string {
        $version   = defined( 'SPARX_3IATLAS_VERSION' ) ? SPARX_3IATLAS_VERSION : 'v1';
        $cache_key = md5( strval( $post_id ) . '_' . $version . '_' . self::SPARXSTAR_CACHE_KEY );
        if ( ! empty( $taxonomy ) ) {
            $key = 'sparx_linked_content_' . $taxonomy . '_' . $cache_key;
        } else {
            $key = 'sparx_linked_content_' . $cache_key;
        } 
        return $key;
    }

    /**
     * Returns the current post content cache expiry time.
     *
     * @return int Expiry time in seconds.
     */
    private function _get_post_cache_time(): int {
            return $this->post_cache_expires;
    }

    /**
     * Returns the current term list cache expiry time.
     *
     * @return int Expiry time in seconds.
     */
    private function _get_term_cache_time(): int {
            return $this->term_cache_expires;
    }
}
