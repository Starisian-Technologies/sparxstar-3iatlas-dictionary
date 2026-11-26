<?php
/**
 * Text handling for language data crossing the display seam.
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
 * Normalisation helpers for lexical text.
 */
final class DisplayText {

    /**
     * Return a string in Unicode NFC.
     *
     * NFC is canonical for every piece of language data on this platform. NFKC
     * and NFKD are never used: compatibility decomposition rewrites characters
     * that carry meaning in West African orthographies (ŋ ɓ ɗ ñ ɲ ʔ), and a
     * dictionary that mangles its own headwords is worse than one that is down.
     *
     * When ext-intl is unavailable the value is returned UNCHANGED. Passing text
     * through untouched is safe; guessing at a normalisation is not.
     *
     * @param mixed $value Candidate value.
     * @return string NFC-normalised string, or '' when the value is not a string.
     */
    public static function nfc( mixed $value ): string {
        if ( ! is_string( $value ) ) {
            return '';
        }

        if ( ! class_exists( '\Normalizer' ) ) {
            return $value;
        }

        if ( \Normalizer::isNormalized( $value, \Normalizer::FORM_C ) ) {
            return $value;
        }

        $normalized = \Normalizer::normalize( $value, \Normalizer::FORM_C );

        return is_string( $normalized ) ? $normalized : $value;
    }

    /**
     * NFC-normalise every string in a list, dropping anything else.
     *
     * @param mixed $value Candidate list.
     * @return array<int,string>
     */
    public static function nfc_list( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $out = array();
        foreach ( $value as $item ) {
            if ( is_string( $item ) && '' !== $item ) {
                $out[] = self::nfc( $item );
            }
        }

        return $out;
    }

    /**
     * Validate an entry slug without mangling it.
     *
     * `sanitize_title()` is deliberately NOT used here. It strips accents and
     * transliterates, which is exactly the transformation that destroys West
     * African orthography — a slug carrying ŋ, ɓ, ɗ, ñ, ɲ or ʔ must survive
     * intact or it addresses a different entry, or none. The slug is only ever
     * used as a URL-encoded query value, so the check is for structure
     * characters and control characters, not for a restricted alphabet.
     *
     * @param string $value Candidate slug.
     * @return string The NFC slug, or '' when it is not usable.
     */
    public static function slug( string $value ): string {
        $value = self::nfc( trim( $value ) );

        if ( '' === $value || strlen( $value ) > 200 ) {
            return '';
        }

        if ( 1 === preg_match( '/[\x00-\x1F\x7F\/\?#&=%\s]/u', $value ) ) {
            return '';
        }

        return $value;
    }

    /**
     * Whether a string is a well-formed ISO 639-3 code.
     *
     * Codes are what this plugin stores; names always come from the Node (§5).
     *
     * @param string $code Candidate code.
     * @return bool
     */
    public static function is_iso_639_3( string $code ): bool {
        return 1 === preg_match( '/^[a-z]{3}$/', $code );
    }
}
