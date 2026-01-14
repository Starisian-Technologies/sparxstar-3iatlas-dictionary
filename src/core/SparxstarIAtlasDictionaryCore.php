<?php
namespace Starisian\Sparxstar\IAtlas\Core;


if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SparxstarIAtlasDictionaryCore
 *
 * Handles the core functionality for the plugin.
 * This class is implemented as a final singleton to ensure a single entry point.
 *
 * @package Starisian\Sparxstar\IAtlas\Core
 */
final class SparxstarIAtlasDictionaryCore {

        private static $instance = null;

        public static function sparxIAtlas_get_instance() {
            if ( self::$instance === null ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Private constructor to prevent direct instantiation.
         */
        private function __construct() {
                $this->sparxIAtlas_register_hooks();
        }

        private function  sparxIAtlas_register_hooks() {
                // Hook into ACF save post to sync search index
                add_action('acf/save_post', [$this, 'sparxIAtlas_sync_dictionary_search_index'], 20);
                // Specifically for WP All Import to ensure the search index builds
                add_action('pmxi_saved_post', function($id) {
                        $this->sparxIAtlas_sync_dictionary_search_index($id);
                }, 10, 1);
                // add action to set the alphabetical grouping taxonomy
                add_action('save_post_aiwa_cpt_dictionary', function($post_id) {
                        // Get the first letter of the title
                        $title = get_the_title($post_id);
                        $first_letter = strtoupper(substr($title, 0, 1));

                        // If it's a number or special char, group under '#'
                        if (!ctype_alpha($first_letter)) {
                                $first_letter = '#';
                        }

                        // Set the taxonomy term
                        wp_set_object_terms($post_id, $first_letter, 'aiwa-alpha-letter');
                }, 10, 1);
        }


        public function sparxIAtlas_sync_dictionary_search_index($post_id) {
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
                wp_update_post([
                        'ID'           => $post_id,
                        'post_content' => $combined_index
                ]);
        }
}

