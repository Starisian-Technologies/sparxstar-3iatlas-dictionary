<?php
// In: src/core/PluginCore.php

namespace Starisian\src\core;


if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PluginCore
 *
 * Handles the core functionality for the plugin.
 * This class is implemented as a final singleton to ensure a single entry point.
 *
 * @package Starisian\PluginTemplate\Core
 */
final class PluginCore {
        private static ?PluginCore $instance = null;

        /**
         * Returns the singleton instance of the PluginCore class.
         *
         * @return PluginCore
         */
        public static function getInstance(): PluginCore {
                if (self::$instance === null) {
                        self::$instance = new self();
                }
                return self::$instance;
        }

        /**
         * Private constructor to prevent direct instantiation.
         */
        private function __construct() {
                // Register core plugin hooks, rules, or services here.
                if (class_exists('\\Starisian\\src\\includes\\PluginRules', false)) {
                        \Starisian\src\includes\PluginRules::register_hooks();
                }
        }
}

