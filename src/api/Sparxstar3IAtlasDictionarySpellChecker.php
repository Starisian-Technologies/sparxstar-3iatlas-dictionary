<?php

declare(strict_types=1);

/**
 * Batch spell checker for the 3iAtlas Dictionary.
 *
 * @package Starisian\Sparxstar\IAtlas\api
 * @license Starisian Technologies Proprietary License (STPL)
 */

namespace Starisian\Sparxstar\IAtlas\api;

if (!defined('ABSPATH')) {
    exit(1);
}

final class Sparxstar3IAtlasDictionarySpellChecker
{
    use Sparxstar3IAtlasRateLimitTrait;

    private const REST_NAMESPACE = 'sparxstar/v1/dictionary';
    private const CPT = 'aiwa-cpt-dictionary';
    private const MAX_WORDS = 100;
    private const RATE_LIMIT = 100;
    private const RATE_WINDOW = 900;

    public function register_hooks(): void
    {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function register_rest_routes(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            '/spell',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_spell'),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function handle_spell(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        // TODO: Replace with Helios token introspection when available.
        if (!$this->check_rate_limit()) {
            return new \WP_Error(
                'rate_limited',
                'Too many requests. Retry after 15 minutes.',
                array(
                    'status' => 429,
                    'headers' => array(
                        'Retry-After' => (string) self::RATE_WINDOW,
                    ),
                )
            );
        }

        $body = $request->get_json_params();
        $lang = sanitize_text_field((string) ($body['lang'] ?? ''));
        $words = $body['words'] ?? null;

        if (!is_array($words) || empty($words)) {
            return new \WP_Error('invalid_payload', 'words must be a non-empty array.', array('status' => 400));
        }

        $normalized_words = array();

        foreach (array_slice($words, 0, self::MAX_WORDS) as $word) {
            if (!is_scalar($word)) {
                return new \WP_Error('invalid_payload', 'Each item in words must be a scalar value.', array('status' => 400));
            }

            $normalized_words[] = sanitize_text_field((string) $word);
        }

        $results = array();
        $checked_words = array();

        foreach ($normalized_words as $word) {
            $word = trim($word);
            if ('' === $word) {
                continue;
            }

            if (!isset($checked_words[$word])) {
                $exact = $this->find_exact_word_post($word, $lang);
                $valid = $exact instanceof \WP_Post;

                if ($valid && '' !== $lang) {
                    $lang_terms = wp_get_object_terms($exact->ID, 'starmus_tax_language', array('fields' => 'slugs'));
                    $valid = !is_wp_error($lang_terms) && in_array($lang, $lang_terms, true);
                }

                $suggestions = array();

                if (!$valid) {
                    $fuzzy_args = array(
                        'post_type' => self::CPT,
                        'post_status' => 'publish',
                        'posts_per_page' => 5,
                        's' => $word,
                    );

                    if ('' !== $lang) {
                        $fuzzy_args['tax_query'] = array(
                            array('taxonomy' => 'starmus_tax_language', 'field' => 'slug', 'terms' => $lang),
                        );
                    }

                    $fuzzy = get_posts($fuzzy_args);
                    $suggestions = array_map(
                        static fn(\WP_Post $post): string => $post->post_title,
                        $fuzzy
                    );
                }

                $checked_words[$word] = array(
                    'valid' => $valid,
                    'suggestions' => $suggestions,
                );
            }

            $results[] = array(
                'word' => $word,
                'valid' => $checked_words[$word]['valid'],
                'suggestions' => $checked_words[$word]['suggestions'],
            );
        }

        return new \WP_REST_Response(array('results' => $results), 200);
    }

    private function find_exact_word_post(string $word, string $lang): ?\WP_Post
    {
        $args = array(
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'posts_per_page' => 5,
            's' => $word,
        );

        if ('' !== $lang) {
            $args['tax_query'] = array(
                array('taxonomy' => 'starmus_tax_language', 'field' => 'slug', 'terms' => $lang),
            );
        }

        $posts = get_posts($args);

        foreach ($posts as $post) {
            if (!$post instanceof \WP_Post) {
                continue;
            }

            if (0 === strcasecmp($post->post_title, $word)) {
                return $post;
            }
        }

        return null;
    }

}
