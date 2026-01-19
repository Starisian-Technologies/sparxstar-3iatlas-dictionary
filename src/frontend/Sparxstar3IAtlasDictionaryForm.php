<?php
declare( strict_types=1 );
/**
 * Plugin Name: AIWA Dictionary Form
 * Description: Frontend form for adding/editing dictionary entries
 * Version: 1.0
 * Author: AIWA
 */
namespace Starisian\Sparxstar\IAtlas\frontend;

use WP_Query;
use function defined;
use function esc_attr;
use function esc_html;
use function esc_textarea;
use function esc_url;
use function get_edit_post_link;
use function get_post;
use function get_post_meta;
use function has_shortcode;
use function is_singular;
use function is_user_logged_in; 
use function intval;
use function is_string;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit();
}


/**
 * Class Sparxstar3IAtlasDictionaryForm
 * 
 * Handles the frontend form submission and rendering.
 *
 * @package Starisian\Sparxstar\IAtlas\frontend
 * @author Starisian Technologies (Max Barrett) <support@starisian.com>
 * @version 0.6.5
 * @since 0.1.0
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */
final class Sparxstar3IAtlasDictionaryForm {

    /**
     * Initializes the class and registers hooks.
     */
    public function __construct() {
        $this->sparxIAtlas_register_hooks();
    }

    /**
     * Register action hooks for the frontend form.
     * 
     * @return void
     */
    public function sparxIAtlas_register_hooks(): void {
        add_action( 'wp_enqueue_scripts', array( $this, 'sparxIAtlas_dict_form_enqueue_scripts' ) );
        add_action( 'wp_ajax_sparxIAtlas_dict_form_submit', array( $this, 'sparxIAtlas_dict_submit_form' ) );
        add_action( 'wp_ajax_sparxIAtlas_dict_search_synonyms', array( $this, 'sparxIAtlas_dict_search_synonyms' ) );
        add_action( 'wp_ajax_nopriv_sparxIAtlas_dict_search_synonyms', array( $this, 'sparxIAtlas_dict_search_synonyms' ) );
        add_action( 'wp_ajax_sparxIAtlas_dict_get_synonym_details', array( $this, 'sparxIAtlas_dict_get_synonym_details' ) );
        add_action( 'wp_ajax_nopriv_sparxIAtlas_dict_get_synonym_details', array( $this, 'sparxIAtlas_dict_get_synonym_details' ) );
        add_action( 'wp_ajax_sparxIAtlas_dict_get_entry_details', array( $this, 'sparxIAtlas_dict_get_entry_details' ) );
        add_action( 'wp_ajax_nopriv_sparxIAtlas_dict_get_entry_details', array( $this, 'sparxIAtlas_dict_get_entry_details' ) );
        add_action( 'init', array( $this, 'sparxIAtlas_register_shortcodes' ) );
    }

    /**
     * Registers the shortcode for the form.
     * 
     * @return void
     */
    public function sparxIAtlas_register_shortcodes(): void {
        add_shortcode( 'sparxstar_dictionary_form', array( $this, 'sparxIAtlas_dictionary_render_form' ) );
    }

    /**
     * Enqueue scripts and styles
     */
    public function sparxIAtlas_dict_form_enqueue_scripts(): void {
        global $post;
        if ( is_singular() && $post instanceof \WP_Post && has_shortcode( $post->post_content, 'sparxstar_dictionary_form' ) ) {
            wp_enqueue_media();
            if ( defined( 'SPARX_3IATLAS_URL' ) ) {
                wp_enqueue_style( 'sparxstar-dict-form-style', SPARX_3IATLAS_URL . 'assets/css/sparxstar-3iatlas-dictionary-form-style.min.css', array(), SPARX_3IATLAS_VERSION );
                wp_enqueue_script( 'sparxstar-dict-form-script', SPARX_3IATLAS_URL . 'assets/js/sparxstar-3iatlas-dictionary-form.min.js', array( 'jquery' ), SPARX_3IATLAS_VERSION, true );
            } 
            wp_localize_script(
                'sparxstar-dict-form-script',
                'sparxstarDict',
                array(
                    'ajaxurl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'sparxstar_dict_form_nonce' ),
                )
            );
        }
    }

