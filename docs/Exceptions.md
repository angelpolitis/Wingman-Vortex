# Exceptions

All exceptions thrown by Wingman Vortex implement the `Wingman\Vortex\Interfaces\VortexException` marker interface, which extends `Throwable`. This allows callers to catch any Autoloader exception with a single clause:

```php
use Wingman\Vortex\Interfaces\VortexException;

try {
    Autoloader::register('app', $pathFinder);
}
catch (VortexException $e) {
    // Handles any Autoloader-specific exception.
}
```

Every exception class lives in the `Wingman\Vortex\Exceptions` namespace.

---

## Exception Hierarchy

```
Throwable
└── VortexException (interface)
    ├── LogicException
    │   └── DuplicateAutoloaderNameException
    ├── InvalidArgumentException
    │   └── InvalidExtensionTypeException
    ├── BadMethodCallException
    │   ├── UndefinedInstanceMethodException
    │   └── UndefinedStaticMethodException
    └── RuntimeException
        ├── AutoBundlingBuildFailureException
        ├── LackOfEligibleFilesException
        ├── OutputDirectoryCreationException
        ├── OutputFileMoveException
        ├── OutputLockAcquisitionException
        ├── SourceFileReadException
        └── TemporaryOutputFileWriteException
```

---

## The `VortexException` Interface

**`Wingman\Vortex\Interfaces\VortexException`**

A marker interface that extends `Throwable`. It carries no additional methods. Its sole purpose is to allow catch-all handling of every Autoloader-specific exception via a single type.

---

## Registration Exceptions

### `DuplicateAutoloaderNameException`

**Extends:** `LogicException`

Thrown by `Autoloader::register()` (both static and instance forms) when an autoloader with the same name already exists in `$registry`.

**Throw site:** `register__i()`

```php
// Throws DuplicateAutoloaderNameException — 'app' is already registered.
Autoloader::register('app', $pathFinder);
Autoloader::register('app', $otherPathFinder);
```

**Resolution:** Choose a unique name per autoloader. Use `Autoloader::get('app')` to check whether a name is already taken before registering.

---

## Extension Exceptions

### `InvalidExtensionTypeException`

**Extends:** `InvalidArgumentException`

Thrown by `Autoloader::setExtensions()` when the array contains at least one value that is not a string.

**Throw site:** `setExtensions()`

```php
// Throws InvalidExtensionTypeException — 42 is not a string.
Autoloader::get('app')->setExtensions(['php', 42]);
```

**Resolution:** Ensure every element of the extensions array is a non-empty string (e.g. `"php"`, `"inc"`).

---

## Magic Method Exceptions

These are thrown when code calls a method via `__call()` or `__callStatic()` that does not have a corresponding `{name}__i()` or `{name}__c()` implementation.

### `UndefinedInstanceMethodException`

**Extends:** `BadMethodCallException`

Thrown when `$autoloader->someMethod()` is invoked and no `someMethod__i()` method exists on the class.

### `UndefinedStaticMethodException`

**Extends:** `BadMethodCallException`

Thrown when `Autoloader::someMethod()` is invoked and no `someMethod__c()` method exists on the class.

---

## Auto-Bundling Exceptions

### `AutoBundlingBuildFailureException`

**Extends:** `RuntimeException`

Thrown by `buildAutoBundlesAtShutdown()` when:

- `$autoBundlingFailOnBuildError` is `true`, and
- An exception is raised during bundle or preload script generation.

The original exception is attached as the previous exception (`$e->getPrevious()`).

**Throw site:** `buildAutoBundlesAtShutdown()`

```php
Autoloader::configureAutoBundling([
    'enabled'          => true,
    'failOnBuildError' => true,
    'bundleFile'       => '/var/www/cache/bundle.php',
]);
```

When `failOnBuildError` is `false` (the default), the error is instead stored silently via `getLastAutoBundlingError()`.

---

## Bundler Exceptions

These exceptions are thrown by `Bundler::generateBundle()` and `Bundler::generatePreloadScript()` during artefact write operations.

### `LackOfEligibleFilesException`

**Extends:** `RuntimeException`

Thrown when the eligible file list is empty after applying exclusion patterns and safety filters. This happens when all queued files are either excluded by a pattern or rejected as unsafe (when `skipUnsafeFiles` is `true`).

**Throw sites:** `generateBundle()`, `generatePreloadScript()`

**Resolution:** Review exclusion patterns and the file queue. Temporarily call `setSkipUnsafeFiles(false)` to see whether safety filters are removing all files.

---

### `OutputDirectoryCreationException`

**Extends:** `RuntimeException`

Thrown when the parent directory of the output file does not exist and `mkdir()` fails to create it.

**Throw site:** `writeFileSetAtomically()`

**Resolution:** Ensure the configured output path is under a directory that the web server process has write permission to.

---

### `OutputLockAcquisitionException`

**Extends:** `RuntimeException`

Thrown when the `.lock` file used to serialise concurrent writers cannot be opened for writing. This is typically a permissions failure on the output directory.

**Throw site:** `writeFileSetAtomically()`

**Resolution:** Check directory permissions. The web server process must be able to create files in the output directory.

---

### `OutputFileMoveException`

**Extends:** `RuntimeException`

Thrown when the atomic `rename()` of the temporary file to its final path fails. This can occur when the temporary file and the destination path are on different filesystems.

**Throw site:** `writeFileSetAtomically()`

**Resolution:** Always configure `bundleFile` and `preloadScriptFile` to a path on the same filesystem partition as the PHP temporary directory (`sys_get_temp_dir()`), or within a directory writable by the process.

---

### `SourceFileReadException`

**Extends:** `RuntimeException`

Thrown during bundle payload extraction when `file_get_contents()` returns `false` for a queued source file. Only possible in bundle mode (file contents are read for concatenation); not thrown during preload script generation.

**Throw site:** `extractPhpPayload()`

**Resolution:** Ensure all queued source files remain readable throughout the request lifetime.

---

### `TemporaryOutputFileWriteException`

**Extends:** `RuntimeException`

Thrown when writing the temporary file (before the atomic swap) fails. This can be caused by a full disk, a permissions issue, or an I/O error.

**Throw site:** `writeFileSetAtomically()`

**Resolution:** Check disk space and permissions on the output directory.
