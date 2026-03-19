# Bundler

The `Bundler` compiles a collection of PHP source files into one or both of two artefact types:

- **Bundle** — a single monolithic PHP file that can be `require_once`'d to load many classes without any autoloader overhead.
- **Preload script** — a PHP file that calls `opcache_compile_file()` for each discovered class, used as the server's `opcache.preload` script to warm OPcache at startup.

`Bundler` is an offline-oriented, build-time tool. Do not call it on the hot request path; see [Auto-Bundling](Auto-Bundling.md) for how to invoke it automatically at shutdown.

---

## Contents

- [Bundler](#bundler)
  - [Contents](#contents)
  - [File Collection](#file-collection)
    - [Starting from captured runtime paths](#starting-from-captured-runtime-paths)
    - [Starting from an explicit file list](#starting-from-an-explicit-file-list)
  - [Bundle Generation](#bundle-generation)
  - [Preload Generation](#preload-generation)
  - [Safety Controls](#safety-controls)
  - [Exclusion Patterns](#exclusion-patterns)
  - [Deterministic Signatures](#deterministic-signatures)
  - [Atomic Writes](#atomic-writes)
  - [Generation Summary](#generation-summary)

---

## File Collection

A `Bundler` instance maintains an internal queue of normalised, validated file paths.

### Starting from captured runtime paths

The most common approach is to let the Autoloader record which files it resolved during a warm-up run, then hand those paths directly to the Bundler:

```php
use Wingman\Vortex\Bundler;

$bundler = (new Bundler())->captureLoadedFiles();
```

`captureLoadedFiles()` calls `Autoloader::getLoadedFiles()` internally, which collects every successfully resolved path from every registered autoloader. An optional `$minPriority` argument limits which autoloaders contribute:

```php
$bundler->captureLoadedFiles(minPriority: 50);
```

### Starting from an explicit file list

Pass a file list at construction time or replace it later:

```php
$bundler = new Bundler([
    '/var/www/src/App/Controller.php',
    '/var/www/src/App/Model.php',
]);

// Or replace entirely:
$bundler->setFiles(['/var/www/src/App/Service.php']);

// Or add one file at a time:
$bundler->addFile('/var/www/src/App/Repository.php');
```

`addFile()` silently skips files that do not exist, are not regular files, or are not readable. Duplicate real paths (as resolved by `realpath()`) are deduplicated.

---

## Bundle Generation

```php
use Wingman\Vortex\Bundler;

$bundler = (new Bundler())->captureLoadedFiles();

$summary = $bundler->generateBundle(
    outputFile: '/var/www/cache/bundle.php',
    profile: 'production',
    force: false
);
```

The output file begins with a header comment block followed by the concatenated PHP payload of each eligible source file. Opening `<?php` tags and closing `?>` tags are stripped from payloads before concatenation. An optional `php_strip_whitespace()` pass reduces file size.

A metadata side-car is written at `{outputFile}.meta.json` recording the profile, generation timestamp, file list, and signature.

To use the generated bundle at runtime:

```php
require_once '/var/www/cache/bundle.php';
```

Or configure `useBundleAtRuntime` in auto-bundling mode to have the Autoloader load it automatically. See [Auto-Bundling](Auto-Bundling.md).

---

## Preload Generation

```php
$summary = $bundler->generatePreloadScript(
    outputFile: '/var/www/cache/preload.php',
    profile: 'production',
    force: false
);
```

The generated script checks `function_exists("opcache_compile_file")` and then calls it for each eligible file. Token-level safety checks are skipped for preload mode because files are not concatenated.

Register the script in `php.ini`:

```ini
opcache.preload = /var/www/cache/preload.php
opcache.preload_user = www-data
```

Restart PHP-FPM or the web server after every bundle regeneration.

---

## Safety Controls

Before including a file in a **bundle** (concatenation mode only), the Bundler tokenises its source and rejects it when any of the following tokens are found:

| Token | Reason |
| ----- | ------ |
| `T_DIR` (`__DIR__`) | Path becomes wrong when executed from a different file location |
| `T_FILE` (`__FILE__`) | Same reason as `__DIR__` |
| `T_EXIT` | Terminates the entire process |
| `T_EVAL` | Code injection risk; unpredictable semantics in concatenated context |
| `T_INCLUDE` / `T_INCLUDE_ONCE` | Already-relative paths will break |
| `T_REQUIRE` / `T_REQUIRE_ONCE` | Same as include |

Rejected files are recorded in the summary under `skippedFiles` with `reason => "unsafe"`.

To disable safety checks and include all files regardless:

```php
$bundler->setSkipUnsafeFiles(false);
```

Use with caution: including unsafe files in a bundle will likely cause runtime errors.

---

## Exclusion Patterns

Register patterns to unconditionally exclude matching files from any artefact:

```php
$bundler->setExcludePatterns([
    '/vendor/',          // Plain substring match
    '#/tests/#',         // Regex match
    '~bootstrap~',       // Regex match
]);
```

Each pattern is tested in order:

1. If it is a valid regex (tested via `preg_match()`), it is applied as a regex against the full file path.
2. Otherwise it is treated as a plain substring and tested via `str_contains()`.

Excluded files are recorded in the summary under `skippedFiles` with `reason => "excluded"`.

---

## Deterministic Signatures

Before writing any artefact, the Bundler computes a SHA-256 signature over:

- The file path, `mtime`, and `sha256` hash of each eligible file.
- The generation mode (`bundle` or `preload`).
- Mode-specific options (`profile`, `stripWhitespace`).

If the artefact already exists **and** its side-car signature matches, generation is skipped:

```php
// $summary['generated'] will be false — no disk write occurred.
$summary = $bundler->generateBundle('/var/www/cache/bundle.php');
```

Force regeneration regardless of the signature:

```php
$summary = $bundler->generateBundle('/var/www/cache/bundle.php', force: true);
```

---

## Atomic Writes

To prevent partial reads by concurrent processes (e.g. multiple web workers triggering shutdown at the same time), artefacts are written **atomically**:

1. A `.lock` file is created and held with `LOCK_EX` while writing.
2. Content is written to a temporary file in the same directory.
3. `rename()` atomically swaps the temporary file to the final path.
4. Both the main artefact and the `.meta.json` side-car are written under the same lock.

This ensures that any process reading the bundle file sees either the old complete version or the new complete version, never a partial write.

---

## Generation Summary

Both `generateBundle()` and `generatePreloadScript()` return an associative array:

```php
[
    'generated' => bool,        // true when the artefact was (re)written
    'mode' => string,           // 'bundle' or 'preload'
    'outputFile' => string,     // Absolute path to the artefact
    'metaFile' => string,       // Absolute path to "{outputFile}.meta.json"
    'profile' => string,
    'signature' => string,      // SHA-256 deterministic signature
    'files' => string[],        // File paths included in the artefact
    'skippedFiles' => array,    // [['file' => ..., 'reason' => 'excluded'|'unsafe'], ...]
]
```

When `generated` is `false`, the artefact was unchanged (signature matched, `force` was not set) and no I/O occurred.
