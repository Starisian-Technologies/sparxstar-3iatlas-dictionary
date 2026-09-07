<?php
/**
 * The administrator screen for the display adapter.
 *
 * It carries exactly one product setting — which languages this deployment
 * shows (contract §6) — plus a read-only report of whether the connection is
 * configured. It never displays, stores or accepts a credential, a key, a
 * token or the Node's URL: those are wp-config.php constants, and the screen
 * names the constant that is missing, never its value.
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
 * Registers the display adapter settings screen.
 */
final class DisplayAdminScreen {

    /**
     * Settings page slug.
     *
     * @var string
     */
    private const PAGE = 'sparxstar-dictionary-display';

    /**
     * Settings group.
     *
     * @var string
     */
    private const GROUP = 'sparxstar_dictionary_display';

    /**
     * Parent menu — the dictionary CPT's own screen, so no new top-level menu
     * is added.
     *
     * @var string
     */
    private const PARENT = 'edit.php?post_type=aiwa-cpt-dictionary';

    /**
     * Deployment configuration.
     *
     * @var DisplayConfig
     */
    private DisplayConfig $config;

    /**
     * Language configuration.
     *
     * @var DisplayLanguageSettings
     */
    private DisplayLanguageSettings $languages;

    /**
     * Upstream data source, used only to validate codes at save time.
     *
     * @var DictionaryDisplaySourceInterface
     */
    private DictionaryDisplaySourceInterface $source;

    /**
     * Constructor.
     *
     * @param DisplayConfig                    $config    Deployment configuration.
     * @param DisplayLanguageSettings          $languages Language configuration.
     * @param DictionaryDisplaySourceInterface $source    Upstream data source.
     */
    public function __construct(
        DisplayConfig $config,
        DisplayLanguageSettings $languages,
        DictionaryDisplaySourceInterface $source
    ) {
        $this->config    = $config;
        $this->languages = $languages;
        $this->source    = $source;
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register_hooks(): void {
        add_action( 'admin_menu', array( $this, 'register_page' ) );
        add_action( 'admin_init', array( $this, 'register_setting' ) );
        add_action( 'admin_notices', array( $this, 'configuration_notice' ) );
    }

    /**
     * Add the settings submenu.
     *
     * @return void
     */
    public function register_page(): void {
        add_submenu_page(
            self::PARENT,
            __( 'Dictionary Display', 'sparxstar-3iatlas-dictionary' ),
            __( 'Display', 'sparxstar-3iatlas-dictionary' ),
            'manage_options',
            self::PAGE,
            array( $this, 'render_page' )
        );
    }

    /**
     * Register the single setting.
     *
     * @return void
     */
    public function register_setting(): void {
        register_setting(
            self::GROUP,
            DisplayLanguageSettings::OPTION,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize' ),
                'default'           => array(
                    'mode'  => DisplayLanguageSettings::MODE_SINGLE,
                    'codes' => array(),
                ),
            )
        );
    }

    /**
     * Validate the submitted setting against the languages the Node reports.
     *
     * A code the Node does not report is rejected here, at save time, with a
     * clear error — not stored and discovered later by a reader.
     *
     * @param mixed $input Submitted value.
     * @return array{mode:string,codes:array<int,string>}
     */
    public function sanitize( mixed $input ): array {
        $reported = array();
        $upstream = $this->source->get_languages();
        if ( ! is_wp_error( $upstream ) ) {
            $reported = array_column( $upstream['languages'], 'code' );
        }

        $outcome = $this->languages->validate( $input, $reported );

        foreach ( $outcome['errors'] as $index => $message ) {
            add_settings_error( DisplayLanguageSettings::OPTION, 'sparxstar_dict_lang_' . $index, $message, 'error' );
        }

        return $outcome['settings'];
    }

