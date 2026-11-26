<?php
/**
 * The same-origin REST adapter Browse mode reads through.
 *
 * The browser talks to WordPress and to nothing else. WordPress talks to the
 * Dictionary Node server-side. The Node's URL, this site's credential, its
 * private key, its `client_id`, its `kid`, the access token, the client
 * assertion and every signed upstream header stay on the server — they are
 * never in a response, a header, a script, or an error message (contract §9).
 *
 * Responses reuse the `{success,data,meta}` envelope the React app already
 * parses. That envelope is built HERE from view-model arrays; the upstream
 * envelope never travels past LiveJsonDisplaySource.
 *
 * @package Starisian\Sparxstar\IAtlas\api\display
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\IAtlas\api\display;

use Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasRateLimitTrait;
use Starisian\Sparxstar\IAtlas\api\auth\DictionaryAuthResolver;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

/**
 * Registers and serves `/wp-json/sparxstar/v1/dictionary/display/*`.
 */
final class Sparxstar3IAtlasDisplayAdapterRestApi {

    use Sparxstar3IAtlasRateLimitTrait;

    /**
     * REST namespace, shared with the legacy dictionary API.
     *
     * @var string
     */
    public const REST_NAMESPACE = 'sparxstar/v1/dictionary';

    /**
     * Route prefix for the adapter.
     *
     * @var string
     */
    public const ROUTE_PREFIX = '/display';

    /**
     * Requests per window per client IP.
     *
     * @var int
     */
    private const RATE_LIMIT = 100;

    /**
     * Rate-limit window in seconds.
     *
     * @var int
     */
    private const RATE_WINDOW = 900;

    /**
     * Default number of search results requested upstream.
     *
     * @var int
     */
    private const DEFAULT_SEARCH_LIMIT = 20;

    /**
     * Ceiling on the number of search results this adapter will ask for.
     *
     * Bounded on purpose: there is no pagination on this seam, so a large limit
     * is an enumeration walk with extra steps (contract §2.1).
     *
     * @var int
     */
    private const MAX_SEARCH_LIMIT = 50;

    /**
     * Upstream data source.
     *
     * @var DictionaryDisplaySourceInterface
     */
    private DictionaryDisplaySourceInterface $source;

    /**
     * Language configuration.
     *
     * @var DisplayLanguageSettings
     */
    private DisplayLanguageSettings $languages;

    /**
     * Opaque reader reference provider.
     *
     * @var ReaderRef
     */
    private ReaderRef $reader;

    /**
     * Memoised language resolution for the current request. Never persisted.
     *
     * @var array{languages:array<int,array{code:string,name:string}>,default:string,selector:bool,missing:array<int,string>,notice:string}|null
     */
    private ?array $resolved = null;

    /**
     * Constructor.
     *
     * @param DictionaryDisplaySourceInterface $source    Upstream data source.
     * @param DisplayLanguageSettings          $languages Language configuration.
     * @param ReaderRef                        $reader    Reader reference provider.
     */
    public function __construct(
        DictionaryDisplaySourceInterface $source,
        DisplayLanguageSettings $languages,
        ReaderRef $reader
    ) {
        $this->source    = $source;
        $this->languages = $languages;
        $this->reader    = $reader;
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register_hooks(): void {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Register the adapter routes.
     *
     * @return void
     */
    public function register_rest_routes(): void {
        $routes = array(
            '/languages'   => 'handle_languages',
            '/entry'       => 'handle_entry',
            '/search'      => 'handle_search',
            '/domains'     => 'handle_domains',
            '/word-of-day' => 'handle_word_of_day',
        );

        foreach ( $routes as $path => $callback ) {
            register_rest_route(
                self::REST_NAMESPACE,
                self::ROUTE_PREFIX . $path,
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, $callback ),
                    'permission_callback' => array( $this, 'permission_reader' ),
                )
            );
        }
    }

    /**
     * Permission callback.
     *
     * The adapter is not an open proxy onto a metered upstream: a caller still
     * presents the site's own browse credential, exactly as the legacy routes
     * require, and is rate-limited per address on top.
     *
     * @param \WP_REST_Request $request Incoming request.
     * @return true|\WP_Error
     */
    public function permission_reader( \WP_REST_Request $request ): bool|\WP_Error {
        $result = ( new DictionaryAuthResolver() )->resolve( $request );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! $this->check_rate_limit() ) {
            return new \WP_Error(
                'rate_limited',
                __( 'Too many requests. Please slow down.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 429 )
            );
        }

        return true;
    }

