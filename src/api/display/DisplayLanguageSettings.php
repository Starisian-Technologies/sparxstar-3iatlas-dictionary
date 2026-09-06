<?php
/**
 * The one administrator setting that decides which languages this deployment
 * shows (contract §6).
 *
 * ISO 639-3 codes are stored. Names are never stored and never mapped locally:
 * they are whatever the Node reports, including a Node that reports a code as
 * its own name. There is no default language, no built-in language list, and no
 * language or domain name anywhere in this plugin — an unconfigured deployment
 * shows a configuration notice, not a guess.
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
 * Reads, validates and resolves the display language configuration.
 */
final class DisplayLanguageSettings {

    /**
     * Option storing the setting.
     *
     * @var string
     */
    public const OPTION = 'sparxstar_dict_display_languages';

    /**
     * One language; the public language selector is hidden.
     *
     * @var string
     */
    public const MODE_SINGLE = 'single';

    /**
     * An allowlist of languages; only those are shown.
     *
     * @var string
     */
    public const MODE_SELECTED = 'selected';

    /**
     * Every language the Node reports.
     *
     * @var string
     */
    public const MODE_ALL = 'all_available';

    /**
     * The three legal modes.
     *
     * @return array<int,string>
     */
    public static function modes(): array {
        return array( self::MODE_SINGLE, self::MODE_SELECTED, self::MODE_ALL );
    }

    /**
     * The stored setting, normalised.
     *
     * @return array{mode:string,codes:array<int,string>}
     */
    public function stored(): array {
        $raw = get_option( self::OPTION, array() );
        if ( ! is_array( $raw ) ) {
            $raw = array();
        }

        $mode = isset( $raw['mode'] ) && is_string( $raw['mode'] ) ? $raw['mode'] : '';
        if ( ! in_array( $mode, self::modes(), true ) ) {
            $mode = self::MODE_SINGLE;
        }

        return array(
            'mode'  => $mode,
            'codes' => self::clean_codes( $raw['codes'] ?? array() ),
        );
    }

    /**
     * Validate submitted settings against the languages the Node reports.
     *
     * A configured code the Node does not report is REJECTED at save time with a
     * clear error, rather than stored and discovered at render time.
     *
     * @param mixed             $input    Submitted value.
     * @param array<int,string> $reported Codes the Node currently reports.
     * @return array{settings:array{mode:string,codes:array<int,string>},errors:array<int,string>}
     */
    public function validate( mixed $input, array $reported ): array {
        $errors = array();

        $mode = '';
        if ( is_array( $input ) && isset( $input['mode'] ) && is_string( $input['mode'] ) ) {
            $mode = $input['mode'];
        }
        if ( ! in_array( $mode, self::modes(), true ) ) {
            $mode     = self::MODE_SINGLE;
            $errors[] = __( 'Unrecognised language mode; falling back to a single language.', 'sparxstar-3iatlas-dictionary' );
        }

        $codes = self::clean_codes( is_array( $input ) ? ( $input['codes'] ?? array() ) : array() );

        if ( self::MODE_ALL === $mode ) {
            // The choices are whatever the Node reports; nothing is stored.
            return array(
                'settings' => array(
                    'mode'  => $mode,
                    'codes' => array(),
                ),
                'errors'   => $errors,
            );
        }

        if ( array() === $codes ) {
            $errors[] = __( 'Enter at least one ISO 639-3 language code.', 'sparxstar-3iatlas-dictionary' );
        }

        if ( self::MODE_SINGLE === $mode && count( $codes ) > 1 ) {
            $codes    = array( $codes[0] );
            $errors[] = __( 'Single-language mode takes one code; the extra codes were discarded.', 'sparxstar-3iatlas-dictionary' );
        }

        if ( array() !== $reported ) {
            $unknown = array_values( array_diff( $codes, $reported ) );
            if ( array() !== $unknown ) {
                $errors[] = sprintf(
                    /* translators: %s: comma-separated list of language codes. */
                    __( 'The dictionary service does not report these language codes: %s. They were not saved.', 'sparxstar-3iatlas-dictionary' ),
                    implode( ', ', $unknown )
                );
                $codes = array_values( array_intersect( $codes, $reported ) );
            }
        } else {
            $errors[] = __( 'The dictionary service could not be reached, so the codes could not be verified.', 'sparxstar-3iatlas-dictionary' );
        }

        return array(
            'settings' => array(
                'mode'  => $mode,
                'codes' => $codes,
            ),
            'errors'   => $errors,
        );
    }

