<?php
/**
 * Assembly point for the Dictionary Node display adapter.
 *
 * Wiring only: it builds the object graph, registers the admin screen always,
 * and registers the same-origin REST routes only when the cutover flag is on.
 *
 * The flag defaults to OFF. The Node's `/v1/display/*` routes must be deployed
 * and verified before this UI switches (contract §7, steps 2 and 4); a plugin
 * that switches first is a dictionary serving blank pages. The flag selects
 * exactly one read path — the adapter or the legacy CPT/SCF path — never both.
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
 * Bootstraps the display adapter.
 */
final class Sparxstar3IAtlasDisplayAdapter {

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
     * Upstream data source.
     *
     * @var DictionaryDisplaySourceInterface
     */
    private DictionaryDisplaySourceInterface $source;

    /**
     * Constructor.
     *
     * @param DisplayConfig|null                    $config    Deployment configuration.
     * @param DictionaryDisplaySourceInterface|null $source    Upstream data source.
     * @param DisplayLanguageSettings|null          $languages Language configuration.
     */
    public function __construct(
        ?DisplayConfig $config = null,
        ?DictionaryDisplaySourceInterface $source = null,
        ?DisplayLanguageSettings $languages = null
    ) {
        $this->config    = $config ?? new DisplayConfig();
        $this->languages = $languages ?? new DisplayLanguageSettings();
        $this->source    = $source ?? self::default_source( $this->config );
    }

    /**
     * Whether Browse mode reads through the adapter.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return $this->config->is_adapter_enabled();
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register_hooks(): void {
        if ( is_admin() ) {
            ( new DisplayAdminScreen( $this->config, $this->languages, $this->source ) )->register_hooks();
        }

        if ( ! $this->is_enabled() ) {
            return;
        }

        ( new Sparxstar3IAtlasDisplayAdapterRestApi(
            $this->source,
            $this->languages,
            new ReaderRef( $this->config )
        ) )->register_hooks();
    }

    /**
     * Build the default data source: the Node's live JSON display tier.
     *
     * The `sparxstar_dictionary_display_source` filter may return any object
     * implementing DictionaryDisplaySourceInterface, which is how a different
     * upstream transport is introduced without touching the REST handlers, the
     * admin screen, or the React app.
     *
     * @param DisplayConfig $config Deployment configuration.
     * @return DictionaryDisplaySourceInterface
     */
    private static function default_source( DisplayConfig $config ): DictionaryDisplaySourceInterface {
        $source = new LiveJsonDisplaySource(
            new NodeHttpClient( $config, new IdentityMachineTokenClient( $config ) )
        );

        $filtered = apply_filters( 'sparxstar_dictionary_display_source', $source );

        return $filtered instanceof DictionaryDisplaySourceInterface ? $filtered : $source;
    }
}
