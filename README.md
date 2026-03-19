# Wingman — Vortex

**Wingman Vortex** is a high-performance, prioritised class discovery engine designed for modular PHP applications. Unlike standard PSR-4 loaders, Wingman provides **Case-Insensitive resolution**, a **Weighted Priority Queue**, and **Multi-Tiered Caching** (APCu and in-memory static caches) to ensure maximum OS portability and production-grade speed.

## Features

* **Case-Insensitive Resolution:** Seamlessly bridges the gap between Windows and Linux environments by resolving fuzzy paths to their exact case-sensitive matches.
* **Priority-Based Execution:** Assign weights to different loaders to ensure Core or Vendor classes take precedence.
* **Persistence via APCu:** Shared memory caching persists resolved paths across HTTP requests, reducing Disk I/O to near-zero.
* **Telemetry & Logging:** Comprehensive logging of the discovery process for easy debugging of "Class not found" errors.

## Installation

Install the package via Composer:

```bash
composer require wingman/vortex

```

## Basic Usage

Register a new autoloader by providing a unique name and a `pathFinder` callable.

```php
use Wingman\Vortex\Autoloader;

// Define a loader for your App namespace
Autoloader::register('app_loader', function ($class) {
    // Return a string path or an array ['class' => $class, 'path' => $path]
    return __DIR__ . '/src/' . $class . '.php';
}, 100); // Priority 100

```

### Advanced: Custom Extensions & Priority

You can fine-tune how the autoloader behaves for specific modules.

```php
$loader = Autoloader::from('modules', function($class) {
    return "/path/to/modules/" . $class;
})
->setExtensions(['php', 'inc', 'module'])
->setPriority(50)
->register();

```

## Production Performance

For production environments, ensure the **APCu** extension is enabled. The autoloader automatically detects it and begins caching resolved paths in shared memory.

### Clearing the Cache

When deploying new code, clear the persistent cache to avoid stale path references:

```php
use Wingman\Vortex\Autoloader;

Autoloader::clearCache();

```

## Debugging

If a class isn't loading as expected, you can dump the telemetry log of a specific autoloader:

```php
$data = Autoloader::get('app_loader')->dump();
print_r($data['log']);

```

## Long-Running Workers

For queue workers or daemon-style processes, reset runtime state between work units:

```php
use Wingman\Vortex\RuntimeGuard;

while ($job = getNextJob()) {
    try {
        handleJob($job);
    }
    finally {
        RuntimeGuard::afterWorkUnit();
    }
}
```

If you need to reset both in-memory and APCu caches between jobs:

```php
RuntimeGuard::afterWorkUnitFully();
```

If a worker handles mixed tenants/environments, you can also reset automatic
bundling configuration defaults between work units:

```php
RuntimeGuard::afterWorkUnit(clearRegistry: false, resetAutoBundlingConfiguration: true);
```

## Bundle Generation

The package includes a deterministic bundler for warm-up/build phases.

### Automatic Mode

You can enable automatic bundling so Autoloader handles both runtime bundle use and shutdown artefact generation.

Direct settings accept short keys, so you do not need to pass full dotted keys.

```php
use Wingman\Vortex\Autoloader;

Autoloader::configureAutoBundling([
    "enabled" => true,
    "bundleFile" => __DIR__ . "/cache/bundle.default.php",
    "preloadScriptFile" => __DIR__ . "/cache/preload.default.php",
    "profile" => "default",
    "buildOnShutdown" => true,
    "generateBundle" => true,
    "generatePreload" => true,
    "useBundleAtRuntime" => true
]);
```

You can also load the same settings from one or more Cortex-supported configuration files (`.ini`, `.json`, `.yaml`, `.php`, directories, layered imports, etc.):

```php
use Wingman\Vortex\Autoloader;

Autoloader::configureAutoBundling(__DIR__ . "/config/autoloader.json");
Autoloader::configureAutoBundling([
    __DIR__ . "/config/autoloader.default.ini",
    __DIR__ . "/config/autoloader.production.yaml",
]);
```

Or hydrate from a global/shared Cortex `Configuration` instance:

