<?php

declare(strict_types=1);

/**
 * REST API controller for the 3iAtlas Dictionary.
 *
 * @package Starisian\Sparxstar\IAtlas\api
 * @license Starisian Technologies Proprietary License (STPL)
 */

namespace Starisian\Sparxstar\IAtlas\api;

if (!defined('ABSPATH')) {
    exit(1);
}

final class Sparxstar3IAtlasDictionaryRestApi
{
    public const REST_NAMESPACE = 'sparxstar/v1/dictionary';
    private const CPT = 'aiwa-cpt-dictionary';
    private const RATE_LIMIT = 100;
    private const RATE_WINDOW = 900;

    public function register_hooks(): void
    {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function register_rest_routes(): void
    {
        $routes = array(
            array('GET', '/lookup', 'handle_lookup'),
            array('GET', '/search', 'handle_search'),
            array('GET', '/wordlist', 'handle_wordlist'),
            array('GET', '/languages', 'handle_languages'),
            array('GET', '/domains', 'handle_domains'),
            array('GET', '/game-set', 'handle_game_set'),
            array('GET', '/word-of-day', 'handle_word_of_day'),
            array('POST', '/progress/sync', 'handle_progress_sync', 'permission_helios'),
        );

        foreach ($routes as $route) {
            $permission = isset($route[3]) ? array($this, $route[3]) : array($this, 'permission_open');
            register_rest_route(
                self::REST_NAMESPACE,
                $route[1],
                array(
                    'methods' => $route[0],
                    'callback' => array($this, $route[2]),
                    'permission_callback' => $permission,
                )
            );
        }
    }

    public function permission_open(): bool
    {
        return true;
    }

    public function permission_helios(\WP_REST_Request $request): bool
    {
        // TODO: Replace with Helios token introspection when available.
        $auth = $request->get_header('Authorization');
        $token = $auth ? str_replace('Bearer ', '', $auth) : '';
        return '' !== $token && is_user_logged_in();
    }

    private function check_rate_limit(): bool
    {
        // TODO: Replace with Helios token introspection when available.
        $ip = sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $key = 'dict_rl_' . md5($ip);
        $hit = (int) get_transient($key);

        if ($hit >= self::RATE_LIMIT) {
            return false;
        }

        set_transient($key, $hit + 1, self::RATE_WINDOW);
        return true;
    }

    private function rate_limit_error(): \WP_Error
    {
        return new \WP_Error(
            'rate_limited',
            'Too many requests. Retry after 15 minutes.',
            array('status' => 429)
        );
    }

    private function cached_response(array $data, int $max_age = 3600): \WP_REST_Response
    {
        $response = new \WP_REST_Response($data, 200);
        $response->header('Cache-Control', 'public, max-age=' . $max_age);
        return $response;
    }

    private static function domain_code_from_slug(string $slug): string
    {
        if (1 === preg_match('/-([0-9]+(?:\.[0-9]+)*)$/', $slug, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function build_entry(int $post_id, bool $include_audio = true): array
    {
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }

        $lang_terms = wp_get_object_terms($post_id, 'starmus_tax_language', array('fields' => 'slugs'));
        $domain_terms = wp_get_object_terms($post_id, 'aiwa_domain', array('fields' => 'names'));
        $pos_terms = wp_get_object_terms($post_id, 'starmus_part_of_speech', array('fields' => 'names'));

        $syn_posts = get_field('aiwa_synonyms', $post_id);
        $ant_posts = get_field('aiwa_antonyms', $post_id);
        $synonyms = is_array($syn_posts)
            ? array_map(
                static fn($entry): string => $entry instanceof \WP_Post ? $entry->post_title : '',
                $syn_posts
            )
            : array();
        $antonyms = is_array($ant_posts)
            ? array_map(
                static fn($entry): string => $entry instanceof \WP_Post ? $entry->post_title : '',
                $ant_posts
            )
            : array();
        $synonyms = array_values(array_filter($synonyms, static fn(string $value): bool => '' !== $value));
        $antonyms = array_values(array_filter($antonyms, static fn(string $value): bool => '' !== $value));

        $sentences_raw = get_field('aiwa_example_sentences', $post_id);
        $sentences = array();
        if (is_array($sentences_raw)) {
            foreach ($sentences_raw as $row) {
                $sentences[] = array(
                    'sentence' => (string) ($row['aiwa_sentence_example'] ?? ''),
                    'ipa' => (string) ($row['aiwa_sentence_ipa'] ?? ''),
                    'phonetic' => (string) ($row['aiwa_sentence_phonetic'] ?? ''),
                    'translation_en' => (string) ($row['aiwa_sentence_english'] ?? ''),
                    'translation_fr' => (string) ($row['aiwa_sentence_french'] ?? ''),
                );
            }
        }

        $entry = array(
            'uuid' => (string) get_field('aiwa_entry_uuid', $post_id),
            'headword' => $post->post_title,
            'slug' => $post->post_name,
            'definition' => (string) get_field('aiwa_extract', $post_id),
            'translation_en' => (string) get_field('aiwa_translation_english', $post_id),
            'translation_fr' => (string) get_field('aiwa_translation_french', $post_id),
            'ipa' => (string) get_field('aiwa_ipa_pronunciation', $post_id),
            'phonetic' => (string) get_field('aiwa_phonetic', $post_id),
            'part_of_speech' => !is_wp_error($pos_terms) && !empty($pos_terms) ? $pos_terms[0] : '',
            'language' => !is_wp_error($lang_terms) && !empty($lang_terms) ? $lang_terms[0] : '',
            'domain' => !is_wp_error($domain_terms) && !empty($domain_terms) ? $domain_terms[0] : '',
            'origin' => (string) get_field('aiwa_origin', $post_id),
            'synonyms' => $synonyms,
            'antonyms' => $antonyms,
            'example_sentences' => $sentences,
        );

        if ($include_audio) {
            $audio = get_field('aiwa_audio_file', $post_id);
            $entry['audio_url'] = is_array($audio) ? ($audio['url'] ?? null) : ($audio ?: null);
        }

        return $entry;
    }

    public function handle_lookup(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        // TODO: Replace with Helios token introspection when available.
        if (!$this->check_rate_limit()) {
            return $this->rate_limit_error();
        }

        $slug = sanitize_text_field((string) ($request->get_param('slug') ?? ''));
        $uuid = sanitize_text_field((string) ($request->get_param('uuid') ?? ''));

        if ('' === $slug && '' === $uuid) {
            return new \WP_Error('missing_param', 'slug or uuid is required.', array('status' => 400));
        }

        if ('' !== $slug) {
            $post = get_page_by_path($slug, OBJECT, self::CPT);
        } else {
            $posts = get_posts(
                array(
                    'post_type' => self::CPT,
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                    'meta_query' => array(
                        array('key' => 'aiwa_entry_uuid', 'value' => $uuid, 'compare' => '='),
                    ),
                )
            );
            $post = $posts[0] ?? null;
        }

        if (!$post instanceof \WP_Post) {
            return new \WP_Error('not_found', 'Entry not found.', array('status' => 404));
        }

        return $this->cached_response(
            array(
                'success' => true,
                'data' => $this->build_entry($post->ID),
            )
        );
    }

    public function handle_search(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        // TODO: Replace with Helios token introspection when available.
        if (!$this->check_rate_limit()) {
            return $this->rate_limit_error();
        }

        $q = sanitize_text_field((string) ($request->get_param('q') ?? ''));
        $lang = sanitize_text_field((string) ($request->get_param('lang') ?? ''));
        $per_page = min(100, max(1, absint($request->get_param('per_page') ?? 20)));
        $page = max(1, absint($request->get_param('page') ?? 1));

        if (mb_strlen($q) < 2) {
            return new \WP_Error('query_too_short', 'q must be at least 2 characters.', array('status' => 400));
        }

        $args = array(
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            's' => $q,
        );

        if ('' !== $lang) {
            $args['tax_query'] = array(
                array('taxonomy' => 'starmus_tax_language', 'field' => 'slug', 'terms' => $lang),
            );
        }

        $query = new \WP_Query($args);
        $items = array();

        foreach ($query->posts as $post) {
            if (!$post instanceof \WP_Post) {
                continue;
            }
            $lang_terms = wp_get_object_terms($post->ID, 'starmus_tax_language', array('fields' => 'slugs'));
            $items[] = array(
                'uuid' => (string) get_field('aiwa_entry_uuid', $post->ID),
                'headword' => $post->post_title,
                'slug' => $post->post_name,
                'definition' => (string) get_field('aiwa_extract', $post->ID),
                'translation_en' => (string) get_field('aiwa_translation_english', $post->ID),
                'ipa' => (string) get_field('aiwa_ipa_pronunciation', $post->ID),
                'language' => !is_wp_error($lang_terms) && !empty($lang_terms) ? $lang_terms[0] : '',
            );
        }

        return $this->cached_response(
            array(
                'success' => true,
                'data' => $items,
                'meta' => array(
                    'total' => (int) $query->found_posts,
                    'page' => $page,
                    'per_page' => $per_page,
                ),
            )
        );
    }

    public function handle_wordlist(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        // TODO: Replace with Helios token introspection when available.
        if (!$this->check_rate_limit()) {
            return $this->rate_limit_error();
        }

        $lang = sanitize_text_field((string) ($request->get_param('lang') ?? ''));
        $per_page = min(2000, max(1, absint($request->get_param('per_page') ?? 1000)));
        $page = max(1, absint($request->get_param('page') ?? 1));

        $args = array(
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'no_found_rows' => false,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
            'fields' => 'ids',
        );

        if ('' !== $lang) {
            $args['tax_query'] = array(
                array('taxonomy' => 'starmus_tax_language', 'field' => 'slug', 'terms' => $lang),
            );
        }

        $query = new \WP_Query($args);
        $words = array();

        foreach ($query->posts as $post_id) {
            $post = get_post((int) $post_id);
            if (!$post instanceof \WP_Post) {
                continue;
            }

            $lang_terms = wp_get_object_terms((int) $post_id, 'starmus_tax_language', array('fields' => 'slugs'));
            $words[] = array(
                'headword' => $post->post_title,
                'slug' => $post->post_name,
                'uuid' => (string) get_post_meta((int) $post_id, 'aiwa_entry_uuid', true),
                'language' => !is_wp_error($lang_terms) && !empty($lang_terms) ? $lang_terms[0] : '',
            );
        }

        $response = $this->cached_response(
            array(
                'success' => true,
                'data' => array('words' => $words),
                'meta' => array(
                    'total' => (int) $query->found_posts,
                    'page' => $page,
                    'per_page' => $per_page,
                ),
            ),
            3600
        );

        $etag = md5((string) $query->found_posts . $lang . $page);
        $response->header('ETag', '"' . $etag . '"');

        return $response;
    }

    public function handle_languages(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        // TODO: Replace with Helios token introspection when available.
        if (!$this->check_rate_limit()) {
            return $this->rate_limit_error();
        }

        $terms = get_terms(
            array(
                'taxonomy' => 'starmus_tax_language',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
            )
        );

        if (is_wp_error($terms)) {
            return new \WP_Error('taxonomy_error', 'Failed to retrieve languages.', array('status' => 500));
        }

        $languages = array_map(
            static fn(\WP_Term $term): array => array(
                'slug' => $term->slug,
                'name' => $term->name,
                'count' => (int) $term->count,
            ),
            $terms
        );

        return $this->cached_response(
            array('success' => true, 'data' => array('languages' => $languages)),
            604800
        );
    }

    public function handle_domains(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        // TODO: Replace with Helios token introspection when available.
        if (!$this->check_rate_limit()) {
            return $this->rate_limit_error();
        }

        $lang = sanitize_text_field((string) ($request->get_param('lang_source') ?? ''));

        $args = array(
            'taxonomy' => 'aiwa_domain',
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC',
        );

        if ('' !== $lang) {
            $args['object_ids'] = get_posts(
                array(
                    'post_type' => self::CPT,
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'tax_query' => array(
                        array('taxonomy' => 'starmus_tax_language', 'field' => 'slug', 'terms' => $lang),
                    ),
                )
            );
        }

        $terms = get_terms($args);

        if (is_wp_error($terms)) {
            return new \WP_Error('taxonomy_error', 'Failed to retrieve domains.', array('status' => 500));
        }

        $domains = array_map(
            static fn(\WP_Term $term): array => array(
                'slug' => $term->slug,
                'name' => $term->name,
                'code' => self::domain_code_from_slug($term->slug),
                'count' => (int) $term->count,
            ),
            $terms
        );

        return $this->cached_response(
            array('success' => true, 'data' => array('domains' => $domains))
        );
    }

    public function handle_game_set(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        // TODO: Replace with Helios token introspection when available.
        if (!$this->check_rate_limit()) {
            return $this->rate_limit_error();
        }

        $lang = sanitize_text_field((string) ($request->get_param('lang_source') ?? ''));
        $domain = sanitize_text_field((string) ($request->get_param('domain') ?? ''));
        $limit = min(50, max(1, absint($request->get_param('limit') ?? 20)));
        $include_audio = filter_var($request->get_param('include_audio'), FILTER_VALIDATE_BOOLEAN);

        if ('' === $lang) {
            return new \WP_Error('missing_param', 'lang_source is required.', array('status' => 400));
        }

        $tax_query = array(
            array('taxonomy' => 'starmus_tax_language', 'field' => 'slug', 'terms' => $lang),
        );

        if ('' !== $domain) {
            $tax_query[] = array('taxonomy' => 'aiwa_domain', 'field' => 'slug', 'terms' => $domain);
            $tax_query['relation'] = 'AND';
        }

        $posts = get_posts(
            array(
                'post_type' => self::CPT,
                'post_status' => 'publish',
                'posts_per_page' => $limit * 3,
                // TODO: Replace ORDER BY RAND() approach with scalable selection for large datasets.
                'orderby' => 'rand',
                'tax_query' => $tax_query,
            )
        );

        $words = array();
        foreach ($posts as $post) {
            if (!$post instanceof \WP_Post) {
                continue;
            }

            if (count($words) >= $limit) {
                break;
            }

            $translation_en = (string) get_field('aiwa_translation_english', $post->ID);
            $ipa = (string) get_field('aiwa_ipa_pronunciation', $post->ID);

            if ('' === $post->post_title || '' === $translation_en || '' === $ipa) {
                continue;
            }

            $lang_terms = wp_get_object_terms($post->ID, 'starmus_tax_language', array('fields' => 'slugs'));
            $domain_terms = wp_get_object_terms($post->ID, 'aiwa_domain', array('fields' => 'names'));
            $sentences_raw = get_field('aiwa_example_sentences', $post->ID);
            $example = is_array($sentences_raw) && !empty($sentences_raw)
                ? array(
                    'sentence' => (string) ($sentences_raw[0]['aiwa_sentence_example'] ?? ''),
                    'translation_en' => (string) ($sentences_raw[0]['aiwa_sentence_english'] ?? ''),
                )
                : null;

            $word = array(
                'uuid' => (string) get_field('aiwa_entry_uuid', $post->ID),
                'headword' => $post->post_title,
                'ipa' => $ipa,
                'phonetic' => (string) get_field('aiwa_phonetic', $post->ID),
                'translation_en' => $translation_en,
                'translation_fr' => (string) get_field('aiwa_translation_french', $post->ID),
                'part_of_speech' => '',
                'domain' => !is_wp_error($domain_terms) && !empty($domain_terms) ? $domain_terms[0] : '',
                'language' => !is_wp_error($lang_terms) && !empty($lang_terms) ? $lang_terms[0] : '',
                'example' => $example,
                'audio_url' => null,
            );

            if ($include_audio) {
                $audio = get_field('aiwa_audio_file', $post->ID);
                $word['audio_url'] = is_array($audio) ? ($audio['url'] ?? null) : ($audio ?: null);
            }

            $words[] = $word;
        }

        $response = $this->cached_response(
            array('success' => true, 'data' => array('words' => $words)),
            10800
        );
        $etag = md5($lang . $domain . $limit . (string) $include_audio);
        $response->header('ETag', '"' . $etag . '"');

        return $response;
    }

    public function handle_word_of_day(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        // TODO: Replace with Helios token introspection when available.
        if (!$this->check_rate_limit()) {
            return $this->rate_limit_error();
        }

        $today = gmdate('Y-m-d');
        $cache_key = 'dict_wod_' . $today;
        $cached = get_transient($cache_key);

        if (false !== $cached) {
            return $this->cached_response(
                array('success' => true, 'data' => array('word' => $cached, 'date' => $today)),
                3600
            );
        }

        $total = wp_count_posts(self::CPT)->publish ?? 0;
        if (0 === (int) $total) {
            return new \WP_Error('no_entries', 'No dictionary entries available.', array('status' => 404));
        }

        srand((int) str_replace('-', '', $today));
        $offset = rand(0, (int) $total - 1);

        $posts = get_posts(
            array(
                'post_type' => self::CPT,
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'offset' => $offset,
                'orderby' => 'ID',
                'order' => 'ASC',
            )
        );

        if (empty($posts) || !$posts[0] instanceof \WP_Post) {
            return new \WP_Error('not_found', 'Could not select word of the day.', array('status' => 404));
        }

        $entry = $this->build_entry($posts[0]->ID);
        set_transient($cache_key, $entry, 3600);

        return $this->cached_response(
            array('success' => true, 'data' => array('word' => $entry, 'date' => $today)),
            3600
        );
    }

    public function handle_progress_sync(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $body = $request->get_json_params();
        $events = $body['events'] ?? null;

        if (!is_array($events)) {
            return new \WP_Error('invalid_payload', 'events must be a JSON array.', array('status' => 400));
        }

        $user_id = get_current_user_id();
        $accepted = 0;
        $failed = 0;

        foreach ($events as $event) {
            if (!is_array($event)) {
                ++$failed;
                continue;
            }

            $type = sanitize_text_field((string) ($event['type'] ?? ''));
            $word_id = sanitize_text_field((string) ($event['word_uuid'] ?? ''));
            $game = sanitize_text_field((string) ($event['game'] ?? ''));
            $domain = sanitize_text_field((string) ($event['domain'] ?? ''));

            if ('' === $type) {
                ++$failed;
                continue;
            }

            switch ($type) {
                case 'aiwa_game_word_correct':
                    do_action('aiwa_game_word_correct', $user_id, $word_id, $game);
                    break;
                case 'aiwa_game_listen_write':
                    do_action('aiwa_game_listen_write', $user_id, $word_id);
                    break;
                case 'aiwa_game_session_complete':
                    do_action('aiwa_game_session_complete', $user_id, $domain);
                    break;
                case 'aiwa_game_domain_mastered':
                    do_action('aiwa_game_domain_mastered', $user_id, $domain);
                    break;
                case 'aiwa_game_streak_3':
                    do_action('aiwa_game_streak_3', $user_id);
                    break;
                case 'aiwa_game_new_word_practiced':
                    do_action('aiwa_game_new_word_practiced', $user_id, $word_id);
                    break;
                case 'aiwa_game_return_visit':
                    do_action('aiwa_game_return_visit', $user_id);
                    break;
                default:
                    ++$failed;
                    continue 2;
            }

            ++$accepted;
        }

        return new \WP_REST_Response(
            array('xp_awarded' => 0, 'gold_awarded' => 0, 'events_processed' => $accepted, 'failed' => $failed),
            200
        );
    }
}
