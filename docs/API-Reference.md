# API Reference

Complete method signatures for every public class in Wingman Vortex. All classes reside in the `Wingman\Vortex` namespace unless noted otherwise.

---

## Contents

- [Autoloader](#autoloader)
  - [Static Methods](#static-methods)
  - [Instance Methods](#instance-methods)
- [Bundler](#bundler)
- [RuntimeGuard](#runtimeguard)
- [AutoBundlingSettings](#autobundlingsettings)
- [Interfaces](#interfaces)
- [Exceptions](#exceptions)

---

## Autoloader

**Namespace:** `Wingman\Vortex`

The core class. A single SPL handler is registered on inclusion and dispatches to every entry in the priority-ordered registry. Instances are created via `from()` or `register()` and stored in the static registry.

---

### Static Methods

| Signature | Returns | Throws |
| --------- | ------- | ------ |
| `clearCache()` | `bool` | — |
| `clearLastAutoBundlingError()` | `void` | — |
| `clearRuntimeCaches()` | `void` | — |
| `configureAutoBundling(array\|string\|Configuration\|null $options = [])` | `void` | — |
| `dequeueRegistryBasedOnPriority(string $className, int $minPriority = -PHP_INT_MAX)` | `void` | — |
| `executeAutoBundlesAtShutdownSafely()` | `void` | — |
| `buildAutoBundlesAtShutdown()` | `void` | `AutoBundlingBuildFailureException` |
| `from(string $name, callable $pathFinder, int $priority = 0)` | `static` | — |
| `get(string $name)` | `static\|null` | — |
| `getLastAutoBundlingError()` | `Throwable\|null` | — |
| `getLoadedFiles(int $minPriority = -PHP_INT_MAX)` | `string[]` | — |
| `getQueue(int $minPriority = -PHP_INT_MAX)` | `string[]` | — |
| `getRegistry()` | `static[]` | — |
| `isAutoBundlingEnabled()` | `bool` | — |
| `register(string $name, callable $pathFinder, int $priority = 0)` | `static` | `DuplicateAutoloaderNameException` |
| `resetRuntimeState(bool $clearRegistry = false, bool $resetAutoBundlingConfiguration = false)` | `void` | — |
| `resolveCI(string $fuzzyPath, ?string $directory = null, array $extensions = [])` | `string\|null` | — |

---

#### `clearCache()`

Deletes all `wingman_path_*` keys from APCu shared memory. Returns `true` when APCu was available and the sweep succeeded; `false` otherwise. Call this after every deployment to prevent stale path references.

```php
Autoloader::clearCache();
```

---

#### `clearLastAutoBundlingError()`

Resets the error stored by the last failed auto-bundling run to `null`.

---

#### `clearRuntimeCaches()`

Clears the in-process resolved-path cache (`$resolvedCache`) and directory scan cache (`$dirCache`). Does not touch APCu. Automatically called by `resetRuntimeState()`.

---

#### `configureAutoBundling(array|string|Configuration|null $options = [])`

Applies automatic bundling settings from one of four input forms:

| Input | Behaviour |
| ----- | --------- |
| `array` of strings | Treated as a list of config source paths and merged via Cortex |
| `array` of key/value pairs | Direct settings map; short keys and full dotted keys are both accepted |
| `string` | Single config source file path |
| `Configuration` instance | Hydrated directly from the Cortex configuration object |
| `null` or empty array | No-op; existing settings are unchanged |

See [Auto-Bundling](Auto-Bundling.md) for the full settings reference and examples.

---

#### `dequeueRegistryBasedOnPriority(string $className, int $minPriority = -PHP_INT_MAX)`

Iterates the priority-ordered queue and calls `run()` on each enabled autoloader until one successfully resolves `$className`. Normally called by the internal SPL handler automatically.

| Parameter | Type | Description |
| --------- | ---- | ----------- |
| `$className` | `string` | The fully-qualified class name to resolve. |
| `$minPriority` | `int` | Skip autoloaders below this priority. |

---

#### `executeAutoBundlesAtShutdownSafely()`

Safe wrapper around `buildAutoBundlesAtShutdown()`. Any `Throwable` is caught, logged via `error_log()`, and stored via `getLastAutoBundlingError()`. Registered automatically as a shutdown function when `buildOnShutdown` is `true`.

---

#### `buildAutoBundlesAtShutdown()`

Executes configured bundle and/or preload artefact generation at shutdown. Skips silently when `$autoBundlingEnabled` is `false` or generation has already run. Throws `AutoBundlingBuildFailureException` when `failOnBuildError` is `true`.

---

#### `from(string $name, callable $pathFinder, int $priority = 0)`

Named constructor. Creates a new `Autoloader` instance **without** registering it. Useful for setting extensions or priority before calling `register()`.

| Parameter | Type | Description |
| --------- | ---- | ----------- |
| `$name` | `string` | Unique name for this autoloader. |
| `$pathFinder` | `callable` | Callable that accepts a class name and returns a file path or `['class' => ..., 'path' => ...]`. |
| `$priority` | `int` | Queue priority; higher values execute first. Default `0`. |

```php
$loader = Autoloader::from('app', function ($class) {
    return __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
})->setExtensions(['php'])->setPriority(100)->register();
```

---

#### `get(string $name)`

Returns the registered `Autoloader` instance with the given name, or `null` when not found.

```php
$loader = Autoloader::get('app');
```

---

#### `getLastAutoBundlingError()`

Returns the `Throwable` captured by the last failed auto-bundling run, or `null` if none.

```php
if ($e = Autoloader::getLastAutoBundlingError()) {
    // Inspect or log the failure.
}
```

---

#### `getLoadedFiles(int $minPriority = -PHP_INT_MAX)`

Returns a flat array of all file paths that were successfully resolved and loaded by registered autoloaders, in queue order, without duplicates. This is the primary feed for `Bundler::captureLoadedFiles()`.

---

#### `getQueue(int $minPriority = -PHP_INT_MAX)`

Returns an array of autoloader names sorted by priority descending, with creation-date ascending as a stable tie-breaker. Disabled autoloaders and those below `$minPriority` are excluded.

---

#### `getRegistry()`

Returns the raw registry array keyed by autoloader name.

---

#### `isAutoBundlingEnabled()`

Returns `true` when `configureAutoBundling()` has been called with `enabled => true`.

---

#### `register(string $name, callable $pathFinder, int $priority = 0)`

Static shorthand. Creates a new instance **and** registers it in a single call.

```php
Autoloader::register('app', fn ($class) => __DIR__ . '/src/' . $class . '.php', 100);
```

Throws `DuplicateAutoloaderNameException` when a loader with the same name already exists.

---

#### `resetRuntimeState(bool $clearRegistry = false, bool $resetAutoBundlingConfiguration = false)`

Resets per-request bootstrapping flags, clears in-memory path and directory caches, and wipes all autoloader log entries. Designed for worker loops.

| Parameter | Default | Effect when `true` |
| --------- | ------- | ------------------ |
| `$clearRegistry` | `false` | Removes all registered autoloaders from `$registry`. |
| `$resetAutoBundlingConfiguration` | `false` | Restores all auto-bundling statics to their default values. |

See [Workers](Workers.md) for usage examples.

---

#### `resolveCI(string $fuzzyPath, ?string $directory = null, array $extensions = [])`

Resolves a case-insensitive path to its exact case-sensitive match on disk. Walks each path segment case-insensitively using cached `scandir()` results.

| Parameter | Type | Description |
| --------- | ---- | ----------- |
| `$fuzzyPath` | `string` | The path to resolve; absolute or relative. |
| `$directory` | `string\|null` | Optional base directory. When provided, `$fuzzyPath` is treated as relative to it. |
| `$extensions` | `string[]` | Extensions to try on the final segment when no exact entry matches. |

Returns the real path string on success, or `null` when no match exists.

```php
$path = Autoloader::resolveCI('/var/www/src/mycontroller.php');
// Returns '/var/www/src/MyController.php' even on Linux.
```

---

### Instance Methods

| Signature | Returns | Throws |
| --------- | ------- | ------ |
| `disable()` | `static` | — |
| `dump()` | `array` | — |
| `enable()` | `static` | — |
| `getExtensions()` | `string[]` | — |
| `getLastError()` | `Throwable\|null` | — |
| `getLastFoundClass()` | `string\|null` | — |
| `getLog()` | `array` | — |
| `getPriority()` | `int` | — |
| `getSuccessfullyResolvedPaths()` | `string[]` | — |
| `isEnabled()` | `bool` | — |
| `register()` | `static` | `DuplicateAutoloaderNameException` |
| `run(string $className)` | `static` | — |
| `setExtensions(array $extensions)` | `static` | `InvalidExtensionTypeException` |
| `setPriority(int $priority)` | `static` | — |
| `wasLastAttemptErred()` | `bool` | — |
| `wasLastAttemptSuccessful()` | `bool` | — |

---

#### `disable()` / `enable()`

Toggles the autoloader in and out of the queue without removing it from the registry.

---

#### `dump()`

Returns a snapshot array of the autoloader's current state:

```php
[
    'name' => string,
    'priority' => int,
    'enabled' => bool,
    'creationDate' => float,   // Unix timestamp in milliseconds
    'log' => array,
    'error' => bool,           // Whether the last run errored
    'classFound' => bool,      // Whether the last run found the class
    'lastClass' => ?string,
    'lastError' => ?Throwable,
]
```

---

#### `getExtensions()`

Returns the file extensions this autoloader appends during case-insensitive resolution. Default: `["php", "inc"]`.

---

#### `getLastError()`

Returns the `Throwable` captured during the most recent `run()` call, or `null`.

---

#### `getLastFoundClass()`

Returns the class name resolved during the most recent successful `run()` call, or `null`.

---

#### `getLog()`

Returns all log entries recorded across every `run()` call on this instance. Each entry is an array:

```php
[
    'timestamp' => int,             // Unix timestamp in milliseconds
    'requestedClass' => string,
    'extensions' => string[],
    'pathFinderResult'=> mixed,     // Raw return value from the pathFinder callable
    'path' => ?string,              // Resolved path after CI resolution
    'pathValid' => bool,
    'class' => string,              // May differ from requestedClass when pathFinder returns a remapped class
    'classFound' => bool,
    'error' => ?Throwable
]
```

---

#### `getSuccessfullyResolvedPaths()`

Returns all file paths that were successfully resolved and loaded by this autoloader instance, in execution order, without duplicates.

---

#### `register()`

Registers this instance into the static registry. Throws `DuplicateAutoloaderNameException` when a loader with the same name already exists.

---

#### `run(string $className)`

Attempts to resolve and load `$className`. The path-finder callable is invoked; the returned path (or `path` key from an array result) is run through `resolveCI()` if not already an exact file, cached in APCu and the in-process store, and finally passed to `require_once`. All errors are caught and stored in the log. Returns `$this` for inspection via `wasLastAttemptSuccessful()`.

---

#### `setExtensions(array $extensions)`

Replaces the default extension list. Every value must be a string; otherwise `InvalidExtensionTypeException` is thrown.

---

#### `setPriority(int $priority)`

Changes the queue priority of this autoloader. Takes effect on the next `getQueue()` call.

---

#### `wasLastAttemptErred()`

Returns `true` when the most recent `run()` call recorded an exception.

---

#### `wasLastAttemptSuccessful()`

Returns `true` when the most recent `run()` call successfully loaded the requested class.

---

## Bundler

**Namespace:** `Wingman\Vortex`

Offline-oriented service for compiling discovered PHP source files into deterministic artefacts. Intended for build steps and warm-up scripts, not hot request paths.

See [Bundler](Bundler.md) for a full narrative guide.

**Constructor:** `__construct(array $files = [])`

| Signature | Returns | Throws |
| --------- | ------- | ------ |
| `addFile(string $file)` | `static` | — |
| `captureLoadedFiles(int $minPriority = -PHP_INT_MAX)` | `static` | — |
| `generateBundle(string $outputFile, string $profile = "default", bool $force = false)` | `array` | `LackOfEligibleFilesException`, `OutputDirectoryCreationException`, `OutputLockAcquisitionException`, `OutputFileMoveException`, `SourceFileReadException`, `TemporaryOutputFileWriteException` |
| `generatePreloadScript(string $outputFile, string $profile = "default", bool $force = false)` | `array` | `LackOfEligibleFilesException`, `OutputDirectoryCreationException`, `OutputLockAcquisitionException`, `OutputFileMoveException`, `TemporaryOutputFileWriteException` |
| `setExcludePatterns(array $patterns)` | `static` | — |
| `setFiles(array $files)` | `static` | — |
| `setSkipUnsafeFiles(bool $skipUnsafeFiles)` | `static` | — |
| `setStripWhitespace(bool $stripWhitespace)` | `static` | — |

---

#### `addFile(string $file)`

Adds a single file to the queue after normalising its path via `realpath()`. Silently skips entries that do not exist or are not readable. Duplicate real paths are deduplicated.

---

#### `captureLoadedFiles(int $minPriority = -PHP_INT_MAX)`

Replaces the current file queue with the output of `Autoloader::getLoadedFiles($minPriority)`. Useful when generating a bundle immediately after a warm-up run.

---

#### `generateBundle(string $outputFile, string $profile = "default", bool $force = false)`

Generates a monolithic PHP bundle from eligible queued files. Returns a summary array:

```php
[
    'generated'    => bool,     // false when signature matched and force was not set
    'mode'         => 'bundle',
    'outputFile'   => string,
    'metaFile'     => string,   // "{outputFile}.meta.json"
    'profile'      => string,
    'signature'    => string,   // SHA-256 of files + options
    'files'        => string[], // Eligible files included
    'skippedFiles' => array,    // [['file' => ..., 'reason' => 'excluded'|'unsafe'], ...]
]
```

Writes are performed atomically: a temporary file is written then `rename()`-swapped. A lock file serialises concurrent writers.

---

#### `generatePreloadScript(string $outputFile, string $profile = "default", bool $force = false)`

Generates an OPcache preload script that calls `opcache_compile_file()` for each eligible file. Returns the same summary shape as `generateBundle()` with `mode => 'preload'`. Safety checks (token inspection) are skipped for preload mode since files are not concatenated.

---

#### `setExcludePatterns(array $patterns)`

Replaces the exclusion pattern list. Each pattern is a regex or a plain substring. Non-string and blank values are silently ignored.

During generation, a file is excluded when any pattern matches:

- As a regex (via `preg_match()`), or
- As a plain substring (via `str_contains()`).

---

#### `setFiles(array $files)`

Replaces the queue entirely by calling `addFile()` for each element. Non-string values are skipped.

---

#### `setSkipUnsafeFiles(bool $skipUnsafeFiles)`

When `true` (default), `generateBundle()` inspects each file's tokens and skips files that contain `__DIR__`, `__FILE__`, `exit`, `eval`, `include`, `include_once`, `require`, or `require_once`.

---

#### `setStripWhitespace(bool $stripWhitespace)`

When `true`, each file's payload is processed with `php_strip_whitespace()` before concatenation, reducing bundle size.

---

## RuntimeGuard

**Namespace:** `Wingman\Vortex`

Stateless helper for worker and daemon lifecycle management. All methods are static.

| Signature | Returns |
| --------- | ------- |
| `afterWorkUnit(bool $clearRegistry = false, bool $resetAutoBundlingConfiguration = false)` | `void` |
| `afterWorkUnitFully(bool $clearRegistry = false, bool $resetAutoBundlingConfiguration = false)` | `void` |

---

#### `afterWorkUnit(bool $clearRegistry = false, bool $resetAutoBundlingConfiguration = false)`

Calls `Autoloader::resetRuntimeState()`. Clears in-process caches and per-request bootstrapping flags without touching APCu.

---

#### `afterWorkUnitFully(bool $clearRegistry = false, bool $resetAutoBundlingConfiguration = false)`

Calls `afterWorkUnit()` then `Autoloader::clearCache()` to also flush APCu entries. Use this when file changes are expected between jobs.

See [Workers](Workers.md) for detailed usage patterns.

---

## AutoBundlingSettings

**Namespace:** `Wingman\Vortex\Objects`

Data-transfer object that holds the resolved configuration for the automatic bundling lifecycle. Annotated with `#[ConfigGroup("vortex.bundling.auto")]` for Cortex hydration.

| Property | Type | Default | Short key |
| -------- | ---- | ------- | --------- |
| `$enabled` | `bool` | `false` | `enabled` |
| `$buildOnShutdown` | `bool` | `true` | `buildOnShutdown` |
| `$generateBundle` | `bool` | `true` | `generateBundle` |
| `$generatePreload` | `bool` | `true` | `generatePreload` |
| `$forceGeneration` | `bool` | `false` | `forceGeneration` |
| `$failOnBuildError` | `bool` | `false` | `failOnBuildError` |
| `$useBundleAtRuntime` | `bool` | `true` | `useBundleAtRuntime` |
| `$profile` | `string` | `"default"` | `profile` |
| `$bundleFile` | `?string` | `null` | `bundleFile` |
| `$preloadScriptFile` | `?string` | `null` | `preloadScriptFile` |
| `$excludePatterns` | `string[]` | `[]` | `excludePatterns` |
| `$skipUnsafeFiles` | `bool` | `true` | `skipUnsafeFiles` |
| `$stripWhitespace` | `bool` | `false` | `stripWhitespace` |

---

## Interfaces

**Namespace:** `Wingman\Vortex\Interfaces`

### `VortexException`

A marker interface that extends `Throwable`. Every exception class in this package implements it, enabling a single catch clause to handle all Autoloader-specific failures:

```php
use Wingman\Vortex\Interfaces\VortexException;

try {
    Autoloader::register('app', $pathFinder);
}
catch (VortexException $e) {
    // Handle any Autoloader-specific exception.
}
```

---

## Exceptions

**Namespace:** `Wingman\Vortex\Exceptions`

All exception classes implement `VortexException`. See [Exceptions](Exceptions.md) for the full hierarchy and throw-site documentation.

| Class | Base | When thrown |
| ----- | ---- | ----------- |
| `AutoBundlingBuildFailureException` | `RuntimeException` | Auto-bundling generation fails and `failOnBuildError` is `true` |
| `DuplicateAutoloaderNameException` | `LogicException` | `register()` is called with a name that already exists |
| `InvalidExtensionTypeException` | `InvalidArgumentException` | `setExtensions()` receives a non-string value |
| `LackOfEligibleFilesException` | `RuntimeException` | `generateBundle()` or `generatePreloadScript()` finds no eligible files |
| `OutputDirectoryCreationException` | `RuntimeException` | The output directory cannot be created |
| `OutputFileMoveException` | `RuntimeException` | Atomic rename of the final artefact fails |
| `OutputLockAcquisitionException` | `RuntimeException` | The lock file for concurrent-write protection cannot be created |
| `SourceFileReadException` | `RuntimeException` | A queued source file cannot be read during concatenation |
| `TemporaryOutputFileWriteException` | `RuntimeException` | Writing the temporary pre-swap artefact fails |
| `UndefinedInstanceMethodException` | `BadMethodCallException` | `__call()` intercepts a call to a non-existent instance method |
| `UndefinedStaticMethodException` | `BadMethodCallException` | `__callStatic()` intercepts a call to a non-existent static method |
