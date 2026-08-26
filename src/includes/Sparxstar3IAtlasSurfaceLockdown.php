<?php
/**
 * Closes the WordPress doors onto dictionary content that are not the API route.
 *
 * Implements spec §1.3: "Locking `wp-json/sparxstar/v1/dictionary` is void if
 * entries leak via WordPress's other doors."
 *
 * Two tiers, deliberately:
 *
 * 1. CLOSED IMMEDIATELY — surfaces no shipped client consumes, so closing them is
 *    not a behaviour change: default `wp/v2` REST, global search, feeds, sitemaps,
 *    oEmbed, and attachment pages for dictionary audio.
 * 2. CLOSED AT CUTOVER — surfaces that currently carry legitimate community access:
 *    single permalinks (the autolinker publishes them into post content) and the
 *    WPGraphQL full-index path (the deployed app's data source). Closing these today
 *    would reduce community access below its pre-measure level, which spec §7.1
 *    forbids, and §7.1 requires that conflict be escalated to an ADR rather than
 *    resolved silently here. See the ADR brief D-11.
 *
 * @package Starisian\Sparxstar\IAtlas\includes
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\IAtlas\includes;

use Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryProtection;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

/**
 * Sparxstar3IAtlasSurfaceLockdown — removes non-route exposure of dictionary entries.
 */
final class Sparxstar3IAtlasSurfaceLockdown {

    /**
     * The dictionary post type slug.
     *
     * Never changed: live data depends on it (AGENTS.md, Absolute Rules).
     *
     * @var string
     */
    public const CPT = 'aiwa-cpt-dictionary';

    /**
     * Register every lockdown hook.
     *
     * @return void
     */
    public function register_hooks(): void {
        add_filter( 'register_post_type_args', array( $this, 'harden_post_type_args' ), 20, 2 );
        add_filter( 'wp_sitemaps_post_types', array( $this, 'remove_from_sitemaps' ) );
        add_filter( 'oembed_response_data', array( $this, 'suppress_oembed' ), 10, 2 );
        add_action( 'pre_get_posts', array( $this, 'block_feed_queries' ) );
        add_action( 'template_redirect', array( $this, 'block_attachment_pages' ) );
    }

    /**
     * Harden the dictionary post type's registration arguments.
     *
     * `show_in_rest` is switched off rather than capability-gated. Spec §1.3 permits
     * either; switching it off is the complete option, because it also removes the CPT
     * from `/wp/v2/search`, which a per-route capability gate would leave open. The
     * editing experience is unaffected in practice: entry content lives in ACF fields,
     * and `post_content` is a generated search index rather than authored prose (see
     * Sparxstar3IAtlasDictionaryCore::sparxIAtlas_sync_dictionary_search_index).
     *
     * @param array<string,mixed> $args      Registration arguments.
     * @param string              $post_type The post type being registered.
     * @return array<string,mixed> The hardened arguments.
     */
    public function harden_post_type_args( array $args, string $post_type ): array {
        if ( self::CPT !== $post_type ) {
            return $args;
        }

        // Tier 1 — closed immediately.
        $args['show_in_rest']        = false;
        $args['exclude_from_search'] = true;
        $args['has_archive']         = false;

        // Keep the admin UI, which would otherwise follow `public` downward at cutover.
        $args['show_ui']      = true;
        $args['show_in_menu'] = true;

        // Tier 2 — closed at cutover only.
        if ( Sparxstar3IAtlasDictionaryProtection::is_cutover_complete() ) {
            $args['public']             = false;
            $args['publicly_queryable'] = false;
            $args['show_in_graphql']    = false;
            $args['rewrite']            = false;
        }

        return $args;
    }

    /**
     * Remove the dictionary post type from XML sitemaps (spec §1.3).
     *
     * @param array<string,mixed> $post_types Sitemap-eligible post type objects, keyed by name.
     * @return array<string,mixed> The filtered list.
     */
    public function remove_from_sitemaps( array $post_types ): array {
        unset( $post_types[ self::CPT ] );
        return $post_types;
    }

    /**
     * Suppress oEmbed responses for dictionary entries (spec §1.3).
     *
     * @param array<string,mixed> $data The oEmbed response data.
     * @param \WP_Post            $post The post being embedded.
     * @return array<string,mixed> Empty when the post is a dictionary entry.
     */
    public function suppress_oembed( $data, $post ) {
        if ( $post instanceof \WP_Post && self::CPT === $post->post_type ) {
            return array();
        }

        return $data;
    }

    /**
     * Keep dictionary entries out of every feed (spec §1.3).
     *
     * Covers both `/feed/?post_type=aiwa-cpt-dictionary` and any feed query that has
     * been widened to include the post type.
     *
     * Scoped to the main query. The leak vector §1.3 names is the feed request itself,
     * which is always the main query; a secondary WP_Query inside a feed template is
     * the theme's own call and is not silently rewritten from here.
     *
     * @param \WP_Query $query The query being prepared.
     * @return void
     */
    public function block_feed_queries( $query ): void {
        if ( ! $query instanceof \WP_Query || is_admin() ) {
            return;
        }

        if ( ! $query->is_main_query() || ! $query->is_feed() ) {
            return;
        }

        $requested = $query->get( 'post_type' );

        if ( self::CPT === $requested ) {
            // A feed explicitly asking for dictionary entries gets nothing to feed on.
            $query->set( 'post_type', 'post' );
            $query->set( 'post__in', array( 0 ) );
            return;
        }

        if ( is_array( $requested ) && in_array( self::CPT, $requested, true ) ) {
            $query->set( 'post_type', array_values( array_diff( $requested, array( self::CPT ) ) ) );
        }
    }

    /**
     * Return 404 for attachment pages belonging to dictionary entries (spec §1.3a).
     *
     * Audio recordings are the corpus's most irreplaceable layer. The attachment page
     * is a second, template-rendered route to the same file and is disabled here.
     * Relocating the files out of the walkable uploads path is separate work, gated on
     * the broker existing (spec §1.3a, ADR brief §6).
     *
     * @return void
     */
    public function block_attachment_pages(): void {
        if ( ! is_attachment() ) {
            return;
        }

        $attachment = get_queried_object();

        if ( ! $attachment instanceof \WP_Post ) {
            return;
        }

        $parent_id = (int) $attachment->post_parent;

        if ( $parent_id > 0 && self::CPT === get_post_type( $parent_id ) ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            nocache_headers();
        }
    }
}