    /**
     * Resolve the configuration against the languages the Node reports now.
     *
     * A language that was valid and is no longer reported degrades to a
     * controlled notice. It never produces a fatal, a blank page, or a silent
     * switch to a different language — serving one language to a reader who
     * asked for another is a worse failure than an honest error (contract §6).
     *
     * @param array<int,array{code:string,name:string}> $reported Terms reported by the Node.
     * @return array{languages:array<int,array{code:string,name:string}>,default:string,selector:bool,missing:array<int,string>,notice:string}
     */
    public function resolve( array $reported ): array {
        $settings   = $this->stored();
        $by_code    = array();
        $reported_c = array();

        foreach ( $reported as $term ) {
            if ( is_array( $term ) && isset( $term['code'] ) && is_string( $term['code'] ) && '' !== $term['code'] ) {
                $by_code[ $term['code'] ] = array(
                    'code' => $term['code'],
                    'name' => isset( $term['name'] ) && is_string( $term['name'] ) && '' !== $term['name'] ? $term['name'] : $term['code'],
                );
                $reported_c[]             = $term['code'];
            }
        }

        if ( self::MODE_ALL === $settings['mode'] ) {
            $languages = array_values( $by_code );
            return array(
                'languages' => $languages,
                'default'   => array() === $languages ? '' : $languages[0]['code'],
                'selector'  => true,
                'missing'   => array(),
                'notice'    => array() === $languages
                    ? __( 'The dictionary service reports no languages yet.', 'sparxstar-3iatlas-dictionary' )
                    : '',
            );
        }

        if ( array() === $settings['codes'] ) {
            return array(
                'languages' => array(),
                'default'   => '',
                'selector'  => false,
                'missing'   => array(),
                'notice'    => __( 'No dictionary language has been configured yet.', 'sparxstar-3iatlas-dictionary' ),
            );
        }

        $languages = array();
        $missing   = array();
        foreach ( $settings['codes'] as $code ) {
            if ( isset( $by_code[ $code ] ) ) {
                $languages[] = $by_code[ $code ];
            } else {
                $missing[] = $code;
            }
        }

        $notice = '';
        if ( array() !== $missing ) {
            $notice = array() === $languages
                ? __( 'The configured dictionary language is no longer available.', 'sparxstar-3iatlas-dictionary' )
                : __( 'One of the configured dictionary languages is no longer available.', 'sparxstar-3iatlas-dictionary' );
        }

        return array(
            'languages' => $languages,
            // Empty when nothing configured is available: the caller renders the
            // notice rather than substituting a language nobody asked for.
            'default'   => array() === $languages ? '' : $languages[0]['code'],
            'selector'  => self::MODE_SELECTED === $settings['mode'],
            'missing'   => $missing,
            'notice'    => $notice,
        );
    }

    /**
     * Normalise a submitted or stored code list: ISO 639-3 only, de-duplicated,
     * order preserved.
     *
     * @param mixed $value Raw codes — an array or a comma/space separated string.
     * @return array<int,string>
     */
    private static function clean_codes( mixed $value ): array {
        if ( is_string( $value ) ) {
            $value = preg_split( '/[\s,]+/', $value ) ?: array();
        }
        if ( ! is_array( $value ) ) {
            return array();
        }

        $codes = array();
        foreach ( $value as $candidate ) {
            if ( ! is_string( $candidate ) ) {
                continue;
            }
            $code = strtolower( trim( $candidate ) );
            if ( DisplayText::is_iso_639_3( $code ) && ! in_array( $code, $codes, true ) ) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
