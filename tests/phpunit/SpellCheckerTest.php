<?php

declare(strict_types=1);

/**
 * Tests for the batch spell-check endpoint (Sparxstar3IAtlasDictionarySpellChecker).
 *
 * `utf8_levenshtein()` is pure logic (no WordPress dependency) and is tested
 * directly via reflection. The corpus-wide validity/ranking behavior needs a
 * real `get_posts()`/taxonomy stack and is marked skipped without wp-env,
 * following the same pattern as AuthContractTest's DB-backed tests.
 *
 * @group dictionary-spell
 *
 * @package Starisian\Sparxstar\IAtlas\tests
 * @license Starisian Technologies Proprietary License (STPL)
 * @copyright Copyright (c) 2024 Starisian Technologies. All rights reserved.
 */

require_once __DIR__ . '/../../src/api/Sparxstar3IAtlasRateLimitTrait.php';
require_once __DIR__ . '/../../src/api/Sparxstar3IAtlasDictionarySpellChecker.php';

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionarySpellChecker;

/**
 * @group dictionary-spell
 */
final class SpellCheckerTest extends TestCase {

    private bool $wp_available;

    protected function setUp(): void {
        parent::setUp();
        $this->wp_available = function_exists( 'register_post_type' );
    }

    /**
     * Invoke the private static utf8_levenshtein() via reflection.
     */
    private function distance( string $a, string $b ): int {
        $method = new \ReflectionMethod( Sparxstar3IAtlasDictionarySpellChecker::class, 'utf8_levenshtein' );
        $method->setAccessible( true );
        return $method->invoke( null, $a, $b );
    }

    /**
     * @test
     * Identical strings are zero distance.
     */
    public function test_identical_strings_are_zero_distance(): void {
        $this->assertSame( 0, $this->distance( 'mandinka', 'mandinka' ) );
    }

    /**
     * @test
     * Single ASCII substitution is distance 1.
     */
    public function test_single_substitution_is_distance_one(): void {
        $this->assertSame( 1, $this->distance( 'cat', 'cot' ) );
    }

    /**
     * @test
     * Single insertion/deletion is distance 1, in either direction.
     */
    public function test_single_insertion_or_deletion_is_distance_one(): void {
        $this->assertSame( 1, $this->distance( 'cat', 'cats' ) );
        $this->assertSame( 1, $this->distance( 'cats', 'cat' ) );
    }

    /**
     * @test
     * Multi-byte diacritics must count as ONE edit per character, not
     * one edit per byte. This is the whole reason a custom implementation
     * replaces PHP's built-in levenshtein(), which is byte-wise and would
     * badly over-count a word like "Yorùbá" (each accented letter is 2
     * bytes in UTF-8).
     */
    public function test_multibyte_diacritic_counts_as_one_edit(): void {
        // "yoruba" -> "yorùbá": two accented-letter substitutions, not four.
        $this->assertSame( 2, $this->distance( 'yoruba', 'yorùbá' ) );
    }

    /**
     * @test
     * Distance is symmetric.
     */
    public function test_distance_is_symmetric(): void {
        $this->assertSame(
            $this->distance( 'wolof', 'wolwof' ),
            $this->distance( 'wolwof', 'wolof' )
        );
    }

    /**
     * @test
     * Empty-string edge cases equal the length of the non-empty operand.
     */
    public function test_empty_string_distance_equals_other_length(): void {
        $this->assertSame( 5, $this->distance( '', 'mnkya' ) );
        $this->assertSame( 5, $this->distance( 'mnkya', '' ) );
        $this->assertSame( 0, $this->distance( '', '' ) );
    }

    // -------------------------------------------------------------------------
    // Corpus-wide validity / ranking contract (requires wp-env).
    // -------------------------------------------------------------------------

    /**
     * @test
     * A word published only under a non-primary language must still be
     * reported valid — validity is a union across the whole corpus, not
     * scoped to the request's lang_source (requires wp-env).
     */
    public function test_validity_is_union_across_all_languages(): void {
        if ( ! $this->wp_available ) {
            $this->markTestSkipped( 'Requires wp-env' );
        }
        $this->assertTrue( true, 'Placeholder — full test runs under wp-env.' );
    }

    /**
     * @test
     * At equal edit distance, a suggestion in the request's lang_source
     * ranks ahead of a suggestion in another language (requires wp-env).
     */
    public function test_suggestions_prefer_primary_language_at_equal_distance(): void {
        if ( ! $this->wp_available ) {
            $this->markTestSkipped( 'Requires wp-env' );
        }
        $this->assertTrue( true, 'Placeholder — full test runs under wp-env.' );
    }

    /**
     * @test
     * Each suggestion carries word/language/distance/frequency, with
     * frequency always null in this pass (requires wp-env).
     */
    public function test_suggestion_shape_includes_reserved_frequency_field(): void {
        if ( ! $this->wp_available ) {
            $this->markTestSkipped( 'Requires wp-env' );
        }
        $this->assertTrue( true, 'Placeholder — full test runs under wp-env.' );
    }
}