    /**
     * Shortcode to display the form
     * Usage: [sparxstar_dictionary_form] or [sparxstar_dictionary_form entry_id="123"]
     * 
     * @param array $atts Shortcode attributes.
     * @return string rendered HTML of the form.
     */
    public function sparxIAtlas_dictionary_render_form( array $atts ): string {
        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            return '<div class="sparxstar-dict-notice error" role="alert">You must be logged in to access this form.</div>';
        }
    
        $atts = shortcode_atts(
            array(
                'entry_id' => 0,
            ),
            $atts
        );
    
        $entry_id       = intval( $atts['entry_id'] );
        $current_user   = wp_get_current_user();
        $is_editor      = current_user_can( 'edit_others_posts' );
        $is_contributor = current_user_can( 'edit_posts' );
    
        // Check permissions
        if ( $entry_id && ! $is_editor ) {
            return '<div class="sparxstar-dict-notice error" role="alert">Only editors can edit existing entries.</div>';
        }
    
        if ( ! $is_contributor && ! $is_editor ) {
            return '<div class="sparxstar-dict-notice error" role="alert">You do not have permission to add dictionary entries.</div>';
        }
    
        // Get existing entry data if editing
        $entry_data = array();
        if ( $entry_id ) {
            $post = get_post( $entry_id );
            if ( ! $post || $post->post_type !== 'aiwa_cpt_dictionary' ) {
                return '<div class="sparxstar-dict-notice error" role="alert">Invalid entry ID.</div>';
            }

        
            $entry_data = array(
                'title'                 => $post->post_title,
                'translation'           => get_post_meta( $entry_id, 'aiwa_translation', true ),
                'translation_english'   => get_post_meta( $entry_id, 'aiwa_translation_english', true ),
                'translation_french'    => get_post_meta( $entry_id, 'aiwa_translation_french', true ),
                'part_of_speech'        => get_post_meta( $entry_id, 'aiwa_part_of_speech', true ),
                'search_string_english' => get_post_meta( $entry_id, 'aiwa_search_string_english', true ),
                'search_string_french'  => get_post_meta( $entry_id, 'aiwa_search_string_french', true ),
                'rating_average'        => get_post_meta( $entry_id, 'aiwa_rating_average', true ),
                'ipa_pronunciation'     => get_post_meta( $entry_id, 'aiwa_ipa_pronunciation', true ),
                'audio_file'            => get_post_meta( $entry_id, 'aiwa_audio_file', true ),
                'origin'                => get_post_meta( $entry_id, 'aiwa_origin', true ),
                'word_photo'            => get_post_meta( $entry_id, 'aiwa_word_photo', true ),
                'example_sentences'     => get_post_meta( $entry_id, 'aiwa_example_sentences', true ),
                'extract'               => get_post_meta( $entry_id, 'aiwa_extract', true ),
                'synonyms'              => get_post_meta( $entry_id, 'aiwa_synonyms', true ),
            );
        }
    
        ob_start();
        ?>
    
    <div class="sparx-dict-form-container">
        <div class="sparx-dict-form-header">
            <h2><?php echo $entry_id ? 'Edit Dictionary Entry' : 'Add New Dictionary Entry'; ?></h2>
            <?php if ( $entry_id ) : ?>
                <p class="sparx-dict-notice info" role="status">Editing will create a new draft version without modifying the original entry.</p>
            <?php endif; ?>
        </div>
        
        <form id="sparx-dict-form" class="sparx-dict-form" data-entry-id="<?php echo esc_attr( strval( $entry_id ) ); ?>">
            
            <!-- Basic Information Section -->
            <div class="aiwa-form-section">
                <h3 class="section-title">Basic Information</h3>
                
