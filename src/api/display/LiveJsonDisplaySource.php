<?php
/**
 * The live implementation of the display source: the Node's `/v1/display/*`
 * JSON tier, read server-side over an authenticated connection.
 *
 * This class is the ONLY place that knows the upstream route names, their query
 * parameters, and the `{success,data,meta}` envelope. Everything above it sees
 * the view-model arrays described on DictionaryDisplaySourceInterface.
 *
 * @package Starisian\Sparxstar\IAtlas\api\display
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\IAtlas\api\display;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

/**
 * Reads the Dictionary Node's bounded display tier.
 */
final class LiveJsonDisplaySource implements DictionaryDisplaySourceInterface {

    /**
     * Route for the language list — the one language-less route.
     *
     * @var string
     */
    private const ROUTE_LANGUAGES = '/v1/display/languages';

    /**
     * Route for a single entry.
     *
     * @var string
     */
    private const ROUTE_ENTRY = '/v1/display/entry';

    /**
     * Route for bounded search.
     *
     * @var string
     */
    private const ROUTE_SEARCH = '/v1/display/search';

    /**
     * Route for the domain list of one language.
     *
     * @var string
     */
    private const ROUTE_DOMAINS = '/v1/display/domains';

    /**
     * Route for the deterministic word of the day.
     *
     * @var string
     */
    private const ROUTE_WORD_OF_DAY = '/v1/display/word-of-day';

    /**
     * Upstream HTTP client.
     *
     * @var NodeHttpClient
     */
    private NodeHttpClient $http;

    /**
     * Constructor.
     *
     * @param NodeHttpClient $http Upstream HTTP client.
     */
    public function __construct( NodeHttpClient $http ) {
        $this->http = $http;
    }

    /**
     * Languages that have a compiled display projection.
     *
     * @return array{languages:array<int,array{code:string,name:string}>,meta:array<string,mixed>}|\WP_Error
     */
    public function get_languages(): array|\WP_Error {
        $result = $this->http->get( self::ROUTE_LANGUAGES );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $rows = self::list_from( $result['data'], 'languages' );
        if ( null === $rows ) {
            return self::schema_error();
        }

        $languages = array();
        foreach ( $rows as $row ) {
            $term = self::term( $row );
            if ( null !== $term ) {
                $languages[] = $term;
            }
        }

        return array(
            'languages' => $languages,
            'meta'      => $result['meta'],
        );
    }

