<?php
    /**
     * Project Name:    Wingman Vortex - Autoloader
     * Created by:      Angel Politis
     * Creation Date:   Nov 05 2025
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */
    # Use the Autoloader namespace.
    namespace Wingman\Vortex;

    # Import the following classes to the current scope.
    use Closure;
    use Throwable;
    use Wingman\Vortex\Bridge\Cortex\Configuration;
    use Wingman\Vortex\Exceptions\AutoBundlingBuildFailureException;
    use Wingman\Vortex\Exceptions\DuplicateAutoloaderNameException;
    use Wingman\Vortex\Exceptions\InvalidExtensionTypeException;
    use Wingman\Vortex\Exceptions\UndefinedInstanceMethodException;
    use Wingman\Vortex\Exceptions\UndefinedStaticMethodException;
    use Wingman\Vortex\Objects\AutoBundlingSettings;

    # Import the following functions to the current scope.
    use function call_user_func;
    use function is_null;
    use function is_string;
    use function sizeof;

    /**
     * An autoloader is used to control how class that haven't been imported manually can be found and loaded
     * automatically into the project.
     * @package Wingman\Vortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     * 
     * @method static Autoloader register(string $name, callable $pathFinder, int $priority = 0) Creates a new autoloader and registers it statically. 
     * @method Autoloader register(string $name, callable $pathFinder, int $priority = 0) Registers an autoloader.
     * 
     * @note:
     * When switching from using __autoload() to using spl_autoload_register keep in mind that deserialisation of
     * the session can trigger class lookups. So you need to make sure the spl_autoload_register is done BEFORE
     * session_start() is called.
     */
    class Autoloader {
        /**
         * A static cache of directory contents to avoid repeated disk reads.
         * Format: ['path/to/dir' => ['file1', 'file2', ...]]
         */
        protected static array $dirCache = [];

        /**
         * The registry of the class.
         * @var static[]
         */
        protected static array $registry = [];

        /**
         * The cache for resolved real paths to avoid redundant disk I/O.
         * @var array<string, string|null>
         */
        protected static array $resolvedCache = [];

        /**
         * Whether automatic bundle bootstrapping and generation are enabled.
         * @var bool
         */
        protected static bool $autoBundlingEnabled = false;

        /**
         * Whether automatic bundling bootstrapping has already executed.
         * @var bool
         */
        protected static bool $autoBundlingBootstrapped = false;

        /**
         * Whether automatic bundling generation has already executed.
         * @var bool
         */
        protected static bool $autoBundlingGenerated = false;

        /**
         * The last automatic bundling error, if any.
         * @var Throwable|null
         */
        protected static ?Throwable $autoBundlingLastError = null;

        /**
         * Whether bundle generation should run at shutdown.
         * @var bool
         */
        protected static bool $autoBundlingBuildOnShutdown = true;

        /**
         * Whether automatic shutdown generation failures should throw an exception.
         * @var bool
         */
        protected static bool $autoBundlingFailOnBuildError = false;

        /**
         * Whether bundle generation should force regeneration even when unchanged.
         * @var bool
         */
        protected static bool $autoBundlingForceGeneration = false;

        /**
         * Whether bundle mode artefacts should be generated.
         * @var bool
         */
        protected static bool $autoBundlingGenerateBundle = true;

        /**
         * Whether preload script artefacts should be generated.
         * @var bool
         */
        protected static bool $autoBundlingGeneratePreload = true;

        /**
         * The profile name used in automatically generated artefacts.
         * @var string
         */
        protected static string $autoBundlingProfile = "default";

        /**
         * Whether an existing bundle should be loaded before normal autoload resolution.
         * @var bool
         */
        protected static bool $autoBundlingUseBundleAtRuntime = true;

        /**
         * The configured output file for automatic monolithic bundle generation.
         * @var string|null
         */
        protected static ?string $autoBundleFile = null;

        /**
         * The configured output file for automatic preload script generation.
         * @var string|null
         */
        protected static ?string $autoPreloadScriptFile = null;

        /**
         * Exclusion patterns passed to the bundler in automatic mode.
         * @var string[]
         */
        protected static array $autoBundlingExcludePatterns = [];

        /**
         * Whether unsafe files should be skipped when auto-generating bundles.
         * @var bool
         */
        protected static bool $autoBundlingSkipUnsafeFiles = true;

        /**
         * Whether whitespace stripping should be enabled when auto-generating bundles.
         * @var bool
         */
        protected static bool $autoBundlingStripWhitespace = false;

        /**
         * An autoloader's transformer from a class name to a file path.
         * @var callable
         */
        protected $pathFinder;

        /**
         * The name/identifier of an autoloader.
         * @var string
         */
        protected string $name;

        /**
         * The creation date of an autoloader.
         * @var float
         */
        protected float $creationDate;

        /**
         * Whether an autoloader is enabled.
         * @var bool
         */
        protected bool $enabled = true;

        /**
         * The extensions an autoloader will try to find files with.
         * @var string[]
         */
        protected array $extensions = ["php", "inc"];

        /**
         * The log of an autoloader.
         * @var array[]
         */
        protected array $log = [];

        /**
         * The priority of an autoloader (0 = default).
         * @var int
         */
        protected int $priority = 0;

        /**
         * Intercepts calls to non-existent instance methods.
         * @param string $name the name of the method
         * @param array $arguments the arguments passed to the method
         * @return mixed the value returned by the intended target
         * @throws UndefinedInstanceMethodException if the intended target wasn't found
         */
        public function __call (string $name, array $arguments) {
            if (!method_exists($this, "{$name}__i")) {
                throw new UndefinedInstanceMethodException("Call to undefined method '$name'.");
            }

            return $this->{"{$name}__i"}(...$arguments);
        }

        /**
         * Intercepts calls to non-existent static methods.
         * @param string $name the name of the method
         * @param array $arguments the arguments passed to the method
         * @return mixed the value returned by the intended target
         * @throws UndefinedStaticMethodException if the intended target wasn't found
         */
        public static function __callStatic (string $name, array $arguments) {
            if (!method_exists(static::class, "{$name}__c")) {
                throw new UndefinedStaticMethodException("Call to undefined static method '$name'.");
            }

            return static::{"{$name}__c"}(...$arguments);
        }

        /**
         * Creates a new autoloader.
         * @param string $name The name of the autoloader.
         * @param callable $pathFinder The path finder of the autoloader.
         * @param int $priority The priority to assign the autoloader.
         */
        public function __construct (string $name, callable $pathFinder, int $priority = 0) {
            $this->name = $name;
            $this->priority = $priority;
            $this->pathFinder = $pathFinder;
            $this->creationDate = microtime(true) * 1000;
        }
        
        /**
         * Gets a path having replaced with a custom separator all defined separators.
         * @param string|null $path The path to fix.
         * @param string $new The separator to replace the old separators [default: `DIRECTORY_SEPARATOR`].
         * @param string[] $old The separators to be replaced [default: `\`, `/`].
         * @return string|null The fixed path, or `null` if no path was given.
         */
        protected static function fix (?string $path, string $new = DIRECTORY_SEPARATOR, array $old = ['\\', '/']) : ?string {
            return is_null($path) ? null : str_replace($old, $new, $path);
		}

        /**
         * Creates a new autoloader and registers it statically.
         * @param string $name The name of the autoloader.
         * @param callable $pathFinder The path finder of the autoloader.
         * @param int $priority The priority to assign the autoloader.
         * @return static The autoloader.
         */
        protected static function register__c (string $name, callable $pathFinder, int $priority = 0) : static {
            return static::from($name, $pathFinder, $priority)->register__i();
        }

        /**
         * Registers an autoloader.
         * @return static The autoloader.
         * @throws DuplicateAutoloaderNameException If an autoloader with the same name already exists.
         */
        protected function register__i () : static {
            if (isset(static::$registry[$this->name])) {
                throw new DuplicateAutoloaderNameException("An autoloader with the name '{$this->name}' already exists.");
            }

            static::$registry[$this->name] = $this;
            return $this;
        }

        /**
         * Clears the APCu cache for autoloaded paths, if APCu is available and being used for caching.
         * This is useful to call after deploying new code to ensure that stale paths aren't used.
         * @return bool Whether the cache was successfully cleared or not (e.g. if APCu isn't available).
         */
        public static function clearCache () : bool {
            if (function_exists("apcu_delete")) {
                /** @disregard */
                return apcu_delete(new \APCUIterator('/^wingman_path_/'));
            }
            return false;
        }

        /**
         * Configures automatic bundling behaviour from direct settings, one or more configuration
         * files, or a Cortex `Configuration` instance.
         *
         * For direct arrays, both short keys (`enabled`, `profile`) and full dotted keys
         * (`vortex.bundling.auto.enabled`) are accepted.
         *
         * Supported settings:
         * - enabled: bool
         * - bundleFile: string|null
         * - preloadScriptFile: string|null
         * - profile: string
         * - buildOnShutdown: bool
         * - generateBundle: bool
         * - generatePreload: bool
         * - forceGeneration: bool
         * - failOnBuildError: bool
         * - useBundleAtRuntime: bool
         * - excludePatterns: string[]
         * - skipUnsafeFiles: bool
         * - stripWhitespace: bool
         *
         * @param array|string|Configuration|null $options A direct settings map, config source path,
         *                                                 array of config source paths, or a
         *                                                 `Configuration` instance.
         */
        public static function configureAutoBundling (array|string|Configuration|null $options = []) : void {
            $settings = new AutoBundlingSettings();

            $settings->enabled = static::$autoBundlingEnabled;
            $settings->buildOnShutdown = static::$autoBundlingBuildOnShutdown;
            $settings->generateBundle = static::$autoBundlingGenerateBundle;
            $settings->generatePreload = static::$autoBundlingGeneratePreload;
            $settings->forceGeneration = static::$autoBundlingForceGeneration;
            $settings->failOnBuildError = static::$autoBundlingFailOnBuildError;
            $settings->useBundleAtRuntime = static::$autoBundlingUseBundleAtRuntime;
            $settings->skipUnsafeFiles = static::$autoBundlingSkipUnsafeFiles;
            $settings->stripWhitespace = static::$autoBundlingStripWhitespace;
            $settings->profile = static::$autoBundlingProfile;
            $settings->bundleFile = static::$autoBundleFile;
            $settings->preloadScriptFile = static::$autoPreloadScriptFile;
            $settings->excludePatterns = static::$autoBundlingExcludePatterns;

            if ($options instanceof Configuration) {
                Configuration::hydrate($settings, $options);
            }
            elseif (is_string($options) && trim($options) !== "") {
                Configuration::hydrate($settings, static::createConfigurationFromSources($options));
            }
            elseif (is_array($options) && !empty($options)) {
                $isSourceList = array_is_list($options);

                if ($isSourceList) {
                    foreach ($options as $source) {
                        if (!is_string($source) || trim($source) === "") {
                            $isSourceList = false;
                            break;
                        }
                    }
                }

                if ($isSourceList) {
                    Configuration::hydrate($settings, static::createConfigurationFromSources($options));
                }
                else {
                    Configuration::hydrate($settings, static::normaliseAutoBundlingOptionMap($options));
                }
            }

            static::applyAutoBundlingSettings($settings);
        }

        /**
         * Applies a resolved settings object to static automatic-bundling runtime state.
         * @param AutoBundlingSettings $settings The resolved settings.
         * @return void
         */
        protected static function applyAutoBundlingSettings (AutoBundlingSettings $settings) : void {
            static::$autoBundlingEnabled = $settings->enabled;
            static::$autoBundlingBuildOnShutdown = $settings->buildOnShutdown;
            static::$autoBundlingGenerateBundle = $settings->generateBundle;
            static::$autoBundlingGeneratePreload = $settings->generatePreload;
            static::$autoBundlingForceGeneration = $settings->forceGeneration;
            static::$autoBundlingFailOnBuildError = $settings->failOnBuildError;
            static::$autoBundlingUseBundleAtRuntime = $settings->useBundleAtRuntime;
            static::$autoBundlingSkipUnsafeFiles = $settings->skipUnsafeFiles;
            static::$autoBundlingStripWhitespace = $settings->stripWhitespace;
            static::$autoBundlingProfile = trim($settings->profile) !== "" ? $settings->profile : static::$autoBundlingProfile;

            static::$autoBundleFile = is_string($settings->bundleFile) && trim($settings->bundleFile) !== ""
                ? static::fix($settings->bundleFile)
                : null;

            static::$autoPreloadScriptFile = is_string($settings->preloadScriptFile) && trim($settings->preloadScriptFile) !== ""
                ? static::fix($settings->preloadScriptFile)
                : null;

            static::$autoBundlingExcludePatterns = [];

            foreach ($settings->excludePatterns as $pattern) {
                if (!is_string($pattern) || trim($pattern) === "") continue;
                static::$autoBundlingExcludePatterns[] = $pattern;
            }
        }

        /**
         * Creates a configuration instance from one or more source files.
         *
         * When a global/shared configuration exists, a clone is used so imported sources can
         * be layered without mutating global configuration state.
         * @param string|array $sources A source file path or a list of source file paths.
         * @return Configuration The loaded configuration instance.
         */
        protected static function createConfigurationFromSources (string|array $sources) : Configuration {
            $globalConfiguration = Configuration::find();
            $configuration = $globalConfiguration !== null
                ? clone $globalConfiguration
                : Configuration::fromIterable([]);

            $sourcePaths = [];

            foreach ((array) $sources as $source) {
                if (!is_string($source) || trim($source) === "") continue;
                $sourcePaths[] = static::fix($source);
            }

            if (!empty($sourcePaths)) {
                $configuration->import($sourcePaths);
            }

            return $configuration;
        }

        /**
         * Normalises a direct options map to dotted keys expected by `#[ConfigGroup]` hydration.
         * @param array $options A direct options map.
         * @return array The normalised flat map.
         */
        protected static function normaliseAutoBundlingOptionMap (array $options) : array {
            $normalised = [];
            $prefix = "vortex.bundling.auto.";

            foreach ($options as $key => $value) {
                if (!is_string($key)) continue;

                $trimmedKey = trim($key);

                if ($trimmedKey === "") continue;

                if (str_starts_with($trimmedKey, $prefix)) {
                    $normalised[$trimmedKey] = $value;
                    continue;
                }

                $normalised[$prefix . $trimmedKey] = $value;
            }

            return $normalised;
        }

        /**
         * Gets whether automatic bundling is enabled.
         * @return bool Whether automatic bundling is enabled.
         */
        public static function isAutoBundlingEnabled () : bool {
            return static::$autoBundlingEnabled;
        }

        /**
         * Boots automatic bundling integration and optional runtime bundle loading.
         * @return void
         */
        protected static function bootstrapAutoBundling () : void {
            if (!static::$autoBundlingEnabled || static::$autoBundlingBootstrapped) return;

            static::$autoBundlingBootstrapped = true;

            if (
                static::$autoBundlingUseBundleAtRuntime &&
                is_string(static::$autoBundleFile) &&
                is_file(static::$autoBundleFile)
            ) {
                require_once static::$autoBundleFile;
            }

            if (static::$autoBundlingBuildOnShutdown) {
                register_shutdown_function([static::class, "executeAutoBundlesAtShutdownSafely"]);
            }
        }

        /**
         * Executes automatic shutdown bundling with internal exception containment.
         * @return void
         */
        public static function executeAutoBundlesAtShutdownSafely () : void {
            try {
                static::buildAutoBundlesAtShutdown();
            }
            catch (Throwable $e) {
                static::$autoBundlingLastError = $e;
                error_log("Wingman Vortex auto-bundling failed: " . $e->getMessage());
            }
        }

        /**
         * Generates configured bundle artefacts during shutdown when automatic mode is enabled.
         * @return void
         */
        public static function buildAutoBundlesAtShutdown () : void {
            if (!static::$autoBundlingEnabled || static::$autoBundlingGenerated) return;

            static::$autoBundlingGenerated = true;

            if (!static::$autoBundlingGenerateBundle && !static::$autoBundlingGeneratePreload) return;

            if (!class_exists(Bundler::class)) return;

            try {
                $bundler = (new Bundler())
                    ->captureLoadedFiles()
                    ->setExcludePatterns(static::$autoBundlingExcludePatterns)
                    ->setSkipUnsafeFiles(static::$autoBundlingSkipUnsafeFiles)
                    ->setStripWhitespace(static::$autoBundlingStripWhitespace);

                if (
                    static::$autoBundlingGenerateBundle &&
                    is_string(static::$autoBundleFile) &&
                    static::$autoBundleFile !== ""
                ) {
                    $bundler->generateBundle(
                        static::$autoBundleFile,
                        static::$autoBundlingProfile,
                        static::$autoBundlingForceGeneration,
                    );
                }

                if (
                    static::$autoBundlingGeneratePreload &&
                    is_string(static::$autoPreloadScriptFile) &&
                    static::$autoPreloadScriptFile !== ""
                ) {
                    $bundler->generatePreloadScript(
                        static::$autoPreloadScriptFile,
                        static::$autoBundlingProfile,
                        static::$autoBundlingForceGeneration,
                    );
                }
            }
            catch (Throwable $e) {
                if (static::$autoBundlingFailOnBuildError) {
                    throw new AutoBundlingBuildFailureException("Wingman Vortex auto-bundling failed.", 0, $e);
                }

                static::$autoBundlingLastError = $e;
                error_log("Wingman Vortex auto-bundling failed: " . $e->getMessage());
            }
        }

        /**
         * Gets the last automatic bundling error, if any.
         * @return Throwable|null The last automatic bundling error.
         */
        public static function getLastAutoBundlingError () : ?Throwable {
            return static::$autoBundlingLastError;
        }

        /**
         * Clears the last automatic bundling error.
         * @return void
         */
        public static function clearLastAutoBundlingError () : void {
            static::$autoBundlingLastError = null;
        }

        /**
         * Clears in-memory runtime caches used by autoloading.
         * @return void
         */
        public static function clearRuntimeCaches () : void {
            static::$dirCache = [];
            static::$resolvedCache = [];
        }

        /**
         * Resets runtime state for long-running processes.
         *
         * This clears runtime caches and per-request bootstrapping flags so the
         * autoloader can be reused safely across worker loops.
         * @param bool $clearRegistry Whether all registered autoloaders should be removed.
         * @param bool $resetAutoBundlingConfiguration Whether automatic bundling options should be reset to defaults.
         * @return void
         */
        public static function resetRuntimeState (bool $clearRegistry = false, bool $resetAutoBundlingConfiguration = false) : void {
            static::clearRuntimeCaches();
            static::clearLastAutoBundlingError();

            static::$autoBundlingBootstrapped = false;
            static::$autoBundlingGenerated = false;

            foreach (static::$registry as $autoloader) {
                $autoloader->log = [];
            }

            if ($clearRegistry) {
                static::$registry = [];
            }

            if ($resetAutoBundlingConfiguration) {
                static::$autoBundlingEnabled = false;
                static::$autoBundlingBuildOnShutdown = true;
                static::$autoBundlingFailOnBuildError = false;
                static::$autoBundlingForceGeneration = false;
                static::$autoBundlingGenerateBundle = true;
                static::$autoBundlingGeneratePreload = true;
                static::$autoBundlingProfile = "default";
                static::$autoBundlingUseBundleAtRuntime = true;
                static::$autoBundleFile = null;
                static::$autoPreloadScriptFile = null;
                static::$autoBundlingExcludePatterns = [];
                static::$autoBundlingSkipUnsafeFiles = true;
                static::$autoBundlingStripWhitespace = false;
            }
        }

        /**
         * Dequeues the entire registry of the class looking for the first suitable autoloader to load a class.
         * @param string $class The class to be autoloaded.
         * @param int $minPriority The minimum priority for an autoloader to be considered.
         */
        public static function dequeueRegistryBasedOnPriority (string $className, int $minPriority = -PHP_INT_MAX) : void {
            foreach (static::getQueue($minPriority) as $autoloaderName) {
                $autoloader = static::$registry[$autoloaderName]->run($className);

                if ($autoloader->wasLastAttemptSuccessful()) break;
            }
        }

        /**
         * Disables an autoloader.
         * @return static The autoloader.
         */
        public function disable () : static {
            $this->enabled = false;
            return $this;
        }

        /**
         * Gets all necessary information about an autoloader.
         * @return array The information of an autoloader.
         */
        public function dump () : array {
            return [
                "name" => $this->name,
                "priority" => $this->priority,
                "enabled" => $this->enabled,
                "creationDate" => $this->creationDate,
                "log" => $this->log,
                "error" => $this->wasLastAttemptErred(),
                "classFound" => $this->wasLastAttemptSuccessful(),
                "lastClass" => $this->getLastFoundClass(),
                "lastError" => $this->getLastError()
            ];
        }

        /**
         * Enables an autoloader.
         * @return static The autoloader.
         */
        public function enable () : static {
            $this->enabled = true;
            return $this;
        }

        /**
         * Creates a new autoloader.
         * @param string $name The name of the autoloader.
         * @param callable $pathFinder The path finder of the autoloader.
         * @param int $priority The priority to assign the autoloader.
         * @return static The autoloader.
         */
        public static function from (string $name, callable $pathFinder, int $priority = 0) : static {
            return new static($name, $pathFinder, $priority);
        }

        /**
         * Gets an autoloader via its name.
         * @param string $name The name of the autoloader.
         * @return static|null The autoloader, if any with that name.
         */
        public static function get (string $name) : ?static {
            return static::$registry[$name] ?? null;
        }

        /**
         * Gets the extensions by default supported by an autoloader.
         * @return string[] The extensions supported by the autoloader,
         */
        public function getExtensions () : array {
            return $this->extensions;
        }

        /**
         * Gets the last error occurred during the execution of an autoloader, if any.
         * @return Throwable|null The last error, if any.
         */
        public function getLastError () : ?Throwable {
            if (sizeof($this->log) == 0) return null;
            $entry = $this->log[array_key_last($this->log)];
            return $entry["error"];
        }

        /**
         * Gets the last class found by an autoloader, if any.
         * @return string|null The last class, if any.
         */
        public function getLastFoundClass () : ?string {
            if (sizeof($this->log) == 0) return null;
            $entry = $this->log[array_key_last($this->log)];
            return $entry["classFound"] ? $entry["class"] : null;
        }

        /**
         * Gets all successfully resolved file paths from registered autoloaders.
         * @param int $minPriority The minimum priority for an autoloader to be considered.
         * @return string[] The resolved file paths in registry queue order.
         */
        public static function getLoadedFiles (int $minPriority = -PHP_INT_MAX) : array {
            $loadedFiles = [];

            foreach (static::getQueue($minPriority) as $autoloaderName) {
                foreach (static::$registry[$autoloaderName]->getSuccessfullyResolvedPaths() as $path) {
                    if (isset($loadedFiles[$path])) continue;
                    $loadedFiles[$path] = $path;
                }
            }

            return array_values($loadedFiles);
        }

        /**
         * Gets the log of an autoloader.
         * @return array The log of the autoloader.
         */
        public function getLog () : array {
            return $this->log;
        }

        /**
         * Gets the priority of an autoloader.
         * @return int The priority of the autoloader.
         */
        public function getPriority () : int {
            return $this->priority;
        }

        /**
         * Gets the current autoloader queue.
         * @param int $minPriority The minimum priority for an autoloader to be considered.
         * @return string[] The names of the autoloaders as queued to run.
         */
        public static function getQueue (int $minPriority = -PHP_INT_MAX) : array {
            $enabledAutoloaders = [];

            $autoloaderPriorities = [];

            foreach (static::$registry as $name => $autoloader) {
                if (!$autoloader->enabled || $minPriority > $autoloader->priority) continue;

                $enabledAutoloaders[] = $autoloader;
                $autoloaderPriorities[$name] = $autoloader->priority;
            }

            # Sort by priority in descending order and creation date in ascending order.
            uksort($autoloaderPriorities, function ($aName, $bName) use ($autoloaderPriorities) {
                $aPriority = $autoloaderPriorities[$aName];
                $bPriority = $autoloaderPriorities[$bName];

                if ($aPriority !== $bPriority) return $bPriority <=> $aPriority;

                $aDate = static::$registry[$aName]->creationDate;
                $bDate = static::$registry[$bName]->creationDate;

                return $aDate <=> $bDate;
            });
            
            return array_keys($autoloaderPriorities);
        }

        /**
         * Gets the registry of autoloaders.
         * @return static[] The registry of autoloaders.
         */
        public static function getRegistry () : array {
            return static::$registry;
        }

        /**
         * Gets the successfully resolved file paths of an autoloader.
         * @return string[] The resolved file paths in execution order.
         */
        public function getSuccessfullyResolvedPaths () : array {
            $paths = [];

            foreach ($this->log as $entry) {
                if (!($entry["classFound"] ?? false)) continue;
                if (!($entry["pathValid"] ?? false)) continue;

                $path = $entry["path"] ?? null;

                if (!is_string($path) || isset($paths[$path])) continue;

                $paths[$path] = $path;
            }

            return array_values($paths);
        }

        /**
         * Gets whether an autoloader is enabled.
         * @return bool Whether the autoloader is enabled.
         */
        public function isEnabled () : bool {
            return $this->enabled;
        }

        /**
         * Resolves a case-insensitive path to its exact case-sensitive match on disk.
         * Supports absolute paths or directory-relative paths.
         * @param string $fuzzyPath The path to resolve.
         * @param string|null $directory Optional base directory.
         * @param string[] $extensions Optional extensions to try if the last segment doesn't match a file directly.
         * @return string|null The real path or `null`.
         */
        public static function resolveCI (string $fuzzyPath, ?string $directory = null, array $extensions = []) : ?string {
            $fuzzyPath = static::fix($fuzzyPath);
            
            # If a directory is provided, ensure we are working with relative segments.
            if ($directory !== null) {
                $currentPath = rtrim(static::fix($directory), DIRECTORY_SEPARATOR);
                $remainingPath = str_starts_with($fuzzyPath, $currentPath)
                    ? ltrim(substr($fuzzyPath, strlen($currentPath)), DIRECTORY_SEPARATOR)
                    : $fuzzyPath;
            }

            # Otherwise, start from the root if the path is absolute, or from the current directory if it's relative.
            else {
                $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === "WIN");
                $isWindowsAbsolute = ($isWindows && strlen($fuzzyPath) > 1 && $fuzzyPath[1] === ':');
                $isUnixAbsolute = str_starts_with($fuzzyPath, DIRECTORY_SEPARATOR);

                if ($isWindowsAbsolute) {
                    $currentPath = strtoupper(substr($fuzzyPath, 0, 2));
                    $remainingPath = ltrim(substr($fuzzyPath, 2), DIRECTORY_SEPARATOR);
                }
                elseif ($isUnixAbsolute) {
                    $currentPath = DIRECTORY_SEPARATOR;
                    $remainingPath = ltrim($fuzzyPath, DIRECTORY_SEPARATOR);
                }
                else {
                    $cwd = getcwd();

                    if ($cwd === false) {
                        return null;
                    }

                    $currentPath = rtrim(static::fix($cwd), DIRECTORY_SEPARATOR);
                    $remainingPath = $fuzzyPath;
                }
            }
            
            $segments = array_filter(explode(DIRECTORY_SEPARATOR, $remainingPath));
            $totalSegments = count($segments);
            $count = 0;

            foreach ($segments as $part) {
                $count++;
                
                // Check if we've already scanned this directory in this request
                if (!isset(self::$dirCache[$currentPath])) {
                    if (!is_dir($currentPath)) return null;
                    self::$dirCache[$currentPath] = scandir($currentPath);
                }

                $isLast = ($count === $totalSegments);
                $entries = self::$dirCache[$currentPath];
                $found = false;

                foreach ($entries as $entry) {
                    # Case 1: Exact or Case-Insensitive match for a directory or the final file
                    if (strcasecmp($entry, $part) === 0) {
                        $candidate = $currentPath . DIRECTORY_SEPARATOR . $entry;
                        if ($isLast && !is_file($candidate)) continue;
                        $currentPath = $candidate;
                        $found = true;
                        break;
                    }

                    # Case 2: Last segment file extension matching (e.g., class.php)
                    if ($isLast && !empty($extensions)) {
                        foreach ($extensions as $ext) {
                            if (strcasecmp($entry, $part . '.' . $ext) !== 0) continue;
                            $currentPath .= DIRECTORY_SEPARATOR . $entry;
                            $found = true;
                            break 2;
                        }
                    }
                }

                if (!$found) return null;
            }

            return is_file($currentPath) || is_dir($currentPath) ? $currentPath : null;
        }

        /**
         * Runs an autoloader for a given class.
         * @param string $className The class to run the autoloader for.
         * @return static The autoloader.
         */
        public function run (string $className) : static {
            $error = null;
            $path = null;
            $pathFinderResult = null;
            $requestedClass = $className;
            $extensions = $this->getExtensions();
            $cacheKey = "wingman_path_" . md5($this->name . "|" . $className);

            try {
                if (function_exists("apcu_fetch")) {
                    /** @disregard */
                    $path = apcu_fetch($cacheKey);
                }

                if (!$path) {
                    $pathFinderResult = call_user_func($this->pathFinder, $className);

                    if (is_array($pathFinderResult)) {
                        $className = $pathFinderResult["class"] ?? $className;
                        $path = $pathFinderResult["path"] ?? null;
                    }
                    else $path = $pathFinderResult;

                    if ($path) {
                        if (is_file($path)) ;
                        elseif (isset(self::$resolvedCache[$path])) {
                            $path = self::$resolvedCache[$path];
                        }
                        else {
                            $originalPath = $path;
                            $path = self::resolveCI($path, null, $extensions);
                            self::$resolvedCache[$originalPath] = $path;
                        }
                        
                        if (function_exists("apcu_store")) {
                            /** @disregard */
                            apcu_store($cacheKey, $path, 86_400);
                        }
                    }
                }
            }
            catch (Throwable $e) {
                $error = $e;
            }

            $pathValid = !is_null($path) && is_file($path);

            try {
                if (!$error && $pathValid) {
                    require_once $path;
                }
            }
            catch (Throwable $e) {
                $error = $e;
            }
            finally {
                $classFound = $pathValid && (
                    class_exists($className, false) ||
                    interface_exists($className, false) ||
                    trait_exists($className, false) ||
                    enum_exists($className, false)
                );

                $this->log[] = [
                    "timestamp" => floor(microtime(true) * 1000),
                    "requestedClass" => $requestedClass,
                    "extensions" => $extensions,
                    "pathFinderResult" => $pathFinderResult,
                    "path" => $path,
                    "pathValid" => $pathValid,
                    "class" => $className,
                    "classFound" => $classFound,
                    "error" => $error
                ];
            }

            return $this;
        }

        /**
         * Sets the extensions supported by default by an autoloader.
         * @param string[] The extensions to support by default.
         * @return static The autoloader.
         * @throws InvalidExtensionTypeException If an extension that isn't a string is found.
         */
        public function setExtensions (array $extensions) : static {
            foreach ($extensions as $i => $extension) {
                if (is_string($extension)) continue;

                throw new InvalidExtensionTypeException("The extension at index $i isn't a string.");
            }

            $this->extensions = $extensions;

            return $this;
        }
        
        /**
         * Sets the priority of an autoloader.
         * @param int $priority The priority of the autoloader.
         * @return static The autoloader.
         */
        public function setPriority (int $priority) : static {
            $this->priority = $priority;
            return $this;
        }

        /**
         * Gets whether there was an error during the last execution of an autoloader.
         * @return bool Whether an error occurred.
         */
        public function wasLastAttemptErred () : bool {
            if (sizeof($this->log) == 0) return false;
            $entry = $this->log[array_key_last($this->log)];
            return !is_null($entry["error"]);
        }

        /**
         * Gets whether there the last execution of an autoloader found the requested class.
         * @return bool Whether the autoloader resulted in loading the class.
         */
        public function wasLastAttemptSuccessful () : bool {
            if (sizeof($this->log) == 0) return false;
            $entry = $this->log[array_key_last($this->log)];
            return $entry["classFound"];
        }
    }

    # Register a univeral autoloader that dequeues the actual autoloaders accordingly.
    # The closure is bound to the Autoloader class scope so it can access the protected
    # bootstrapAutoBundling() method directly without routing through __callStatic.
    spl_autoload_register(Closure::bind(static function (string $className) : void {
        static::bootstrapAutoBundling();
        static::dequeueRegistryBasedOnPriority($className);
    }, null, Autoloader::class), true, true);
?>