# Auto-Bundling

The automatic bundling lifecycle lets the Autoloader manage both **runtime bundle loading** and **shutdown artefact generation** without any manual build steps. Configure it once at application bootstrap; the Autoloader handles the rest.

---

## Contents

- [Auto-Bundling](#auto-bundling)
  - [Contents](#contents)
  - [How It Works](#how-it-works)
  - [Quick Start](#quick-start)
  - [Configuration Options](#configuration-options)
  - [Configuration Sources](#configuration-sources)
    - [Direct Array](#direct-array)
    - [Configuration File](#configuration-file)
    - [Multiple Configuration Files](#multiple-configuration-files)
    - [Cortex Configuration Instance](#cortex-configuration-instance)
  - [Runtime Bundle Loading](#runtime-bundle-loading)
  - [Shutdown Generation](#shutdown-generation)
  - [Error Handling](#error-handling)
  - [Profiles](#profiles)
  - [Force Regeneration](#force-regeneration)
  - [Production Checklist](#production-checklist)

---

## How It Works

1. `Autoloader::configureAutoBundling()` is called at bootstrap with chosen settings.
2. When the first class resolution request arrives, the SPL handler triggers
   `bootstrapAutoBundling()` internally (once per process):
   - If `useBundleAtRuntime` is `true` and the bundle file exists, it is `require_once`'d to bypass individual file lookups entirely.
   - If `buildOnShutdown` is `true`, a shutdown function is registered.
3. Normal autoloading proceeds and each successfully resolved file is recorded in the Autoloader log.
4. At PHP shutdown, `executeAutoBundlesAtShutdownSafely()` fires and calls the `Bundler` with all files collected during the request to regenerate artefacts when the signature has changed.

This means the bundle stays up to date **automatically** as new classes are discovered across requests — no CI step or cron job needed.

---

## Quick Start

```php
use Wingman\Vortex\Autoloader;

// Register your application autoloader.
Autoloader::register('app', function ($class) {
    return __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
});

// Enable automatic bundling.
Autoloader::configureAutoBundling([
    'enabled' => true,
    'bundleFile' => __DIR__ . '/cache/bundle.php',
    'preloadScriptFile'=> __DIR__ . '/cache/preload.php',
    'useBundleAtRuntime' => true,
    'buildOnShutdown' => true
]);
```

On the first request (no bundle exists yet): every class is resolved individually, the bundle is generated at shutdown.

On subsequent requests: the bundle is `require_once`'d at bootstrap; no per-class file I/O occurs.

---

## Configuration Options

| Key | Type | Default | Description |
| --- | ---- | ------- | ----------- |
| `enabled` | `bool` | `false` | Master switch. Nothing happens unless this is `true`. |
| `bundleFile` | `string\|null` | `null` | Absolute path to the monolithic bundle output file. |
| `preloadScriptFile` | `string\|null` | `null` | Absolute path to the OPcache preload script output file. |
| `profile` | `string` | `"default"` | Profile label embedded in generated artefact headers. |
| `buildOnShutdown` | `bool` | `true` | Register a shutdown function to regenerate artefacts. |
| `generateBundle` | `bool` | `true` | Whether to write the bundle artefact at shutdown. |
| `generatePreload` | `bool` | `true` | Whether to write the preload script artefact at shutdown. |
| `forceGeneration` | `bool` | `false` | Skip the signature check and always regenerate. |
| `failOnBuildError` | `bool` | `false` | Throw `AutoBundlingBuildFailureException` on generation errors instead of logging silently. |
| `useBundleAtRuntime` | `bool` | `true` | `require_once` the bundle file at bootstrap when it exists. |
| `excludePatterns` | `string[]` | `[]` | Regex or substring patterns to exclude files from artefacts. |
| `skipUnsafeFiles` | `bool` | `true` | Skip files containing `__DIR__`, `exit`, `require`, etc. during bundling. |
| `stripWhitespace` | `bool` | `false` | Apply `php_strip_whitespace()` to each source file before concatenation. |

---

## Configuration Sources

`configureAutoBundling()` accepts four input types. Calling it multiple times is safe — each call merges into the existing settings.

### Direct Array

Short property keys (matching `AutoBundlingSettings` property names) and full dotted keys (`vortex.bundling.auto.*`) are both accepted in the same call:

```php
Autoloader::configureAutoBundling([
    'enabled' => true,
    'bundleFile' => '/var/www/cache/bundle.php',
    'vortex.bundling.auto.stripWhitespace' => false
]);
```

### Configuration File

Pass a single file path. Cortex loads the file format automatically (`.ini`, `.json`, `.yaml`, `.php`, or a directory of files):

```php
Autoloader::configureAutoBundling(__DIR__ . '/config/autoloader.json');
```

Example `autoloader.json`:

```json
{
    "autoloader": {
        "bundling": {
            "auto": {
                "enabled": true,
                "bundleFile": "/var/www/cache/bundle.php",
                "preloadScriptFile": "/var/www/cache/preload.php",
                "buildOnShutdown": true,
                "useBundleAtRuntime": true
            }
        }
    }
}
```

### Multiple Configuration Files

Pass an indexed array of strings. Files are merged in order; later files override earlier ones:

```php
Autoloader::configureAutoBundling([
    __DIR__ . '/config/autoloader.defaults.ini',
    __DIR__ . '/config/autoloader.production.yaml'
]);
```

### Cortex Configuration Instance

When a global/shared `Configuration` object is already present (e.g. from application bootstrap), pass it directly. The Autoloader clones it before importing settings so global state is not mutated:

```php
use Wingman\Vortex\Bridge\Cortex\Configuration;

$global = Configuration::find();

if ($global !== null) {
    Autoloader::configureAutoBundling($global);
}
```

---

## Runtime Bundle Loading

When `useBundleAtRuntime` is `true` and the bundle file path is configured, the Autoloader loads the bundle on the first class resolution attempt of the request. After the bundle is loaded, classes defined inside it are already in memory — the SPL handler never needs to touch disk for them.

The bundle file must be the absolute path written by a previous `generateBundle()` run. An invalid or missing path is silently ignored; normal per-class resolution continues.

---

## Shutdown Generation

When `buildOnShutdown` is `true`, the Autoloader registers a shutdown function during bootstrap. At the end of the request, `executeAutoBundlesAtShutdownSafely()` fires and calls the `Bundler` with the list of all files loaded during the request.

The Bundler computes a deterministic signature. If nothing changed since the last run, no I/O occurs. Generation only triggers when:

- New class files were resolved that are not yet in the bundle.
- Source files tracked in the bundle metadata were modified.
- `forceGeneration` is `true`.

This makes repeated-request overhead negligible.

---

## Error Handling

By default, generation failures are silently contained — the application continues and the error is accessible through:

```php
$e = Autoloader::getLastAutoBundlingError();
```

To surface errors aggressively (e.g. in staging), enable `failOnBuildError`:

```php
Autoloader::configureAutoBundling([
    'enabled' => true,
    'failOnBuildError' => true,
    'bundleFile' => __DIR__ . '/cache/bundle.php'
]);
```

When the shutdown function fires and generation fails, an `AutoBundlingBuildFailureException` is thrown. The previous error is also available via `getLastAutoBundlingError()`.

Clear the error between worker jobs:

```php
Autoloader::clearLastAutoBundlingError();
```

---

## Profiles

The `profile` string is embedded in the generated artefact header for traceability. Use it to distinguish artefacts from different environments or deployment stages:

```php
Autoloader::configureAutoBundling([
    'profile' => 'production-2026-03-19',
    'bundleFile' => '/var/www/cache/bundle.php'
]);
```

The profile is also factored into the deterministic **signature**, so changing the profile name triggers regeneration even when the source files are unchanged.

---

## Force Regeneration

Force an unconditional rebuild regardless of the signature check:

```php
Autoloader::configureAutoBundling([
    'enabled' => true,
    'forceGeneration' => true,
    'bundleFile' => '/var/www/cache/bundle.php'
]);
```

Useful after a deployment when you want the first post-deployment request to regenerate artefacts immediately. Remember to disable it again in subsequent requests or revert via `resetRuntimeState(resetAutoBundlingConfiguration: true)`.

---

## Production Checklist

- Ensure the bundle file's parent directory is writable by the web server process.
- Set `skipUnsafeFiles: true` (the default) unless you have audited every source file for unsafe tokens.
- Use `stripWhitespace: false` unless you have a specific size constraint; stripping removes comments that may be referenced by `@psalm`, `@phpstan`, or other tools.
- Call `Autoloader::clearCache()` after every deployment to flush APCu resolved-path entries.
- If using the preload script, reload PHP-FPM or the web server after the bundle is regenerated so OPcache picks up the new file list.
- Monitor `getLastAutoBundlingError()` in your error tracking pipeline to catch silent failures.
