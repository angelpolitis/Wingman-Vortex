# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - 2026-03-19

### Added

- **Initial release** of Wingman Vortex.
- **Priority queue:** Registry system for weighted loader execution, sorted by priority descending, then by creation date ascending for stable tie-breaking.
- **Fuzzy resolution:** Case-insensitive path matching via `resolveCI()` for Linux/Windows cross-compatibility, with per-request directory scan caching.
- **Multi-tiered caching:** APCu shared memory caching (24-hour TTL) with in-process static fallback for resolved paths and directory scans.
- **Telemetry:** Comprehensive per-resolution log entries and `dump()` method for tracing class discovery failures.
- **Fluent builder API:** `Autoloader::from()` named constructor enabling a chainable setup flow before calling `register()`.
- **Extension control:** Per-autoloader file extension list via `setExtensions()`, defaulting to `["php", "inc"]`.
- **Automatic bundling lifecycle:** `Autoloader::configureAutoBundling()` accepting a direct settings map, one or more configuration-file paths, or a Cortex `Configuration` instance.
- **Settings object model:** `Objects\AutoBundlingSettings` annotated with `#[ConfigGroup("vortex.bundling.auto")]`, accepting both short property keys and full dotted keys.
- **Runtime bootstrap integration:** Bundle preloading at request startup and shutdown-driven artefact generation via `register_shutdown_function`.
- **Build-time bundling utility:** `Bundler` class with deterministic SHA-256 artefact signatures, JSON metadata sidecars, exclusion patterns, unsafe-file token inspection, and optional `php_strip_whitespace()` compression.
- **OPcache preload generation:** `Bundler::generatePreloadScript()` producing `opcache_compile_file()` scripts for server warm-up.
- **Atomic artefact writes:** Bundle and metadata files are written through a lock-file acquisition and `rename()` atomic swap to prevent partial artefact reads by concurrent processes.
- **Configuration isolation:** Auto-bundling configuration loaded from source files starts from a clone of the global/shared Cortex configuration when one is present, avoiding mutation of shared state.
- **Exception grouping contract:** `Interfaces\VortexException` marker interface implemented by all package exceptions, enabling single-clause catch coverage.
- **Purpose-specific exceptions:** Eleven concrete exception classes covering every distinct failure scenario — `AutoBundlingBuildFailureException`, `DuplicateAutoloaderNameException`, `InvalidExtensionTypeException`, `LackOfEligibleFilesException`, `OutputDirectoryCreationException`, `OutputFileMoveException`, `OutputLockAcquisitionException`, `SourceFileReadException`, `TemporaryOutputFileWriteException`, `UndefinedInstanceMethodException`, `UndefinedStaticMethodException`.
- **Runtime guard utility:** `RuntimeGuard` with `afterWorkUnit()` and `afterWorkUnitFully()` helpers for resetting in-memory and APCu state between jobs in long-running workers.
- **Worker reset controls:** `Autoloader::resetRuntimeState()` supporting optional registry clearing and auto-bundling configuration resets.
- **Error containment:** `Autoloader::run()` captures all `Throwable` errors during path resolution and path-finder execution, recording them in the log without breaking the SPL chain.
- **Safe shutdown wrapper:** `executeAutoBundlesAtShutdownSafely()` contains shutdown-bundling failures and exposes them via `getLastAutoBundlingError()`.
- **Cortex bridge:** Bridge stubs for `Configuration`, `#[ConfigGroup]`, and `#[Configurable]` so configuration-driven features remain functional when Cortex is not installed.
- **Documentation:** Full API reference, auto-bundling guide, bundler guide, exception catalogue, worker lifecycle documentation, and MPL 2.0 licensing.
