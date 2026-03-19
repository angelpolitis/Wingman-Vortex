<?php
    /**
     * Project Name:    Wingman Vortex - Auto Bundling Settings
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2026-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Autoloader.Objects namespace.
    namespace Wingman\Vortex\Objects;

    # Import the following classes to the current scope.
    use Wingman\Vortex\Bridge\Cortex\Attributes\ConfigGroup;
    use Wingman\Vortex\Bridge\Cortex\Attributes\Configurable;

    /**
     * The configuration payload for the autoloader's automatic bundling lifecycle.
     *
     * The class-level `#[ConfigGroup]` enables short per-property keys (`enabled`, `profile`,
     * `bundleFile`, etc.) while still supporting the full dotted keys under
     * `vortex.bundling.auto.*`.
     * @package Wingman\Vortex\Objects
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[ConfigGroup("vortex.bundling.auto")]
    class AutoBundlingSettings {
        /**
         * Whether automatic bundle bootstrapping and generation are enabled.
         * @var bool
         */
        #[Configurable("enabled", "Whether automatic bundle bootstrapping and generation are enabled.")]
        public bool $enabled = false;

        /**
         * Whether bundle generation should run at shutdown.
         * @var bool
         */
        #[Configurable("buildOnShutdown", "Whether bundle generation should run at shutdown.")]
        public bool $buildOnShutdown = true;

        /**
         * Whether bundle artefacts should be generated.
         * @var bool
         */
        #[Configurable("generateBundle", "Whether bundle artefacts should be generated.")]
        public bool $generateBundle = true;

        /**
         * Whether preload script artefacts should be generated.
         * @var bool
         */
        #[Configurable("generatePreload", "Whether preload script artefacts should be generated.")]
        public bool $generatePreload = true;

        /**
         * Whether generation should be forced even when signatures are unchanged.
         * @var bool
         */
        #[Configurable("forceGeneration", "Whether generation should be forced even when signatures are unchanged.")]
        public bool $forceGeneration = false;

        /**
         * Whether automatic shutdown generation failures should throw an exception.
         * @var bool
         */
        #[Configurable("failOnBuildError", "Whether automatic shutdown generation failures should throw an exception.")]
        public bool $failOnBuildError = false;

        /**
         * Whether an existing bundle should be loaded before normal autoload resolution.
         * @var bool
         */
        #[Configurable("useBundleAtRuntime", "Whether an existing bundle should be loaded before normal autoload resolution.")]
        public bool $useBundleAtRuntime = true;

        /**
         * The profile name used by generated artefacts.
         * @var string
         */
        #[Configurable("profile", "The profile name used by generated artefacts.")]
        public string $profile = "default";

        /**
         * The monolithic bundle output file path.
         * @var string|null
         */
        #[Configurable("bundleFile", "The monolithic bundle output file path.")]
        public ?string $bundleFile = null;

        /**
         * The preload script output file path.
         * @var string|null
         */
        #[Configurable("preloadScriptFile", "The preload script output file path.")]
        public ?string $preloadScriptFile = null;

        /**
         * Exclusion patterns to pass to the bundler.
         * @var string[]
         */
        #[Configurable("excludePatterns", "Exclusion patterns to pass to the bundler.")]
        public array $excludePatterns = [];

        /**
         * Whether unsafe files should be skipped during generation.
         * @var bool
         */
        #[Configurable("skipUnsafeFiles", "Whether unsafe files should be skipped during generation.")]
        public bool $skipUnsafeFiles = true;

        /**
         * Whether whitespace stripping should be enabled during generation.
         * @var bool
         */
        #[Configurable("stripWhitespace", "Whether whitespace stripping should be enabled during generation.")]
        public bool $stripWhitespace = false;
    }
?>