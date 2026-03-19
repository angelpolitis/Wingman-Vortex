# Workers

PHP processes that do not exit between jobs — queue consumers, daemon loops, socket servers — share all static state across iterations. Without explicit resets, the Autoloader accumulates stale cache entries, grows its log unboundedly, and may carry auto-bundling state across logically separate jobs.

`RuntimeGuard` provides two static helpers for this purpose.

---

## Contents

- [Workers](#workers)
  - [Contents](#contents)
  - [The Problem](#the-problem)
  - [RuntimeGuard](#runtimeguard)
    - [`afterWorkUnit()`](#afterworkunit)
    - [`afterWorkUnitFully()`](#afterworkunitfully)
  - [Resetting the Registry](#resetting-the-registry)
  - [Resetting Auto-Bundling Configuration](#resetting-auto-bundling-configuration)
  - [Using `Autoloader::resetRuntimeState()` Directly](#using-autoloaderresetruntimestate-directly)
  - [What Gets Reset](#what-gets-reset)

---

## The Problem

Consider a queue worker that processes thousands of jobs per run:

```php
while ($job = $queue->pop()) {
    $handler = new $job->handlerClass();
    $handler->handle($job);
}
```

After each job:

- The `$resolvedCache` and `$dirCache` arrays grow without bound as new class paths accumulate.
- If separate jobs resolve conflicting class-path entries (e.g. tenant-specific overlays), stale entries from an earlier job are reused by later ones.
- Auto-bundling bootstrapping flags (`$autoBundlingBootstrapped`, `$autoBundlingGenerated`) remain set, preventing the next job from triggering bootstrap or generation.
- Per-autoloader `$log` arrays keep every resolution event from every job in memory.

---

## RuntimeGuard

### `afterWorkUnit()`

Clears **in-memory** state only. APCu entries are untouched.

```php
use Wingman\Vortex\RuntimeGuard;

while ($job = $queue->pop()) {
    try {
        $handler = new $job->handlerClass();
        $handler->handle($job);
    }
    finally {
        RuntimeGuard::afterWorkUnit();
    }
}
```

This is the appropriate call when:

- The same classes are expected across jobs (APCu cache keeps resolving them fast).
- File changes between jobs are not expected.
- Per-request in-memory state must not bleed across jobs.

---

### `afterWorkUnitFully()`

Clears **in-memory state and APCu** entries.

```php
use Wingman\Vortex\RuntimeGuard;

while ($job = $queue->pop()) {
    try {
        $handler = new $job->handlerClass();
        $handler->handle($job);
    }
    finally {
        RuntimeGuard::afterWorkUnitFully();
    }
}
```

Use this when:

- Jobs process different tenants or environments with distinct file layouts.
- Files may change between jobs (e.g. a deployment happens mid-run).
- An APCu entry resolved in one job must not be reused in the next.

---

## Resetting the Registry

By default, `afterWorkUnit()` and `afterWorkUnitFully()` preserve the registered autoloaders. To also clear the registry (remove all `Autoloader::register()` entries), pass `clearRegistry: true`:

```php
RuntimeGuard::afterWorkUnit(clearRegistry: true);
```

This is rarely needed. Use it when each job must bootstrap its own loader configuration from scratch, for example in a multi-tenant worker where loader paths differ per job.

After clearing the registry, re-register loaders before the next job:

```php
while ($job = $queue->pop()) {
    Autoloader::register('tenant_' . $job->tenantId, function ($class) use ($job) {
        return "/tenants/{$job->tenantId}/src/" . str_replace('\\', '/', $class) . '.php';
    });

    try {
        $handler = new $job->handlerClass();
        $handler->handle($job);
    }
    finally {
        RuntimeGuard::afterWorkUnit(clearRegistry: true);
    }
}
```

---

## Resetting Auto-Bundling Configuration

When jobs have different auto-bundling requirements, pass `resetAutoBundlingConfiguration: true` to restore all auto-bundling statics to their defaults before the next `configureAutoBundling()` call:

```php
RuntimeGuard::afterWorkUnit(resetAutoBundlingConfiguration: true);

// Then re-configure for the next job.
Autoloader::configureAutoBundling([
    'enabled' => true,
    'bundleFile' => "/cache/{$job->profile}/bundle.php"
]);
```

Without this flag, the previous job's `bundleFile` path, `profile`, exclusion patterns, and other settings remain active for the following job.

---

## Using `Autoloader::resetRuntimeState()` Directly

`RuntimeGuard` is a thin convenience wrapper. When you need fine-grained control, call `Autoloader::resetRuntimeState()` directly:

```php
use Wingman\Vortex\Autoloader;

// In-memory reset only, keep registry and bundling config.
Autoloader::resetRuntimeState();

// In-memory reset + registry wipe.
Autoloader::resetRuntimeState(clearRegistry: true);

// Full in-memory + config reset.
Autoloader::resetRuntimeState(
    clearRegistry: true,
    resetAutoBundlingConfiguration: true
);

// Then clear APCu separately.
Autoloader::clearCache();
```

---

## What Gets Reset

| What | `afterWorkUnit()` | `afterWorkUnitFully()` | `clearRegistry: true` | `resetAutoBundlingConfiguration: true` |
| ---- | :---: | :---: | :---: | :---: |
| `$resolvedCache` | ✅ | ✅ | ✅ | ✅ |
| `$dirCache` | ✅ | ✅ | ✅ | ✅ |
| Per-autoloader `$log` | ✅ | ✅ | ✅ | ✅ |
| `$autoBundlingBootstrapped` | ✅ | ✅ | ✅ | ✅ |
| `$autoBundlingGenerated` | ✅ | ✅ | ✅ | ✅ |
| `$autoBundlingLastError` | ✅ | ✅ | ✅ | ✅ |
| APCu `wingman_path_*` keys | ❌ | ✅ | ❌ | ❌ |
| `$registry` (all loaders) | ❌ | ❌ | ✅ | ❌ |
| Auto-bundling settings (all) | ❌ | ❌ | ❌ | ✅ |
