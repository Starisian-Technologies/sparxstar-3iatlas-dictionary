<?php
/**
 * Sparxstar3 IAtlas Dictionary.
 *
 * @package Sparxstar\3iAtlas\Dictionary
 */

declare(strict_types=1);
/**
 * Main plugin orchestrator file.
 *
 * @package Starisian\Sparxstar\IAtlas\core
 * @author Starisian Technologies (Max Barrett) <support@starisian.com>
 * @version 0.6.5
 * @since 0.1.0
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

namespace Starisian\Sparxstar\IAtlas\core;

use Starisian\Sparxstar\IAtlas\frontend\Sparxstar3IAtlasDictionaryForm;
use Starisian\Sparxstar\IAtlas\includes\Sparxstar3IAtlasPostTypes;
use Starisian\Sparxstar\IAtlas\core\Sparxstar3IAtlasDictionaryCore;
use Starisian\Sparxstar\IAtlas\includes\Sparxstar3IAtlasAutoLinker;
use WP_DEBUG;
use WP_Post;
use Throwable;
use RuntimeException;
use function defined;
use function add_action;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Sparxstar3IAtlasDictionary 
 * 
 * Main orchestrator for the plugin. Initializes dependencies, hooks, and components.
 */
final class Sparxstar3IAtlasDictionary {

    /**
     * Singleton instance of the class.
     *
     * @var Sparxstar3IAtlasDictionary|null
     */
    private static ?Sparxstar3IAtlasDictionary $instance = null;

    /**
     * Construct.
     *
     * @return mixed
     */
    private function __construct() {
        $this->sparxIAtlas_load_textdomain();
        $this->sparxIAtlas_load_dependencies();
        $this->sparxIAtlas_register_hooks();
    }