                <div class="form-group">
                    <label for="aiwa_title">Word / Term *</label>
                    <input type="text" id="aiwa_title" name="aiwa_title" required aria-required="true" value="<?php echo esc_attr( $entry_data['title'] ?? '' ); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="aiwa_translation">Translation</label>
                        <input type="text" id="aiwa_translation" name="aiwa_translation" value="<?php echo esc_attr( $entry_data['translation'] ?? '' ); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="aiwa_part_of_speech">Part of Speech</label>
                        <select id="aiwa_part_of_speech" name="aiwa_part_of_speech">
                            <option value="">Select...</option>
                            <?php
                            $pos_options = array(
                                'noun'         => 'Noun',
                                'verb'         => 'Verb',
                                'adjective'    => 'Adjective',
                                'adverb'       => 'Adverb',
                                'pronoun'      => 'Pronoun',
                                'preposition'  => 'Preposition',
                                'conjunction'  => 'Conjunction',
                                'interjection' => 'Interjection',
                                'article'      => 'Article',
                                'determiner'   => 'Determiner',
                            );
                            foreach ( $pos_options as $value => $label ) {
                                $selected = ( $entry_data['part_of_speech'] ?? '' ) === $value ? 'selected' : '';
                                echo '<option value="' . esc_attr( $value ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $label ) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="aiwa_translation_english">Translation (English)</label>
                        <input type="text" id="aiwa_translation_english" name="aiwa_translation_english" value="<?php echo esc_attr( $entry_data['translation_english'] ?? '' ); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="aiwa_translation_french">Translation (French)</label>
                        <input type="text" id="aiwa_translation_french" name="aiwa_translation_french" value="<?php echo esc_attr( $entry_data['translation_french'] ?? '' ); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="aiwa_search_string_english">Search String (English)</label>
                        <input type="text" id="aiwa_search_string_english" name="aiwa_search_string_english" aria-describedby="desc_aiwa_search_string_english" value="<?php echo esc_attr( $entry_data['search_string_english'] ?? '' ); ?>">
                        <small id="desc_aiwa_search_string_english">Combination of word + English translation for search indexing</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="aiwa_search_string_french">Search String (French)</label>
                        <input type="text" id="aiwa_search_string_french" name="aiwa_search_string_french" aria-describedby="desc_aiwa_search_string_french" value="<?php echo esc_attr( $entry_data['search_string_french'] ?? '' ); ?>">
                        <small id="desc_aiwa_search_string_french">Combination of word + French translation for search indexing</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="aiwa_ipa_pronunciation">IPA Pronunciation</label>
                        <input type="text" id="aiwa_ipa_pronunciation" name="aiwa_ipa_pronunciation" value="<?php echo esc_attr( $entry_data['ipa_pronunciation'] ?? '' ); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="aiwa_rating_average">Rating Average</label>
                        <input type="number" id="aiwa_rating_average" name="aiwa_rating_average" min="1" max="5" step="0.01" aria-describedby="desc_aiwa_rating_average" value="<?php echo esc_attr( $entry_data['rating_average'] ?? '' ); ?>">
                        <small id="desc_aiwa_rating_average">Average rating (1-5)</small>
                    </div>
                </div>
            </div>
            
            <!-- Media Section -->
            <div class="aiwa-form-section">
                <h3 class="section-title">Media</h3>
                
                <div class="form-group">
                    <label for="aiwa_audio_file">Audio Recording</label>
                    <div class="media-upload-container">
                        <input type="hidden" id="aiwa_audio_file" name="aiwa_audio_file" value="<?php echo esc_attr( $entry_data['audio_file'] ?? '' ); ?>">
                        <button type="button" class="btn-secondary upload-media-btn" data-field="aiwa_audio_file" data-type="audio">
                            Choose Audio File
                        </button>
                        <span class="media-filename"></span>
                        <button type="button" class="btn-text remove-media-btn" style="display:none;" aria-label="Remove audio file">Remove</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="aiwa_word_photo">Word Photo</label>
                    <div class="media-upload-container">
                        <input type="hidden" id="aiwa_word_photo" name="aiwa_word_photo" value="<?php echo esc_attr( $entry_data['word_photo'] ?? '' ); ?>">
                        <button type="button" class="btn-secondary upload-media-btn" data-field="aiwa_word_photo" data-type="image">
                            Choose Image
                        </button>
                        <div class="image-preview-container">
                            <?php
                            if ( ! empty( $entry_data['word_photo'] ) ) : 
                                $img = wp_get_attachment_image_src( $entry_data['word_photo'], 'thumbnail' );
                                if ( $img ) :
                                    ?>
                                    <img src="<?php echo esc_url( $img[0] ); ?>" alt="Preview">
                                    <?php
                                endif; 
                                endif;
                            ?>
                        </div>
                        <button type="button" class="btn-text remove-media-btn" style="display:none;" aria-label="Remove image">Remove</button>
                    </div>
                </div>
            </div>
            
            <!-- Content Section -->
            <div class="aiwa-form-section">
                <h3 class="section-title">Content</h3>
                
                <div class="form-group">
                    <label for="aiwa_origin">Word Origin</label>
                    <input type="text" id="aiwa_origin" name="aiwa_origin" value="<?php echo esc_attr( $entry_data['origin'] ?? '' ); ?>">
                </div>
                
                <div class="form-group">
                    <label for="aiwa_extract">Extract (Long Definition)</label>
                    <textarea id="aiwa_extract" name="aiwa_extract" rows="4"><?php echo esc_textarea( $entry_data['extract'] ?? '' ); ?></textarea>
                </div>
            </div>
            
            <!-- Example Sentences Section -->
            <div class="aiwa-form-section">
                <h3 class="section-title">Example Sentences</h3>
                
                <div id="example-sentences-container">
                        <?php 
                        $sentences = $entry_data['example_sentences'] ?? array();
                        if ( ! empty( $sentences ) && is_array( $sentences ) ) {
                            foreach ( $sentences as $index => $sentence ) {
                                ?>
                            <div class="sentence-row" data-index="<?php echo esc_attr( $index ); ?>">
                                <div class="sentence-fields">
                                    <div class="form-group">
                                        <label for="sentence_<?php echo esc_attr( $index ); ?>_text">Sentence</label>
                                        <input type="text" id="sentence_<?php echo esc_attr( $index ); ?>_text" name="sentences[<?php echo esc_attr( $index ); ?>][aiwa_sentence]" value="<?php echo esc_attr( $sentence['aiwa_sentence'] ?? '' ); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="sentence_<?php echo esc_attr( $index ); ?>_trans">Translation</label>
                                        <input type="text" id="sentence_<?php echo esc_attr( $index ); ?>_trans" name="sentences[<?php echo esc_attr( $index ); ?>][aiwa_s_translation]" value="<?php echo esc_attr( $sentence['aiwa_s_translation'] ?? '' ); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="sentence_<?php echo esc_attr( $index ); ?>_trans_en">Translation (English)</label>
                                        <input type="text" id="sentence_<?php echo esc_attr( $index ); ?>_trans_en" name="sentences[<?php echo esc_attr( $index ); ?>][aiwa_s_translation_english]" value="<?php echo esc_attr( $sentence['aiwa_s_translation_english'] ?? '' ); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="sentence_<?php echo esc_attr( $index ); ?>_trans_fr">Translation (French)</label>
                                        <input type="text" id="sentence_<?php echo esc_attr( $index ); ?>_trans_fr" name="sentences[<?php echo esc_attr( $index ); ?>][aiwa_s_translation_french]" value="<?php echo esc_attr( $sentence['aiwa_s_translation_french'] ?? '' ); ?>">
                                    </div>
                                </div>
                                <button type="button" class="btn-text remove-sentence-btn" aria-label="Remove sentence">Remove</button>
                            </div>
                                <?php
                            }
                        }
                        ?>
                </div>
                
                <button type="button" id="add-sentence-btn" class="btn-secondary">Add Sentence</button>
            </div>
            
            <!-- Synonyms Section -->
            <div class="aiwa-form-section">
                <h3 class="section-title">Synonyms</h3>
                
                <div class="form-group">
                    <label for="aiwa_synonyms_search">Related Words</label>
                    <input type="text" id="aiwa_synonyms_search" aria-label="Search for synonyms" placeholder="Search for synonyms...">
                    <div id="synonym-results" class="synonym-results" role="listbox"></div>
                    <div id="selected-synonyms" class="selected-synonyms" role="list">
                        <?php 
                        if ( ! empty( $entry_data['synonyms'] ) && is_array( $entry_data['synonyms'] ) ) {
                            foreach ( $entry_data['synonyms'] as $syn_id ) {
                                $syn_post = get_post( $syn_id );
                                if ( $syn_post ) {
                                    echo '<span class="synonym-tag" data-id="' . esc_attr( $syn_id ) . '" role="listitem">' . esc_html( $syn_post->post_title ) . ' <button type="button" class="remove-synonym" aria-label="Remove synonym ' . esc_attr( $syn_post->post_title ) . '">×</button></span>';
                                }
                            }
                        }
                        ?>
                    </div>
                    <input type="hidden" id="aiwa_synonyms" name="aiwa_synonyms" value="<?php echo esc_attr( implode( ',', $entry_data['synonyms'] ?? array() ) ); ?>">
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn-primary" id="submit-btn">
                        <?php echo $entry_id ? 'Save as New Draft' : 'Add Entry (Draft)'; ?>
                </button>
                <div class="form-message" id="form-message"></div>
            </div>
            
        </form>
    </div>
    
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler for form submission
     */
    public function sparxIAtlas_dict_submit_form(): void {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sparxstar_dict_form_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }
    
        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
        }
    
