<?php

declare( strict_types=1 );

/**
 * Tests for Sparxstar3IAtlasAutoLinker::process_replacements()
 *
 * Exercises chunked-regex processing introduced to fix:
 *   "preg_replace_callback(): Compilation failed: regular expression is too large"
 *
 * WordPress functions used by the method under test are stubbed below so that
 * these unit tests run without a WordPress install.
 *
 * @package Starisian\Sparxstar\IAtlas\tests
 */

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\IAtlas\includes\Sparxstar3IAtlasAutoLinker;

// ---------------------------------------------------------------------------
// WordPress function stubs (only what process_replacements() calls).
// ---------------------------------------------------------------------------

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter(): void {}
}
if ( ! function_exists( 'add_action' ) ) {
    function add_action(): void {}
}
if ( ! function_exists( 'get_the_ID' ) ) {
    /** Returns a sentinel post ID that never matches a linked post. */
    function get_the_ID(): int {
        return 9999;
    }
}
if ( ! function_exists( 'url_to_postid' ) ) {
    /**
     * Maps fake test URLs to deterministic post IDs.
     * All test URLs follow the pattern "https://example.com/term-NNN/".
     */
    function url_to_postid( string $url ): int {
        if ( preg_match( '/term-(\d+)/', $url, $m ) ) {
            return (int) $m[1];
        }
        return 0;
    }
}
if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( string $url ): string {
        return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
    }
}
if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( string $text ): string {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( string $key ): mixed { return false; }
}
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( string $key, mixed $value, int $expiry = 0 ): bool { return true; }
}
if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( string $key ): bool { return true; }
}
if ( ! function_exists( 'home_url' ) ) {
    function home_url(): string { return 'https://example.com'; }
}
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/' );
}

// Load the class under test directly from source so we always exercise the
// current working code (not a build artefact copy).
require_once __DIR__ . '/../../src/includes/Sparxstar3IAtlasAutoLinker.php';

// ---------------------------------------------------------------------------
// Helper: build a term list of $count terms, optionally with a specific term.
// ---------------------------------------------------------------------------

/**
 * @return array<string,string>
 */
function build_terms( int $count, string $extra_term = '', string $extra_url = '' ): array {
    $terms = [];

    // Build from longest to shortest so the sort order in get_dictionary_terms()
    // is respected for the test fixtures.
    for ( $i = $count; $i >= 1; $i-- ) {
        $term        = 'TestTerm' . str_pad( (string) $i, 4, '0', STR_PAD_LEFT );
        $terms[$term] = "https://example.com/term-{$i}/";
    }

    if ( '' !== $extra_term ) {
        // Prepend so it appears in the first chunk (longest-first ordering).
        $terms = array_merge( [ $extra_term => $extra_url ], $terms );
    }

    return $terms;
}

// ---------------------------------------------------------------------------

final class AutoLinkerChunkTest extends TestCase {

    /**
     * Invoke the private process_replacements() method via reflection.
     *
     * @param array<string,string> $terms
     */
    private function invokeProcessReplacements( string $content, array $terms ): string {
        $linker = new Sparxstar3IAtlasAutoLinker();
        $ref    = new \ReflectionClass( $linker );
        $method = $ref->getMethod( 'process_replacements' );
        $method->setAccessible( true );
        return $method->invoke( $linker, $content, $terms );
    }

    // -----------------------------------------------------------------------
    // Test 1 – Empty terms returns original content unchanged.
    // -----------------------------------------------------------------------
    public function testEmptyTermsReturnsOriginalContent(): void {
        $content = '<p>Hello World</p>';
        $result  = $this->invokeProcessReplacements( $content, [] );
        $this->assertSame( $content, $result );
    }