    /**
     * Gets the singleton instance of the class.
     *
     * @return Sparxstar3IAtlasDictionary The singleton instance.
     */
    public static function sparxIAtlas_get_instance(): Sparxstar3IAtlasDictionary {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registers the necessary actions and filters.
     *
     * @return void
     */
    private function sparxIAtlas_register_hooks(): void {
        add_action( 'init', array( $this, 'sparxIAtlas_register_shortcodes' ) );
        add_action( 'init', array( $this, 'sparxIAtlas_register_app_route' ) );
        // Late priority so the one-shot flush runs after every rewrite rule
        // (CPTs + the app route) has registered on init.
        add_action( 'init', array( $this, 'sparxIAtlas_maybe_flush_routes' ), 99 );
        add_filter( 'query_vars', array( $this, 'sparxIAtlas_register_query_var' ) );
        add_action( 'template_redirect', array( $this, 'sparxIAtlas_maybe_render_app_page' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'sparxIAtlas_register_assets' ) );
        if ( is_admin() ) {
            add_action( 'admin_notices', array( $this, 'sparxIAtlas_configuration_notices' ) );
        }
    }

    /**
     * Query var that flags a request for the standalone full-page app.
     *
     * @var string
     */
    private const APP_QUERY_VAR = 'sparxstar_dictionary_app';

    /**
     * The URL slug for the standalone full-page app (filterable).
     * Default: site.com/dictionary/. Returns a sanitized, non-empty slug.
     *
     * @return string
     */
    private function sparxIAtlas_app_slug(): string {
        $slug = sanitize_title( (string) apply_filters( 'sparxstar_dictionary_app_slug', 'dictionary' ) );
        return '' !== $slug ? $slug : 'dictionary';
    }

    /**
     * Builds the settings object localized to the frontend. Shared by the
     * shortcode and the standalone full-page route so both stay in lockstep.
     *
     * @return array<string,mixed>|null Null when the GraphQL endpoint is invalid.
     */
    private function sparxIAtlas_app_settings(): ?array {
        $graphql_url = (string) \site_url( SPARX_3IATLAS_GRAPHQL_SLUG );
        if ( '' === $graphql_url || filter_var( $graphql_url, FILTER_VALIDATE_URL ) === false ) {
            return null;
        }
        return array(
            'root_id'    => 'sparxstar-dictionary-root',
            'graphqlUrl' => $graphql_url,
            'restUrl'    => \untrailingslashit( \rest_url( 'sparxstar/v1/dictionary' ) ),
            'pageToken'  => \Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryRestApi::mint_initial_page_token(),
        );
    }

    /**
     * Registers the pretty rewrite rule for the standalone app page
     * (e.g. site.com/dictionary/). The non-pretty ?sparxstar_dictionary_app=1
     * form works without a permalink flush; this adds the clean URL on top.
     * Flushes once after activation via a one-shot option flag.
     *
     * @return void
     */
    public function sparxIAtlas_register_app_route(): void {
        add_rewrite_rule(
            '^' . $this->sparxIAtlas_app_slug() . '/?$',
            'index.php?' . self::APP_QUERY_VAR . '=1',
            'top'
        );
    }

    /**
     * Performs the one-shot rewrite flush scheduled at activation, once all
     * rules are registered. Runs at init priority 99 so it never flushes
     * before the CPT or app-route rules exist.
     *
     * @return void
     */
    public function sparxIAtlas_maybe_flush_routes(): void {
        if ( get_option( 'sparxstar_dict_flush_routes' ) ) {
            flush_rewrite_rules();
            delete_option( 'sparxstar_dict_flush_routes' );
        }
    }

    /**
     * Whitelists the app query var so WordPress preserves it through routing.
     *
     * @param array<int,string> $vars Registered public query vars.
     * @return array<int,string>
     */
    public function sparxIAtlas_register_query_var( array $vars ): array {
        $vars[] = self::APP_QUERY_VAR;
        return $vars;
    }

    /**
     * Renders the standalone full-page app and exits when the current request
     * targets the app route (pretty URL or ?sparxstar_dictionary_app=1).
     *
     * @return void
     */
    public function sparxIAtlas_maybe_render_app_page(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public view, no state change.
        $is_app = ( '' !== (string) get_query_var( self::APP_QUERY_VAR ) ) || isset( $_GET[ self::APP_QUERY_VAR ] );
        if ( ! $is_app ) {
            return;
        }
        $this->sparxIAtlas_render_app_page();
    }

    /**
     * Emits frame headers so the app page can be embedded as an iframe. Public,
     * read-only content is framable from anywhere by default; return a non-empty
     * array of origins from the 'sparxstar_dictionary_frame_ancestors' filter to
     * restrict embedding to an allowlist via CSP frame-ancestors.
     *
     * @return void
     */
    private function sparxIAtlas_send_frame_headers(): void {
        if ( headers_sent() ) {
            return;
        }
        $ancestors = apply_filters( 'sparxstar_dictionary_frame_ancestors', array() );
        $origins   = array();
        if ( is_array( $ancestors ) ) {
            foreach ( $ancestors as $ancestor ) {
                $origin = $this->sparxIAtlas_to_csp_origin( (string) $ancestor );
                if ( '' !== $origin ) {
                    $origins[ $origin ] = $origin; // Keyed for de-duplication.
                }
            }
        }

        // Remove any inherited restrictive policy so the embed is not blocked.
        header_remove( 'X-Frame-Options' );
        if ( array() !== $origins ) {
            header( "Content-Security-Policy: frame-ancestors 'self' " . implode( ' ', array_values( $origins ) ) );
        }
    }

    /**
     * Reduces a URL or origin to a CSP frame-ancestors source expression
     * (scheme://host[:port] — no path or query). CSP source expressions must be
     * origins, so anything with a path would make the header invalid. Returns
     * '' for values that can't be parsed into an http(s) origin.
     *
     * @param string $value Candidate origin or URL from the filter.
     * @return string Normalized origin, or '' when invalid.
     */
    private function sparxIAtlas_to_csp_origin( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }
        // Prepend a scheme so a bare host (example.com) parses as a host, not a path.
        if ( ! preg_match( '#^https?://#i', $value ) ) {
            $value = 'https://' . $value;
        }
        $parts = wp_parse_url( $value );
        if ( ! is_array( $parts ) || ! isset( $parts['host'] ) || '' === (string) $parts['host'] ) {
            return '';
        }
        $scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : 'https';
        if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
            return '';
        }
        $origin = $scheme . '://' . strtolower( (string) $parts['host'] );
        if ( isset( $parts['port'] ) ) {
            $origin .= ':' . (int) $parts['port'];
        }
        return $origin;
    }

    /**
     * Outputs the standalone, theme-free HTML document hosting the app and exits.
     * Only the dictionary's own assets are printed, so no theme CSS can leak in.
     * The page is never cached (the page token has a 1-hour TTL).
     *
     * @return void
     */
    private function sparxIAtlas_render_app_page(): void {
        try {
            $settings = $this->sparxIAtlas_app_settings();
            if ( null === $settings ) {
                nocache_headers();
                status_header( 503 );
                echo '<!DOCTYPE html><meta charset="utf-8"><p>' .
                    esc_html__( 'Dictionary endpoint is not available.', 'sparxstar-3iatlas-dictionary' ) .
                    '</p>';
                exit;
            }

            // template_redirect fires before wp_enqueue_scripts (which lives in
            // wp_head), and we short-circuit the theme — so register assets now.
            $this->sparxIAtlas_register_assets();

            nocache_headers();
            status_header( 200 );
            $this->sparxIAtlas_send_frame_headers();

            wp_enqueue_style( 'sparxstar-google-fonts' );
            wp_enqueue_style( 'sparxstar-dictionary-style' );
            wp_enqueue_script( 'sparxstar-dictionary-app' );
            wp_localize_script( 'sparxstar-dictionary-app', 'sparxstarDictionarySettings', $settings );

            $title = (string) apply_filters( 'sparxstar_dictionary_app_title', __( 'Dictionary', 'sparxstar-3iatlas-dictionary' ) );
            ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?php echo esc_html( $title ); ?></title>
<style>html,body{margin:0;padding:0;background:#F8F8F8}#sparxstar-dictionary-root{width:100%;min-height:100vh;min-height:100dvh}</style>
            <?php wp_print_styles( array( 'sparxstar-google-fonts', 'sparxstar-dictionary-style' ) ); ?>
</head>
<body>
<div id="sparxstar-dictionary-root" style="width:100%;min-height:100vh;min-height:100dvh;"></div>
            <?php wp_print_scripts( array( 'sparxstar-dictionary-app' ) ); ?>
</body>
</html>
            <?php
            exit;
        } catch ( \Throwable $throwable ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Deliberate diagnostic, already gated on WP_DEBUG above.
                error_log( '[Starisian 3IAtlas Dictionary]: Error rendering app page - ' . $throwable->getMessage() );
            }
            status_header( 500 );
            exit;
        }
    }

    /**
     * Displays admin notices for missing required configuration constants.
     *
     * @return void
     */
    public function sparxIAtlas_configuration_notices(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( ! defined( 'SPARXSTAR_DICT_PAGE_SECRET' ) || '' === (string) constant( 'SPARXSTAR_DICT_PAGE_SECRET' ) ) {
            echo '<div class="notice notice-error"><p>' .
                esc_html__( 'Sparxstar 3iAtlas Dictionary: SPARXSTAR_DICT_PAGE_SECRET is not defined or is empty in wp-config.php. Page tokens cannot be minted.', 'sparxstar-3iatlas-dictionary' ) .
                '</p></div>';
        }
    }

    /**
     * Registers the shortcode for the dictionary app.
     * 
     * @return void
     */
    public function sparxIAtlas_register_shortcodes(): void {
        add_shortcode( 'sparxstar_dictionary', array( $this, 'sparxIAtlas_render_app' ) );
    }

    /**
     * Registers and conditionally enqueues the assets for the dictionary app.
     * 
     * @return void
     */
    public function sparxIAtlas_register_assets(): void {
        try {
            // Register assets first so they can be enqueued later via shortcode or logic.
            wp_register_script(
                'sparxstar-dictionary-app',
                SPARX_3IATLAS_URL . 'assets/js/sparxstar-3iatlas-dictionary-app.min.js',
                array(),
                SPARX_3IATLAS_VERSION,
                true
            );

            // Inter from Google Fonts — wide African-language coverage; system fonts are the fallback/swap.
            wp_register_style(
                'sparxstar-google-fonts',
                SPARX_3IATLAS_GOOGLE_FONTS_URL,
                array(),
                null
            );

            wp_register_style(
                'sparxstar-dictionary-style',
                SPARX_3IATLAS_URL . 'assets/css/sparxstar-3iatlas-dictionary-app.min.css',
                array( 'sparxstar-google-fonts' ),
                esc_html( SPARX_3IATLAS_VERSION )
            );
        } catch ( \Throwable $throwable ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Deliberate diagnostic, already gated on WP_DEBUG above.
                error_log( '[Starisian 3IAtlas Dictionary]: Error registering/enqueuing assets - ' . $throwable->getMessage() );
            }
        }
    }

    /**
     * Shortcode callback to render the dictionary app.
     * Usage: [sparxstar_dictionary title="My Dictionary"]
     * 
     * @param array|string $atts Shortcode attributes.
     * @return string The rendered shortcode content.
     */
    public function sparxIAtlas_render_app( $atts = array() ): string {
        try {
            $settings = $this->sparxIAtlas_app_settings();
            if ( null === $settings ) {
                return '<p>' . esc_html__( 'Dictionary endpoint is not available.', 'sparxstar-3iatlas-dictionary' ) . '</p>';
            }

            $atts = shortcode_atts(
                array(
                    'title' => 'Dictionary',
                ),
                $atts,
                'sparxstar_dictionary'
            );

            // Pass settings to the frontend (variable name uses capital S to match the React app).
            wp_localize_script( 'sparxstar-dictionary-app', 'sparxstarDictionarySettings', $settings );
            // Ensure assets are enqueued (in case they weren't caught by the global check, e.g., in a widget).
            wp_enqueue_script( 'sparxstar-dictionary-app' );
            wp_enqueue_style( 'sparxstar-dictionary-style' );
        } catch ( \Throwable $throwable ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Deliberate diagnostic, already gated on WP_DEBUG above.
                error_log( '[Starisian 3IAtlas Dictionary]: Error rendering shortcode - ' . $throwable->getMessage() );
            }
            return '<p>' . esc_html__( 'An error occurred while loading the dictionary.', 'sparxstar-3iatlas-dictionary' ) . '</p>';
        }

        return '<div id="sparxstar-dictionary-root" style="width:100%;min-height:100vh;min-height:100dvh;" data-title="' . esc_attr( $atts['title'] ) . '"></div>';
    }

    /**
     * Loads the plugin dependencies and initializes core components.
     *
     * @return void
     */
    private function sparxIAtlas_load_dependencies(): void {
        try {
            // Instantiate Post Types on init (handled by class constructor hook)
            // Asset Protection Spec §1.3 — registered before the post type so the
            // register_post_type_args filter is in place when init fires.
            if ( class_exists( \Starisian\Sparxstar\IAtlas\includes\Sparxstar3IAtlasSurfaceLockdown::class ) ) {
                ( new \Starisian\Sparxstar\IAtlas\includes\Sparxstar3IAtlasSurfaceLockdown() )->register_hooks();
            }

            if ( class_exists( Sparxstar3IAtlasPostTypes::class ) ) {
                new Sparxstar3IAtlasPostTypes();
            }

            // Only load frontend components if not in admin area.
            if ( ! is_admin() ) {
                if ( class_exists( Sparxstar3IAtlasDictionaryCore::class ) ) {
                    // Instantiate Core logic.
                    Sparxstar3IAtlasDictionaryCore::sparxIAtlas_get_instance();
                }

                if ( class_exists( Sparxstar3IAtlasDictionaryForm::class ) && is_user_logged_in() ) {
                    // Instantiate Form if needed.
                    new Sparxstar3IAtlasDictionaryForm();
                }
            }

            // REST API endpoints.
            if ( class_exists( \Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryRestApi::class ) ) {
                ( new \Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryRestApi() )->register_hooks();
            }

            if ( class_exists( \Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryTts::class ) ) {
                ( new \Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryTts() )->register_hooks();
            }

            if ( class_exists( \Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionarySpellChecker::class ) ) {
                ( new \Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionarySpellChecker() )->register_hooks();
            }

            // CORS handler — must be registered early (priority 1 on rest_api_init).
            if ( class_exists( \Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryCors::class ) ) {
                ( new \Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryCors() )->register_hooks();
            }

            // Browser-origin tripwire (spec §1.1) — observes, never blocks.
            if ( class_exists( \Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryTripwire::class ) ) {
                ( new \Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryTripwire() )->register_hooks();
            }

            // Rolling unique-entry budget maintenance (spec §1.2).
            if ( class_exists( \Starisian\Sparxstar\IAtlas\api\auth\UniqueEntryBudget::class ) ) {
                \Starisian\Sparxstar\IAtlas\api\auth\UniqueEntryBudget::register_hooks();
            }

            // Instantiate Auto Linker.
            if ( class_exists( Sparxstar3IAtlasAutoLinker::class ) ) {
                new Sparxstar3IAtlasAutoLinker();
            }

            // WP-CLI commands — only register when CLI is active.
            if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) && class_exists( \Starisian\Sparxstar\IAtlas\cli\Sparxstar3IAtlasDictionaryCliCommands::class ) ) {
                $cli_handler = new \Starisian\Sparxstar\IAtlas\cli\Sparxstar3IAtlasDictionaryCliCommands();
                \WP_CLI::add_command( 'sparxstar-dict key', $cli_handler );

                if ( class_exists( \Starisian\Sparxstar\IAtlas\cli\Sparxstar3IAtlasSystemCredentialCliCommands::class ) ) {
                    \WP_CLI::add_command(
                        'sparxstar-dict system',
                        new \Starisian\Sparxstar\IAtlas\cli\Sparxstar3IAtlasSystemCredentialCliCommands()
                    );
                }
            }
        } catch ( \Throwable $throwable ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Deliberate diagnostic, already gated on WP_DEBUG above.
                error_log( '[Starisian 3IAtlas Dictionary]: Error loading dependencies - ' . $throwable->getMessage() );
            }
        }
    }

    /**
     * Loads the plugin textdomain for translation.
     *
     * @return void
     */
    private function sparxIAtlas_load_textdomain(): void {
        load_plugin_textdomain( 'sparxstar-3iatlas-dictionary', false, dirname( plugin_basename( SPARX_3IATLAS_PATH . 'sparxstar-3iatlas-dictionary.php' ) ) . '/languages' );
    }

    // Prevent cloning and unserializing.
    private function __clone(): never {
        _doing_it_wrong( __FUNCTION__, 'Cloning this object is forbidden.', esc_html( SPARX_3IATLAS_VERSION ) );
        throw new \RuntimeException( 'Cloning is not allowed.' );
    }

    /**
     * Wakeup.
     *
     * @throws \RuntimeException Always — this object must not be duplicated.
     * @return never
     */
    public function __wakeup(): never {
        _doing_it_wrong( __FUNCTION__, 'Serializing this object is forbidden.', esc_html( SPARX_3IATLAS_VERSION ) );
        throw new \RuntimeException( 'Serializing is not allowed.' );
    }

    /**
     * Unserialize.
     *
     * @param array $data Data.
     * @throws \RuntimeException Always — this object must not be duplicated.
     * @return never
     */
    public function __unserialize( array $data ): never {
        _doing_it_wrong( __FUNCTION__, 'Unserializing this object is forbidden.', esc_html( SPARX_3IATLAS_VERSION ) );
        throw new \RuntimeException( 'Unserializing is not allowed.' );
    }
}
