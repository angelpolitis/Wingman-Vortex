<?php
    /**
     * Project Name:    Wingman Vortex - Runtime State Tests
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2026-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Autoloader.Tests namespace.
    namespace Wingman\Vortex\Tests;

    # Import the following classes to the current scope.
    use RuntimeException;
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Vortex\Autoloader;

    /**
     * Tests for resetRuntimeState(), clearRuntimeCaches(), and the last-bundling-error API.
     * Validates full state isolation for long-running process (worker) use-cases.
     */
    class RuntimeStateTest extends AutoloaderTestCase {
        // ── clearRuntimeCaches() ──────────────────────────────────────────────

        #[Group("RuntimeState")]
        #[Define(
            name: "clearRuntimeCaches() — Empties dirCache",
            description: "clearRuntimeCaches() clears the in-memory directory scan cache."
        )]
        public function testClearRuntimeCachesEmptiesDirCache () : void {
            $this->writeStaticProperty("dirCache", ["some/path" => ["file.php"]]);

            Autoloader::clearRuntimeCaches();

            $this->assertEmpty($this->readStaticProperty("dirCache"), "clearRuntimeCaches() must empty dirCache.");
        }

        #[Group("RuntimeState")]
        #[Define(
            name: "clearRuntimeCaches() — Empties resolvedCache",
            description: "clearRuntimeCaches() clears the in-memory resolved-path cache."
        )]
        public function testClearRuntimeCachesEmptiesResolvedCache () : void {
            $this->writeStaticProperty("resolvedCache", ["Foo\\Bar" => "/var/www/src/Foo/Bar.php"]);

            Autoloader::clearRuntimeCaches();

            $this->assertEmpty($this->readStaticProperty("resolvedCache"), "clearRuntimeCaches() must empty resolvedCache.");
        }

        // ── resetRuntimeState() — flags ───────────────────────────────────────

        #[Group("RuntimeState")]
        #[Define(
            name: "resetRuntimeState() — Resets autoBundlingBootstrapped",
            description: "After resetRuntimeState(), the bootstrapped flag is false."
        )]
        public function testResetRuntimeStateClearsBootstrappedFlag () : void {
            $this->writeStaticProperty("autoBundlingBootstrapped", true);

            Autoloader::resetRuntimeState();

            $this->assertFalse($this->readStaticProperty("autoBundlingBootstrapped"), "autoBundlingBootstrapped should be false after reset.");
        }

        #[Group("RuntimeState")]
        #[Define(
            name: "resetRuntimeState() — Resets autoBundlingGenerated",
            description: "After resetRuntimeState(), the generated flag is false."
        )]
        public function testResetRuntimeStateClearsGeneratedFlag () : void {
            $this->writeStaticProperty("autoBundlingGenerated", true);

            Autoloader::resetRuntimeState();

            $this->assertFalse($this->readStaticProperty("autoBundlingGenerated"), "autoBundlingGenerated should be false after reset.");
        }

        // ── resetRuntimeState() — log ─────────────────────────────────────────

        #[Group("RuntimeState")]
        #[Define(
            name: "resetRuntimeState() — Clears All Autoloader Logs",
            description: "resetRuntimeState() wipes the per-entry log of every registered autoloader."
        )]
        public function testResetRuntimeStateClearsLogs () : void {
            $al = Autoloader::register("test", fn ($c) => null);
            $al->run("Any\\Class");
            $this->assertNotEmpty($al->getLog(), "Log should not be empty before reset.");

            Autoloader::resetRuntimeState();

            $this->assertEmpty($al->getLog(), "Log should be empty after resetRuntimeState().");
        }

        // ── resetRuntimeState() — registry ────────────────────────────────────

        #[Group("RuntimeState")]
        #[Define(
            name: "resetRuntimeState() — Preserves Registry by Default",
            description: "Without clearRegistry = true, the autoloader registry is kept intact."
        )]
        public function testResetRuntimeStatePreservesRegistryByDefault () : void {
            Autoloader::register("keep", fn ($c) => null);

            Autoloader::resetRuntimeState(false);

            $this->assertNotNull(Autoloader::get("keep"), "Registry should be preserved when clearRegistry is false.");
        }

        #[Group("RuntimeState")]
        #[Define(
            name: "resetRuntimeState(true) — Clears Registry",
            description: "When clearRegistry = true, all registered autoloaders are removed."
        )]
        public function testResetRuntimeStateWithClearRegistryEmptiesRegistry () : void {
            Autoloader::register("gone", fn ($c) => null);

            Autoloader::resetRuntimeState(true);

            $this->assertEmpty(Autoloader::getRegistry(), "Registry should be empty after resetRuntimeState(true).");
        }

        // ── resetRuntimeState() — configuration reset ─────────────────────────

        #[Group("RuntimeState")]
        #[Define(
            name: "resetRuntimeState(false, true) — Resets Auto-Bundling Config to Defaults",
            description: "With resetAutoBundlingConfiguration = true, all 13 auto-bundling settings return to their default values."
        )]
        public function testResetRuntimeStateResetsAutoBundlingConfig () : void {
            Autoloader::configureAutoBundling([
                "enabled" => true,
                "profile" => "prod",
                "bundleFile" => "/tmp/bundle.php",
                "stripWhitespace" => true,
                "excludePatterns" => ["vendor/"],
            ]);

            Autoloader::resetRuntimeState(false, true);

            $this->assertFalse(Autoloader::isAutoBundlingEnabled(), "enabled should revert to false.");
            $this->assertEquals("default", $this->readStaticProperty("autoBundlingProfile"), "profile should revert to 'default'.");
            $this->assertNull($this->readStaticProperty("autoBundleFile"), "bundleFile should revert to null.");
            $this->assertFalse($this->readStaticProperty("autoBundlingStripWhitespace"), "stripWhitespace should revert to false.");
            $this->assertEmpty($this->readStaticProperty("autoBundlingExcludePatterns"), "excludePatterns should revert to [].");
        }

        #[Group("RuntimeState")]
        #[Define(
            name: "resetRuntimeState(false, false) — Preserves Auto-Bundling Config",
            description: "With resetAutoBundlingConfiguration = false (default), auto-bundling settings are not touched."
        )]
        public function testResetRuntimeStatePreservesAutoBundlingConfigByDefault () : void {
            Autoloader::configureAutoBundling(["enabled" => true, "profile" => "custom"]);

            Autoloader::resetRuntimeState(false, false);

            $this->assertTrue(Autoloader::isAutoBundlingEnabled(), "Auto-bundling enabled setting should be preserved.");
            $this->assertEquals("custom", $this->readStaticProperty("autoBundlingProfile"), "Profile should be preserved.");
        }

        // ── Last bundling error ───────────────────────────────────────────────

        #[Group("RuntimeState")]
        #[Define(
            name: "getLastAutoBundlingError() — Null Initially",
            description: "getLastAutoBundlingError() returns null when no bundling error has occurred."
        )]
        public function testGetLastAutoBundlingErrorNullInitially () : void {
            $this->assertNull(Autoloader::getLastAutoBundlingError(), "getLastAutoBundlingError() should be null initially.");
        }

        #[Group("RuntimeState")]
        #[Define(
            name: "clearLastAutoBundlingError() — Clears Stored Error",
            description: "clearLastAutoBundlingError() resets the last error to null."
        )]
        public function testClearLastAutoBundlingErrorClearsIt () : void {
            $this->writeStaticProperty("autoBundlingLastError", new RuntimeException("test error"));

            Autoloader::clearLastAutoBundlingError();

            $this->assertNull(Autoloader::getLastAutoBundlingError(), "clearLastAutoBundlingError() must set it to null.");
        }

        #[Group("RuntimeState")]
        #[Define(
            name: "resetRuntimeState() — Clears Last Bundling Error",
            description: "resetRuntimeState() implicitly calls clearLastAutoBundlingError()."
        )]
        public function testResetRuntimeStateClearsLastBundlingError () : void {
            $this->writeStaticProperty("autoBundlingLastError", new RuntimeException("stale"));

            Autoloader::resetRuntimeState();

            $this->assertNull(Autoloader::getLastAutoBundlingError(), "resetRuntimeState() must clear the last bundling error.");
        }
    }
?>