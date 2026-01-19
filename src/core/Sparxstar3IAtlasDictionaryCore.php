<?php

/**
 * Core functionality file.
 *
 * @package Starisian\Sparxstar\IAtlas\core
 * @author Starisian Technologies (Max Barrett) <support@starisian.com>
 * @version 0.6.5
 * @since 0.1.0
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

namespace Starisian\Sparxstar\IAtlas\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Sparxstar3IAtlasDictionaryCore
 *
 * Handles the core functionality for the plugin.
 * This class is implemented as a final singleton to ensure a single entry point.
 *
 * @package Starisian\Sparxstar\IAtlas\core
 */
final class Sparxstar3IAtlasDictionaryCore
{

    /**
     * Singleton instance of the class.
     *
     * @var Sparxstar3IAtlasDictionaryCore|null
     */
    private static ?Sparxstar3IAtlasDictionaryCore $instance = null;

    /**
     * Gets the singleton instance of the class.
     *
     * @return Sparxstar3IAtlasDictionaryCore The singleton instance.
     */
    public static function sparxIAtlas_get_instance(): Sparxstar3IAtlasDictionaryCore
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
        $this->sparxIAtlas_register_hooks();
    }

    /**
     * Registers the necessary actions and filters.
     *
     * @return void
     */
    private function sparxIAtlas_register_hooks(): void
    {
        // Hook into ACF save post to sync search index
        add_action('acf/save_post', array($this, 'sparxIAtlas_sync_dictionary_search_index'), 20);
        // Specifically for WP All Import to ensure the search index builds
        add_action(
            'pmxi_saved_post',
            function ($id) {
                $this->sparxIAtlas_sync_dictionary_search_index($id);
            },
            10,
            1
        );
        // add action to set the alphabetical grouping taxonomy
        add_action(
            'save_post_aiwa_cpt_dictionary',
            function ($post_id) {
                // Get the first letter of the title
                $title        = get_the_title($post_id);
                $first_letter = strtoupper(substr($title, 0, 1));

                // If it's a number or special char, group under '#'
                if (! ctype_alpha($first_letter)) {
                    $first_letter = '#';
                }

                // Set the taxonomy term
                wp_set_object_terms($post_id, $first_letter, 'aiwa-alpha-letter');
            },
            10,
            1
        );

        // NEW: Increase the query limit for Dictionary requests
        add_filter('graphql_connection_max_query_amount', array($this, 'sparxIAtlas_increase_query_limit'), 10, 5);
    }


    /**
     * Syncs the dictionary search index when a post is saved.
     *
     * Combines the foreign word and translation into a single searchable string.
     *
     * @param int $post_id The ID of the post being saved.
     * @return void
     */
    public function sparxIAtlas_sync_dictionary_search_index(int $post_id): void
    {
        // Only run for our Dictionary CPT
        if (get_post_type($post_id) !== 'aiwa_cpt_dictionary') {
            return;
        }

        // Get the title (Foreign Word) and the ACF translation (English)
        $foreign_word = get_the_title($post_id);
        $translation  = get_field('aiwa_translation', $post_id);

        // Combine them into a single string
        $combined_index = $foreign_word . ' ' . $translation;

        // Update the post_content (hidden index) without triggering an infinite loop
        remove_action('acf/save_post', 'sync_dictionary_search_index', 20);
        wp_update_post(
            array(
                'ID'           => $post_id,
                'post_content' => $combined_index,
            )
        );
    }

    /**
     * Increase the max query limit for Dictionary entries.
     * This allows the React App to fetch all 12,000+ words in one request.
     *
     * @param int $amount The default limit (usually 10 or 100).
     * @param mixed $source The source of the connection.
     * @param array $args The arguments passed to the connection.
     * @param mixed $context The context of the request.
     * @param mixed $info The info about the query.
     * @return int The new limit.
     */
     public function sparxIAtlas_increase_query_limit( int $amount, $source, array $args, $context, $info ): int {
        // Allow dictionary queries to fetch up to 2000 items (covering our 1000 item chunks)
        if ( isset( $info->fieldName ) && 'dictionaries' === $info->fieldName ) {
            return 2000;
        }

        return $amount;
    }


    // Prevent cloning and unserializing
    /**
     * Prevents cloning of the singleton instance.
     *
     * @return never
     */
    private function __clone(): never
    {
        _doing_it_wrong(
            __FUNCTION__,
            'Cloning this object is forbidden.',
            SPARX_3IATLAS_VERSION
        );
        throw new \RuntimeException('Cloning is not allowed.');
    }
    /**
     * Prevents unserializing of the singleton instance.
     *
     * @return never
     */
    public function __wakeup(): never
    {
        _doing_it_wrong(
            __FUNCTION__,
            'Serializing this object is forbidden.',
            SPARX_3IATLAS_VERSION
        );
        throw new \RuntimeException('Serializing is not allowed.');
    }

    public function __unserialize(array $data): never
    {
        _doing_it_wrong(
            __FUNCTION__,
            'Unserializing this object is forbidden.',
            SPARX_3IATLAS_VERSION
        );
        throw new \RuntimeException('Unserializing is not allowed.');
    }
}