```php
use Wingman\Vortex\Autoloader;
use Wingman\Vortex\Bridge\Cortex\Configuration;

$global = Configuration::find();

if ($global !== null) {
    Autoloader::configureAutoBundling($global);
}
```

When you pass file paths (single source or layered sources), Autoloader reads the global configuration as a baseline and applies imports on a cloned instance, so global configuration values are available without mutating the shared global object.

When using files or a shared `Configuration`, the expected dotted prefix is `vortex.bundling.auto.*`.
Because `ConfigGroup` is used internally, direct map input can stay short (`enabled`, `profile`, `bundleFile`, etc.).

## Exceptions

Autoloader exposes a package-level exception contract:

* `Wingman\Vortex\Interfaces\VortexException`
* `Wingman\Vortex\Exceptions\LackOfEligibleFilesException`
* `Wingman\Vortex\Exceptions\DuplicateAutoloaderNameException`
* `Wingman\Vortex\Exceptions\InvalidExtensionTypeException`
* `Wingman\Vortex\Exceptions\SourceFileReadException`

Only the interface is generic; exception classes are purpose-specific so consumers can catch exact failure cases or the grouped package contract.

When automatic mode is enabled:

1. If the configured bundle file already exists, it is loaded before normal resolution.
2. At shutdown, loaded-file telemetry is converted into updated bundle/preload artefacts.
3. Deterministic signatures prevent unnecessary rewrites when inputs are unchanged.

When `failOnBuildError` is enabled, `buildAutoBundlesAtShutdown()` can throw in direct/manual calls. For shutdown registration, wraps execution in a safe runner and stores the last failure, retrievable via:

```php
$lastError = Autoloader::getLastAutoBundlingError();
```

### Step 1: Capture loaded files

```php
use Wingman\Vortex\Autoloader;

$files = Autoloader::getLoadedFiles();
```

### Step 2: Generate a monolithic bundle

```php
use Wingman\Vortex\Bundler;

$result = (new Bundler())
    ->setFiles($files)
    ->setExcludePatterns([
        '/vendor\\/legacy\\//',
    ])
    ->generateBundle(__DIR__ . "/cache/bundle.login.php", "login");

var_dump($result["generated"]); // false when signature is unchanged
```

### Step 3: Generate an OPcache preload script

```php
use Wingman\Vortex\Bundler;

$result = (new Bundler())
    ->setFiles($files)
    ->generatePreloadScript(__DIR__ . "/cache/preload.login.php", "login");
```

### Safety Notes

1. Run bundling during deployment/warm-up, not on every request.
2. Keep unsafe file skipping enabled unless you have audited all files.
3. Use profile-specific bundles to avoid loading unnecessary code.
4. Keep the generated `.meta.json` files; they drive change detection and invalidate correctly when source content changes.

### Production Checklist

1. **Enable APCu:** Check `php -m | grep apcu`.
2. **Verify Permissions:** Ensure the web server has read access to the directories defined in your `pathFinder`.
3. **Deploy Script:** Add `Autoloader::clearCache();` or a PHP service reload to your CI/CD pipeline.

---

## Documentation

| Document | Description |
| -------- | ----------- |
| [API Reference](docs/API-Reference.md) | Complete method signatures and parameter tables for every public class |
| [Auto-Bundling](docs/Auto-Bundling.md) | Full lifecycle guide — configuration options, all input forms, runtime loading, shutdown generation, and production checklist |
| [Bundler](docs/Bundler.md) | Narrative guide covering file collection, safety controls, exclusion patterns, deterministic signatures, and atomic writes |
| [Workers](docs/Workers.md) | Long-running process patterns with `RuntimeGuard`, registry clearing, and reset reference matrix |
| [Exceptions](docs/Exceptions.md) | Full exception hierarchy, throw sites, and resolution guidance for every exception class |

---

## License

This project is licensed under the **Mozilla Public License 2.0 (MPL 2.0)**.

Wingman Vortex is part of the **Wingman Framework**, Copyright (c) 2025–2026 Angel Politis.

For the full licence text, please see the [LICENSE](LICENSE) file.