    /**
     * GET /display/languages — the languages this deployment shows.
     *
     * Expands the CHOICES only. It never fetches entries, and never issues one
     * request per language (contract §6).
     *
     * @param \WP_REST_Request $request Incoming request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_languages( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        unset( $request );

        $resolved = $this->resolved_languages();
        if ( is_wp_error( $resolved ) ) {
            return $resolved;
        }

        return $this->respond(
            array(
                'languages' => $resolved['languages'],
                'default'   => $resolved['default'],
                'selector'  => $resolved['selector'],
            ),
            array( 'notice' => $resolved['notice'] )
        );
    }

    /**
     * GET /display/entry — one entry, by slug, in exactly one language.
     *
     * @param \WP_REST_Request $request Incoming request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_entry( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $slug = DisplayText::slug( (string) ( $request->get_param( 'slug' ) ?? '' ) );
        if ( '' === $slug ) {
            return new \WP_Error(
                'bad_request',
                __( 'A word is required.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 400 )
            );
        }

        $language = $this->requested_language( $request );
        if ( is_wp_error( $language ) ) {
            return $language;
        }

        $result = $this->source->get_entry( $slug, $language, $this->reader->current() );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->respond(
            array( 'entry' => $result['entry'] ),
            array( 'language' => $language )
        );
    }

    /**
     * GET /display/search — bounded search inside one language.
     *
     * @param \WP_REST_Request $request Incoming request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_search( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $query = DisplayText::nfc( sanitize_text_field( (string) ( $request->get_param( 'q' ) ?? '' ) ) );
        if ( '' === $query ) {
            return new \WP_Error(
                'bad_request',
                __( 'A search term is required.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 400 )
            );
        }

        $language = $this->requested_language( $request );
        if ( is_wp_error( $language ) ) {
            return $language;
        }

        $limit = (int) ( $request->get_param( 'limit' ) ?? self::DEFAULT_SEARCH_LIMIT );
        if ( $limit < 1 ) {
            $limit = self::DEFAULT_SEARCH_LIMIT;
        }
        if ( $limit > self::MAX_SEARCH_LIMIT ) {
            // Over-cap is an error, never a silent clamp (contract §2.1).
            return new \WP_Error(
                'over_cap',
                __( 'Too many results were requested.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 400 )
            );
        }

        $result = $this->source->search( $query, $language, $limit, $this->reader->current() );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->respond(
            array( 'results' => $result['results'] ),
            array_merge( $result['meta'], array( 'language' => $language ) )
        );
    }

    /**
     * GET /display/domains — domains available in one language.
     *
     * @param \WP_REST_Request $request Incoming request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_domains( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $language = $this->requested_language( $request );
        if ( is_wp_error( $language ) ) {
            return $language;
        }

        $result = $this->source->get_domains( $language );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->respond(
            array( 'domains' => $result['domains'] ),
            array( 'language' => $language )
        );
    }

    /**
     * GET /display/word-of-day — one deterministic entry per calendar day.
     *
     * @param \WP_REST_Request $request Incoming request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_word_of_day( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $language = $this->requested_language( $request );
        if ( is_wp_error( $language ) ) {
            return $language;
        }

        $result = $this->source->get_word_of_day( $language, $this->reader->current() );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->respond(
            array(
                'entry' => $result['entry'],
                'date'  => $result['date'],
            ),
            array( 'language' => $language )
        );
    }

    /**
     * Resolve which language this request is for.
     *
     * Every entry-bearing request names exactly one ISO 639-3 language, and that
     * language must be one this deployment is configured to show. A code outside
     * the configuration is refused — it is never quietly replaced with another
     * language.
     *
     * In `single` and `selected` modes the allowlist is the stored configuration,
     * which was validated against the Node when it was saved, so no upstream
     * round trip is spent restating it on every entry read. In `all_available`
     * mode the allowlist is whatever the Node reports, so it is asked. Either
     * way a language that has since disappeared upstream surfaces as a
     * controlled notice on `/display/languages` — which the UI reads first — and
     * as a controlled error here, never as a different language.
     *
     * @param \WP_REST_Request $request Incoming request.
     * @return string|\WP_Error
     */
    private function requested_language( \WP_REST_Request $request ): string|\WP_Error {
        $settings = $this->languages->stored();

        if ( DisplayLanguageSettings::MODE_ALL === $settings['mode'] ) {
            $resolved = $this->resolved_languages();
            if ( is_wp_error( $resolved ) ) {
                return $resolved;
            }
            $available = array_column( $resolved['languages'], 'code' );
            $default   = (string) $resolved['default'];
            $notice    = (string) $resolved['notice'];
        } else {
            $available = $settings['codes'];
            $default   = array() === $available ? '' : $available[0];
            $notice    = '';
        }

        if ( array() === $available ) {
            return new \WP_Error(
                'language_unavailable',
                '' !== $notice ? $notice : __( 'No dictionary language has been configured yet.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 503 )
            );
        }

        $requested = strtolower( sanitize_key( (string) ( $request->get_param( 'lang' ) ?? '' ) ) );

        if ( '' === $requested ) {
            return $default;
        }

        if ( ! in_array( $requested, $available, true ) ) {
            return new \WP_Error(
                'language_unavailable',
                __( 'That language is not available on this site.', 'sparxstar-3iatlas-dictionary' ),
                array( 'status' => 400 )
            );
        }

        return $requested;
    }

    /**
     * The configured languages, resolved against what the Node reports now.
     *
     * Memoised for the life of the request: one render must not ask the Node the
     * same question twice. Nothing is stored beyond the request.
     *
     * @return array{languages:array<int,array{code:string,name:string}>,default:string,selector:bool,missing:array<int,string>,notice:string}|\WP_Error
     */
    private function resolved_languages(): array|\WP_Error {
        if ( null !== $this->resolved ) {
            return $this->resolved;
        }

        $reported = $this->source->get_languages();
        if ( is_wp_error( $reported ) ) {
            return $reported;
        }

        $this->resolved = $this->languages->resolve( $reported['languages'] );

        return $this->resolved;
    }

    /**
     * Build the response envelope.
     *
     * `no-store`: these responses are metered per reader upstream, so a shared
     * cache serving them would hand out entries nobody was charged for.
     *
     * @param array<string,mixed> $data Response payload.
     * @param array<string,mixed> $meta Response metadata.
     * @return \WP_REST_Response
     */
    private function respond( array $data, array $meta = array() ): \WP_REST_Response {
        $response = new \WP_REST_Response(
            array(
                'success' => true,
                'data'    => $data,
                'meta'    => $meta,
            ),
            200
        );

        $response->header( 'Cache-Control', 'no-store, private' );
        $response->header( 'X-RateLimit-Remaining', (string) $this->get_rate_limit_remaining() );

        return $response;
    }
}
