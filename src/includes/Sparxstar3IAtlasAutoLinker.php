<?php
declare( strict_types=1 );
/**
 * Sparxstar IAtlas Auto Linker
 *
 * @package   Starisian\Sparxstar\IAtlas
 * @author    Starisian Technolgies (Max Barrett) <support@starisian.com>
 * @license   Starisian Technologies Proprietary License (STPD)
 * @copyright Copyright 2026 Starisian Technologies. All rights reserved.
 * @version   0.8.9
 */
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

    // Cache the 12,000 word list for 7 days (Redis/DB)
    // We only clear this if a Dictionary entry is saved.
    private const DICT_LIST_CACHE_TIME = 604800; 

    // Cache the processed HTML for a post (Persistent until post update)
    private const POST_CONTENT_CACHE_TIME = 0; 

    private const SPARXSTAR_CACHE_KEY = 'sparxstar_3iatlas_dictionary';

    private int $post_cache_expires;
    private int $term_cache_expires;

    public function __construct() {
        $this->_set_post_cache_time();
        $this->_set_term_cache_time();    
        $this->register_hooks();
    }

    private function register_hooks(): void {
        // Run late (priority 20) so other shortcodes/filters process first
        add_filter( 'the_content', array( $this, 'auto_link_content' ), 20 );

        // Clear specific post cache on update
        add_action( 'save_post', array( $this, 'clear_post_cache' ) );
        
        // Clear the GLOBAL word list if a dictionary entry is modified
        add_action( 'save_post_aiwa-cpt-dictionary', array( $this, 'clear_dictionary_list_cache' ) );
    }

    /**
     * The Main Filter Function
     */
    public function auto_link_content( string $content ): string {
        // 1. Bail early checks
        // We want to run on Posts, Pages, and Dictionary Entries
        // We allow filtering this list via 'sparx_autolink_post_types'
        $allowed_types = apply_filters( 'sparx_autolink_post_types', array( 'post', 'page', 'aiwa-cpt-dictionary' ) );

        if ( is_admin() || ! is_main_query() || ! is_singular( $allowed_types ) ) {
            return $content;
        }

        global $post;
        
        // 2. Check for Cached Version (Redis/Transient)
        $cached_content = $this->_get_post_cache( $post->ID );

        if ( false !== $cached_content && ! empty( $cached_content ) ) {
            return $cached_content;
        }

        // 3. Get the "Haystack" (The 12,000 dictionary words)
        $terms = $this->get_dictionary_terms();

        if ( empty( $terms ) ) {
            return $content;
        }

        // 4. Perform the "Big Regex" Replacement
        $processed_content = $this->process_replacements( $content, $terms );

        // 5. Save the result to cache
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

        // Fetch IDs only for speed
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
                // Only link words > 3 chars to reduce noise ("The", "And")
                if ( strlen( $title ) > 3 ) {
                    $data[ $title ] = get_permalink( $post_id );
                }
            }
        }

        // Sort by length (Longest first) to ensure "Hospitality Management" 
        // matches before "Hospitality"
        uksort(
            $data,
            function ( $a, $b ) {
                return strlen( $b ) - strlen( $a );
            } 
        );
        // Cache the full list for 7 days (see DICT_LIST_CACHE_TIME)
        $this->_set_term_cache( $data );

        return $data;
    }

    /**
     * Processes content turning matched terms into hyperlinks
     * 
     * Regex (unicode optimized) Explanation:
     * Group 1: <a ...>...</a> (Skip existing links)
     * Group 2: <h[1-6] ...>...</h[1-6]> (Skip headings)
     * Group 3: <script ...>...</script> (Skip scripts)
     * Group 4: <style ...>...</style> (Skip styles)
     * Group 5: (\p{L})(?:' . $term_group . ')(?=\P{L}|$) (Match whole words only)
     *
     * @param string $content
     * @param array  $terms
     * @return string
     */
    private function process_replacements( string $content, array $terms ): string {
        // Prepare terms for Regex: Escape chars
        $escaped_terms = array_map(
            function ( $term ) {
                return preg_quote( $term, '/' );
            },
            array_keys( $terms ) 
        );
        
        $term_group = implode( '|', $escaped_terms );

        // 1. Get the current post's ID for self-reference check
        $current_post_id = get_the_ID();

        // 2. Define the Regex Pattern
        // Group 1-4: Skip tags (A, H1-6, Script, Style)
        // Group 5: The Match
        // CHANGE: Replaced \b with (?<!\p{L}) and (?!\p{L})
        // This ensures we only match if the term is NOT surrounded by other letters.
        // We use the /u modifier for Unicode support.
        
        $pattern = '/(<a\b[^>]*>.*?<\/a>)|(<h[1-6]\b[^>]*>.*?<\/h[1-6]>)|(<script\b[^>]*>.*?<\/script>)|(<style\b[^>]*>.*?<\/style>)|((?<!\p{L})(?:' . $term_group . ')(?!\p{L}))/isu';

        return preg_replace_callback(
            $pattern,
            function ( $matches ) use ( $terms, $current_post_id ) {
                // If groups 1-4 matched (Skip tags), return original text unchanged
                if ( ! empty( $matches[1] ) || ! empty( $matches[2] ) || ! empty( $matches[3] ) || ! empty( $matches[4] ) ) {
                    return $matches[0];
                }

                // Group 5 matched! (Dictionary Word)
                $matched_word = $matches[0];
            
                // Look up URL (Case-insensitive)
                foreach ( $terms as $term => $url ) {
                    // Use multibyte safe comparison for Unicode strings
                    if ( mb_strtolower( $term, 'UTF-8' ) === mb_strtolower( $matched_word, 'UTF-8' ) ) {
                    
                        // Robust Self-Reference Check
                        // Ignore domain, check if the linked Post ID is the current Post ID
                        $linked_post_id = url_to_postid( $url );

                        if ( $linked_post_id === $current_post_id ) {
                            return $matched_word;
                        }

                        return sprintf( 
                            '<a href="%s" class="aiwa-dictionary-link" title="Define: %s" data-word="%s">%s</a>', 
                            esc_url( $url ), 
                            esc_attr( $term ),
                            esc_attr( $term ),
                            $matched_word // Preserve original casing
                        );
                    }
                }

                return $matched_word; // Fallback
            },
            $content 
        );
    }

    public function clear_post_cache( int $post_id = 0 ): void {
        if ( $post_id > 0 ) {
            delete_transient( $this->get_post_cache_key( $post_id ) );
        }
    }

    public function clear_dictionary_list_cache( int $post_id = 0 ): void {
        if ( $post_id > 0 ) {
            $this->clear_post_cache( $post_id );    
        }
        delete_transient( $this->get_term_cache_key() );
        // Optional: If you update a dictionary word, you might want to clear ALL post caches
        // But that's expensive. Better to let them expire naturally or clear manually.
    }

    private function _get_post_cache( int $post_id, string $taxonomy = '' ): mixed {
        return get_transient( $this->get_post_cache_key( $post_id, $taxonomy ) );
    }

    private function _set_post_cache( int $post_id, string $content, string $taxonomy = '', int $expires = 0 ): void {
        if ( $expires <= 0 ) {
            $expires = $this->_get_post_cache_time();
        }
        $key = $this->get_post_cache_key( $post_id, $taxonomy );
        set_transient( $key, $content, $expires );
    }

    private function _set_term_cache( array $terms, string $taxonomy = '', int $expires = 0 ): void {
        if ( $expires <= 0 ) {
            $expires = $this->_get_term_cache_time();
        }
        $key = $this->get_term_cache_key( $taxonomy );
        set_transient( $key, $terms, $expires );
    }

    private function _get_term_cache( string $taxonomy = '' ): mixed {
        return get_transient( $this->get_term_cache_key( $taxonomy ) );
    }

    private function _set_post_cache_time( int $time = 0 ): void {
        if ( defined( 'SPARX_3IATLAS_POST_CACHE' ) && SPARX_3IATLAS_POST_CACHE > 0 ) {
            $this->post_cache_expires = SPARX_3IATLAS_POST_CACHE;
        }
        $this->post_cache_expires = self::POST_CONTENT_CACHE_TIME;
    }

    private function _set_term_cache_time( int $time = 0 ): void {
        if ( defined( 'SPARX_3IATLAS_TERM_CACHE' ) && SPARX_3IATLAS_TERM_CACHE > 0 ) {
            $this->term_cache_expires = SPARX_3IATLAS_TERM_CACHE;
        }
        $this->term_cache_expires = self::DICT_LIST_CACHE_TIME;
    }

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

    private function _get_post_cache_time(): int {
            return $this->post_cache_expires;
    }

    private function _get_term_cache_time(): int {
            return $this->term_cache_expires;
    }
}
