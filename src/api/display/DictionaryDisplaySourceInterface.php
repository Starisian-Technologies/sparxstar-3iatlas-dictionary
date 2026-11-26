<?php
/**
 * The upstream data source for Browse mode.
 *
 * This is the seam's narrow waist. Everything above it — the same-origin REST
 * adapter, the admin language setting, the React app — speaks only these five
 * methods and the plain view-model arrays they return. Route shapes, query
 * strings, bearer tokens and the upstream `{success,data,meta}` envelope stop
 * here and never travel further into the plugin.
 *
 * Every method returns a plain array on success or a WP_Error on ANY failure —
 * timeout, unreachable service, 401, 403, 429, 404, malformed JSON, an HTML
 * body, or an oversized response. There is no other outcome: a blank page, a
 * fatal, or a PHP notice carrying upstream detail is a defect (DICT-ADR-001).
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
 * Reads the authoritative dictionary for display.
 *
 * Two invariants bind every implementation:
 *
 * 1. **Nothing it returns is ever persisted.** No CPT row, no post meta, no
 *    taxonomy term, no option, no transient may hold a dictionary record
 *    (contract §9). The access token is the only upstream value this plugin
 *    stores.
 * 2. **Every entry-bearing call names exactly one ISO 639-3 language** and
 *    carries an opaque reader reference. There is no all-entries call, no
 *    pagination parameter, and no exact count (contract §2.1).
 */
interface DictionaryDisplaySourceInterface {

    /**
     * Languages that have a compiled display projection.
     *
     * The only language-less call. It returns metadata, never words: "all
     * languages available" means all may be SELECTED, never fetch them all.
     *
     * Names are whatever the source reports, including a source that reports a
     * code as its own name. They are rendered verbatim: this plugin never maps
     * a code to a name locally (contract §5).
     *
     * @return array{languages:array<int,array{code:string,name:string}>,meta:array<string,mixed>}|\WP_Error
     */
    public function get_languages(): array|\WP_Error;

    /**
     * One entry, addressed by stable slug within one language.
     *
     * @param string $slug       Stable entry slug.
     * @param string $language   ISO 639-3 code. Required — a slug is only unique within a language.
     * @param string $reader_ref Opaque reader reference for the per-reader sub-budget.
     * @return array{entry:array<string,mixed>,meta:array<string,mixed>}|\WP_Error
     */
    public function get_entry( string $slug, string $language, string $reader_ref ): array|\WP_Error;

    /**
     * Bounded search within one language.
     *
     * @param string $query      The reader's query.
     * @param string $language   ISO 639-3 code.
     * @param int    $limit      Maximum results. Over the source's cap is an error, never a silent clamp.
     * @param string $reader_ref Opaque reader reference.
     * @return array{results:array<int,array<string,mixed>>,meta:array<string,mixed>}|\WP_Error
     */
    public function search( string $query, string $language, int $limit, string $reader_ref ): array|\WP_Error;

    /**
     * Domains available within one language.
     *
     * @param string $language ISO 639-3 code.
     * @return array{domains:array<int,array{code:string,name:string}>,meta:array<string,mixed>}|\WP_Error
     */
    public function get_domains( string $language ): array|\WP_Error;

    /**
     * The one deterministic entry for the current calendar day in one language.
     *
     * @param string $language   ISO 639-3 code.
     * @param string $reader_ref Opaque reader reference.
     * @return array{entry:array<string,mixed>,date:string,meta:array<string,mixed>}|\WP_Error
     */
    public function get_word_of_day( string $language, string $reader_ref ): array|\WP_Error;
}