    /**
     * Admin notice listing configuration problems, by constant name.
     *
     * @return void
     */
    public function configuration_notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! $this->config->is_adapter_enabled() ) {
            return;
        }

        $problems = $this->config->problems();
        if ( array() === $problems ) {
            return;
        }

        echo '<div class="notice notice-error"><p>' .
            esc_html__( 'The 3iAtlas Dictionary display adapter is switched on but cannot operate:', 'sparxstar-3iatlas-dictionary' ) .
            '</p><ul style="list-style:disc;margin-left:2em">';
        foreach ( $problems as $problem ) {
            echo '<li>' . esc_html( $problem ) . '</li>';
        }
        echo '</ul></div>';
    }

    /**
     * Render the settings screen.
     *
     * @return void
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = $this->languages->stored();
        $problems = $this->config->problems();

        echo '<div class="wrap"><h1>' . esc_html__( 'Dictionary Display', 'sparxstar-3iatlas-dictionary' ) . '</h1>';

        echo '<h2>' . esc_html__( 'Connection', 'sparxstar-3iatlas-dictionary' ) . '</h2>';
        if ( array() === $problems ) {
            echo '<p>' . esc_html__( 'The connection to the dictionary service is configured.', 'sparxstar-3iatlas-dictionary' ) . '</p>';
        } else {
            echo '<ul style="list-style:disc;margin-left:2em">';
            foreach ( $problems as $problem ) {
                echo '<li>' . esc_html( $problem ) . '</li>';
            }
            echo '</ul>';
        }

        echo '<p>' . esc_html(
            sprintf(
                /* translators: %s: enabled or disabled, already translated. */
                __( 'Browse mode currently reads from: %s', 'sparxstar-3iatlas-dictionary' ),
                $this->config->is_adapter_enabled()
                    ? __( 'the dictionary service', 'sparxstar-3iatlas-dictionary' )
                    : __( 'this site&#8217;s legacy entries', 'sparxstar-3iatlas-dictionary' )
            )
        ) . '</p>';

        echo '<form action="options.php" method="post">';
        settings_fields( self::GROUP );

        $option = DisplayLanguageSettings::OPTION;

        echo '<h2>' . esc_html__( 'Languages', 'sparxstar-3iatlas-dictionary' ) . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row">' . esc_html__( 'Mode', 'sparxstar-3iatlas-dictionary' ) . '</th><td>';
        $modes = array(
            DisplayLanguageSettings::MODE_SINGLE   => __( 'One language (the language selector is hidden)', 'sparxstar-3iatlas-dictionary' ),
            DisplayLanguageSettings::MODE_SELECTED => __( 'A chosen set of languages', 'sparxstar-3iatlas-dictionary' ),
            DisplayLanguageSettings::MODE_ALL      => __( 'Every language the dictionary service reports', 'sparxstar-3iatlas-dictionary' ),
        );
        foreach ( $modes as $value => $label ) {
            printf(
                '<p><label><input type="radio" name="%1$s[mode]" value="%2$s" %3$s> %4$s</label></p>',
                esc_attr( $option ),
                esc_attr( $value ),
                checked( $settings['mode'], $value, false ),
                esc_html( $label )
            );
        }
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="sparxstar-dict-codes">' .
            esc_html__( 'Language codes', 'sparxstar-3iatlas-dictionary' ) .
            '</label></th><td>';
        printf(
            '<input type="text" class="regular-text" id="sparxstar-dict-codes" name="%1$s[codes]" value="%2$s">',
            esc_attr( $option ),
            esc_attr( implode( ', ', $settings['codes'] ) )
        );
        echo '<p class="description">' .
            esc_html__( 'ISO 639-3 codes, separated by commas. Codes the dictionary service does not report are rejected when you save. Language names are supplied by the dictionary service and are never set here.', 'sparxstar-3iatlas-dictionary' ) .
            '</p>';
        echo '</td></tr>';

        echo '</tbody></table>';

        submit_button();
        echo '</form></div>';
    }
}