        $current_user   = wp_get_current_user();
        $is_editor      = current_user_can( 'edit_others_posts' );
        $is_contributor = current_user_can( 'edit_posts' );
    
        // Check permissions
        if ( ! $is_contributor && ! $is_editor ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to add entries.' ) );
        }
    
        $entry_id = intval( $_POST['entry_id'] ?? 0 );
    
        // If editing, check editor permission
        if ( $entry_id && ! $is_editor ) {
            wp_send_json_error( array( 'message' => 'Only editors can edit entries.' ) );
        }
    
        // Validate required fields
        if ( empty( $_POST['aiwa_title'] ) ) {
            wp_send_json_error( array( 'message' => 'Word/Term is required.' ) );
        }
    
        // Create new post (always draft, never update existing)
        $post_data = array(
            'post_title'  => sanitize_text_field( $_POST['aiwa_title'] ),
            'post_type'   => 'aiwa_cpt_dictionary',
            'post_status' => 'draft',
            'post_author' => $current_user->ID,
        );
    
        $new_post_id = wp_insert_post( $post_data, true );
    
        if ( is_wp_error( $new_post_id ) ) {
            wp_send_json_error( array( 'message' => 'Failed to create entry: ' . $new_post_id->get_error_message() ) );
        }
    
        // Save meta fields
        $meta_fields = array(
            'aiwa_translation',
            'aiwa_translation_english',
            'aiwa_translation_french',
            'aiwa_part_of_speech',
            'aiwa_search_string_english',
            'aiwa_search_string_french',
            'aiwa_rating_average',
            'aiwa_ipa_pronunciation',
            'aiwa_audio_file',
            'aiwa_origin',
            'aiwa_word_photo',
            'aiwa_extract',
        );
    