    /**
     * One entry, by stable slug within one language.
     *
     * @param string $slug       Stable entry slug.
     * @param string $language   ISO 639-3 code.
     * @param string $reader_ref Opaque reader reference.
     * @return array{entry:array<string,mixed>,meta:array<string,mixed>}|\WP_Error
     */
    public function get_entry( string $slug, string $language, string $reader_ref ): array|\WP_Error {
        $result = $this->http->get(
            self::ROUTE_ENTRY,
            array(
                'slug' => $slug,
                'lang' => $language,
            ),
            $reader_ref
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $entry = self::entry_from( $result['data'] );
        if ( null === $entry ) {
            return self::schema_error();
        }

        return array(
            'entry' => $entry,
            'meta'  => $result['meta'],
        );
    }

    /**
     * Bounded search within one language.
     *
     * @param string $query      The reader's query.
     * @param string $language   ISO 639-3 code.
     * @param int    $limit      Maximum results.
     * @param string $reader_ref Opaque reader reference.
     * @return array{results:array<int,array<string,mixed>>,meta:array<string,mixed>}|\WP_Error
     */
    public function search( string $query, string $language, int $limit, string $reader_ref ): array|\WP_Error {
        $result = $this->http->get(
            self::ROUTE_SEARCH,
            array(
                'q'     => $query,
                'lang'  => $language,
                'limit' => (string) $limit,
            ),
            $reader_ref
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $rows = self::list_from( $result['data'], 'results' );
        if ( null === $rows ) {
            return self::schema_error();
        }

        $results = array();
        foreach ( $rows as $row ) {
            $hit = self::hit( $row );
            if ( null !== $hit ) {
                $results[] = $hit;
            }
        }

        $data = is_array( $result['data'] ) ? $result['data'] : array();

        return array(
            'results' => $results,
            'meta'    => array_merge(
                $result['meta'],
                array(
                    // Presented to the reader as "did you mean", never as a result.
                    'is_suggestion' => (bool) ( $data['is_suggestion'] ?? false ),
                    'truncated'     => (bool) ( $data['truncated'] ?? false ),
                )
            ),
        );
    }

    /**
     * Domains available within one language.
     *
     * @param string $language ISO 639-3 code.
     * @return array{domains:array<int,array{code:string,name:string}>,meta:array<string,mixed>}|\WP_Error
     */
    public function get_domains( string $language ): array|\WP_Error {
        $result = $this->http->get( self::ROUTE_DOMAINS, array( 'lang' => $language ) );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $rows = self::list_from( $result['data'], 'domains' );
        if ( null === $rows ) {
            return self::schema_error();
        }

        $domains = array();
        foreach ( $rows as $row ) {
            $term = self::term( $row );
            if ( null !== $term ) {
                $domains[] = $term;
            }
        }

        return array(
            'domains' => $domains,
            'meta'    => $result['meta'],
        );
    }

    /**
     * The deterministic entry for the current calendar day in one language.
     *
     * @param string $language   ISO 639-3 code.
     * @param string $reader_ref Opaque reader reference.
     * @return array{entry:array<string,mixed>,date:string,meta:array<string,mixed>}|\WP_Error
     */
    public function get_word_of_day( string $language, string $reader_ref ): array|\WP_Error {
        $result = $this->http->get( self::ROUTE_WORD_OF_DAY, array( 'lang' => $language ), $reader_ref );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $entry = self::entry_from( $result['data'] );
        if ( null === $entry ) {
            return self::schema_error();
        }

        $data = is_array( $result['data'] ) ? $result['data'] : array();
        $date = isset( $data['date'] ) && is_string( $data['date'] ) ? $data['date'] : '';

        return array(
            'entry' => $entry,
            'date'  => $date,
            'meta'  => $result['meta'],
        );
    }

    /**
     * Pull a list out of a payload, accepting either `data.<key>` or a bare
     * `data` array.
     *
     * @param mixed  $data Decoded `data` member.
     * @param string $key  Expected list key.
     * @return array<int,mixed>|null Null when the payload is not a list.
     */
    private static function list_from( mixed $data, string $key ): ?array {
        if ( is_array( $data ) && isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
            return array_values( $data[ $key ] );
        }

        if ( is_array( $data ) && array_is_list( $data ) ) {
            return $data;
        }

        return null;
    }

    /**
     * Pull an entry out of a payload, accepting `data.entry`, `data.word`, or a
     * bare entry object.
     *
     * @param mixed $data Decoded `data` member.
     * @return array<string,mixed>|null Null when no entry could be read.
     */
    private static function entry_from( mixed $data ): ?array {
        if ( ! is_array( $data ) ) {
            return null;
        }

        foreach ( array( 'entry', 'word' ) as $key ) {
            if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
                return self::entry( $data[ $key ] );
            }
        }

        return self::entry( $data );
    }

    /**
     * Validate and normalise one entry.
     *
     * Slug, headword and language are required — without them there is nothing
     * to address or render. Everything else is optional by design: a field the
     * Node withholds on rights grounds is absent or empty, and the entry must
     * still render (contract §8).
     *
     * @param array<string,mixed> $row Raw entry.
     * @return array<string,mixed>|null Null when the required fields are absent.
     */
    private static function entry( array $row ): ?array {
        $slug     = self::text( $row, 'slug' );
        $headword = self::text( $row, 'header_word', 'headword' );
        $language = self::text( $row, 'language' );

        if ( '' === $slug || '' === $headword || '' === $language ) {
            return null;
        }

        $examples = array();
        $raw      = $row['examples'] ?? ( $row['example_sentences'] ?? null );
        if ( is_array( $raw ) ) {
            foreach ( $raw as $example ) {
                if ( ! is_array( $example ) ) {
                    continue;
                }
                $examples[] = array(
                    'sentence'       => self::text( $example, 'sentence' ),
                    'ipa'            => self::text( $example, 'ipa' ),
                    'phonetic'       => self::text( $example, 'phonetic' ),
                    'translation_en' => self::text( $example, 'translation_en' ),
                    'translation_fr' => self::text( $example, 'translation_fr' ),
                );
            }
        }

        return array(
            'slug'               => $slug,
            'headword'           => $headword,
            'language'           => $language,
            'locale'             => self::text( $row, 'locale' ),
            'part_of_speech'     => self::text( $row, 'part_of_speech' ),
            'definition'         => self::text( $row, 'definition' ),
            'ipa'                => self::text( $row, 'ipa_pronunciation', 'ipa' ),
            'phonetic'           => self::text( $row, 'phonetic_pronunciation', 'phonetic' ),
            'translation_en'     => self::text( $row, 'translation_en' ),
            'translation_fr'     => self::text( $row, 'translation_fr' ),
            'domain'             => self::text( $row, 'domain' ),
            'accepted_spellings' => DisplayText::nfc_list( $row['accepted_spellings'] ?? null ),
            'examples'           => $examples,
            'audio_url'          => self::url( $row['audio_url'] ?? null ),
            'image_url'          => self::url( $row['image_url'] ?? null ),
        );
    }

    /**
     * Validate and normalise one search hit.
     *
     * @param mixed $row Raw hit.
     * @return array<string,mixed>|null Null when the hit is unusable.
     */
    private static function hit( mixed $row ): ?array {
        if ( ! is_array( $row ) ) {
            return null;
        }

        $slug     = self::text( $row, 'slug' );
        $headword = self::text( $row, 'header_word', 'headerWord', 'headword' );

        if ( '' === $slug || '' === $headword ) {
            return null;
        }

        return array(
            'slug'           => $slug,
            'headword'       => $headword,
            'language'       => self::text( $row, 'language' ),
            'part_of_speech' => self::text( $row, 'part_of_speech', 'partOfSpeech' ),
            'matched_on'     => self::text( $row, 'matched_on', 'matchedOn' ),
        );
    }

    /**
     * Validate and normalise a code/name term. A term with no code is dropped;
     * a term with no name falls back to its own code, because the Node states
     * that rendering a code is honest and inventing a name is not (§5).
     *
     * @param mixed $row Raw term.
     * @return array{code:string,name:string}|null
     */
    private static function term( mixed $row ): ?array {
        if ( ! is_array( $row ) ) {
            return null;
        }

        $code = self::text( $row, 'code', 'language', 'domain_code' );
        if ( '' === $code ) {
            return null;
        }

        $name = self::text( $row, 'name', 'display_name' );

        return array(
            'code' => $code,
            'name' => '' === $name ? $code : $name,
        );
    }

    /**
     * Read the first present string key, NFC-normalised.
     *
     * @param array<string,mixed> $row  Source row.
     * @param string              ...$keys Candidate keys, in priority order.
     * @return string
     */
    private static function text( array $row, string ...$keys ): string {
        foreach ( $keys as $key ) {
            if ( isset( $row[ $key ] ) && is_string( $row[ $key ] ) && '' !== $row[ $key ] ) {
                return DisplayText::nfc( $row[ $key ] );
            }
        }
        return '';
    }

    /**
     * Sanitise a media URL supplied upstream. A withheld asset arrives as null
     * and stays null.
     *
     * @param mixed $value Candidate URL.
     * @return string|null
     */
    private static function url( mixed $value ): ?string {
        if ( ! is_string( $value ) || '' === $value ) {
            return null;
        }
        $clean = esc_url_raw( $value );
        return '' === $clean ? null : $clean;
    }

    /**
     * The schema-violation error. One code, one message, no upstream detail.
     *
     * @return \WP_Error
     */
    private static function schema_error(): \WP_Error {
        return new \WP_Error(
            'display_upstream_schema',
            __( 'The dictionary service returned data this site could not read.', 'sparxstar-3iatlas-dictionary' ),
            array( 'status' => 502 )
        );
    }
}