    // -----------------------------------------------------------------------
    // Test 2 – A small list (below REGEX_CHUNK_SIZE) links a term correctly.
    // -----------------------------------------------------------------------
    public function testSmallTermListLinksCorrectly(): void {
        $terms   = [ 'Hospitality' => 'https://example.com/term-1/' ];
        $content = '<p>Learn about Hospitality management today.</p>';
        $result  = $this->invokeProcessReplacements( $content, $terms );

        $this->assertStringContainsString(
            '<a href="https://example.com/term-1/" class="aiwa-dictionary-link"',
            $result
        );
        $this->assertStringContainsString( 'Hospitality', $result );
    }

    // -----------------------------------------------------------------------
    // Test 3 – Large term list (exceeds REGEX_CHUNK_SIZE of 200) links a
    //           specific term correctly without triggering a PCRE error.
    //           This is the primary regression test for the reported bug.
    // -----------------------------------------------------------------------
    public function testLargeTermListLinksWithoutPcreError(): void {
        // 500 terms — well above the REGEX_CHUNK_SIZE = 200 constant.
        $terms   = build_terms( 500, 'Pharmacy', 'https://example.com/pharmacy/' );
        $content = '<p>Pharmacy is an important subject.</p>';

        $result  = $this->invokeProcessReplacements( $content, $terms );

        // The term must have been linked.
        $this->assertStringContainsString(
            '<a href="https://example.com/pharmacy/" class="aiwa-dictionary-link"',
            $result,
            'Term "Pharmacy" should be linked even when the total term list exceeds REGEX_CHUNK_SIZE.'
        );

        // No PCRE error (preg_last_error() should be 0 = PREG_NO_ERROR).
        $this->assertSame( \PREG_NO_ERROR, preg_last_error(), 'PCRE should report no error after processing.' );
    }

    // -----------------------------------------------------------------------
    // Test 4 – Existing <a> links are never double-linked.
    // -----------------------------------------------------------------------
    public function testExistingLinksAreNotDoubleLinked(): void {
        $terms   = [ 'Nursing' => 'https://example.com/term-1/' ];
        $already = '<a href="https://example.com/term-1/">Nursing</a>';
        $content = "<p>See also: {$already} for details.</p>";

        $result  = $this->invokeProcessReplacements( $content, $terms );

        // Should contain exactly one opening <a ...> for "Nursing", not two.
        $count = substr_count( $result, '<a ' );
        $this->assertSame(
            1,
            $count,
            'An already-linked term must not be wrapped in a second <a> tag.'
        );
    }

    // -----------------------------------------------------------------------
    // Test 5 – Terms inside headings are skipped.
    // -----------------------------------------------------------------------
    public function testTermsInsideHeadingsAreSkipped(): void {
        $terms   = [ 'Cardiology' => 'https://example.com/term-1/' ];
        $content = '<h2>Cardiology Overview</h2><p>Cardiology is a field.</p>';

        $result  = $this->invokeProcessReplacements( $content, $terms );

        // The heading text must remain unchanged.
        $this->assertStringContainsString( '<h2>Cardiology Overview</h2>', $result );

        // The body paragraph term must be linked.
        $this->assertStringContainsString(
            '<a href="https://example.com/term-1/" class="aiwa-dictionary-link"',
            $result
        );
    }

    // -----------------------------------------------------------------------
    // Test 6 – Very large term list (>1,200, i.e., multiple full chunks of
    //           200) links a term in the last chunk correctly.
    // -----------------------------------------------------------------------
    public function testVeryLargeTermListLinksLastChunkTerm(): void {
        // 1,200 entries puts the last entry in the 6th chunk (chunk index 5).
        $terms      = build_terms( 1200 );
        $last_term  = 'TestTerm0001'; // lowest numeric suffix, so last in longest-first order
        $last_url   = 'https://example.com/term-1/';

        // Confirm the term is actually present in our fixture.
        $this->assertArrayHasKey( $last_term, $terms );

        $content = "<p>We discuss {$last_term} in detail.</p>";
        $result  = $this->invokeProcessReplacements( $content, $terms );

        $this->assertStringContainsString(
            "<a href=\"{$last_url}\" class=\"aiwa-dictionary-link\"",
            $result,
            "Term in the last chunk of a 1,200-term list must still be linked."
        );
    }
}