        foreach ( $meta_fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $new_post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }
    
        // Save example sentences
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are sanitized inside the loop
        if ( isset( $_POST['sentences'] ) && is_array( $_POST['sentences'] ) ) {
            $sentences = array();
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are sanitized inside the loop
            foreach ( $_POST['sentences'] as $sentence ) {
                $sentences[] = array(
                    'aiwa_sentence'              => sanitize_text_field( $sentence['aiwa_sentence'] ?? '' ),
                    'aiwa_s_translation'         => sanitize_text_field( $sentence['aiwa_s_translation'] ?? '' ),
                    'aiwa_s_translation_english' => sanitize_text_field( $sentence['aiwa_s_translation_english'] ?? '' ),
                    'aiwa_s_translation_french'  => sanitize_text_field( $sentence['aiwa_s_translation_french'] ?? '' ),
                );
            }
            update_post_meta( $new_post_id, 'aiwa_example_sentences', $sentences );
        }
    
        // Save synonyms
        if ( isset( $_POST['aiwa_synonyms'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array map intval ensures safety
            $synonym_ids = array_filter( array_map( 'intval', explode( ',', $_POST['aiwa_synonyms'] ) ) );
            update_post_meta( $new_post_id, 'aiwa_synonyms', $synonym_ids );
        }
    
        // Add note if this was edited from an existing entry
        if ( $entry_id ) {
            update_post_meta( $new_post_id, '_aiwa_edited_from', $entry_id );
        }
    
        wp_send_json_success(
            array(
                'message'  => 'Entry saved as draft successfully!',
                'post_id'  => $new_post_id,
                'edit_url' => get_edit_post_link( $new_post_id, '' ),
            )
        );
    }

    /**
     * AJAX handler for synonym search
     */
    public function sparxIAtlas_dict_search_synonyms(): void {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sparxstar_dict_form_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }
    
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
        }
    
        $search = sanitize_text_field( $_POST['search'] ?? '' );
    
        if ( empty( $search ) ) {
            wp_send_json_success( array( 'results' => array() ) );
        }
    
        $args = array(
            'post_type'      => 'aiwa_cpt_dictionary',
            'post_status'    => 'publish',
            's'              => $search,
            'posts_per_page' => 10,
        );
    
        $query   = new WP_Query( $args );
        $results = array();
    
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $results[] = array(
                    'id'    => get_the_ID(),
                    'title' => get_the_title(),
                );
            }
        }
    
        wp_reset_postdata();
    
        wp_send_json_success( array( 'results' => $results ) );
    }
    /**
     * AJAX handler to get synonym details
     *
     * @return void
     */
    public function sparxIAtlas_dict_get_synonym_details(): void {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sparxstar_dict_form_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }
    
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
        }
    
        $syn_id = intval( $_POST['syn_id'] ?? 0 );
    
        if ( ! $syn_id ) {
            wp_send_json_error( array( 'message' => 'Invalid synonym ID.' ) );
        }
    
        $post = get_post( $syn_id );
    
        if ( ! $post || $post->post_type !== 'aiwa_cpt_dictionary' ) {
            wp_send_json_error( array( 'message' => 'Synonym not found.' ) );
        }
    
        $details = array(
            'id'    => $post->ID,
            'title' => $post->post_title,
        );
    
        wp_send_json_success( array( 'details' => $details ) );
    }
    /**
     * AJAX handler to get entry details
     *
     * @return void
     */
    public function sparxIAtlas_dict_get_entry_details(): void {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sparxstar_dict_form_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }
    
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
        }
    
        $entry_id = intval( $_POST['entry_id'] ?? 0 );
    
        if ( ! $entry_id ) {
            wp_send_json_error( array( 'message' => 'Invalid entry ID.' ) );
        }
    
        $post = get_post( $entry_id );
    
        if ( ! $post || $post->post_type !== 'aiwa_cpt_dictionary' ) {
            wp_send_json_error( array( 'message' => 'Entry not found.' ) );
        }
    
        $details = array(
            'id'    => $post->ID,
            'title' => $post->post_title,
        );
    
        wp_send_json_success( array( 'details' => $details ) );
    }
}