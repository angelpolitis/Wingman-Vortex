<?php
    /**
     * Project Name:    Wingman Vortex - Runtime Guard Tests
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
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Vortex\Autoloader;
    use Wingman\Vortex\RuntimeGuard;

    /**
     * Tests for RuntimeGuard::afterWorkUnit() and RuntimeGuard::afterWorkUnitFully(),
     * verifying that they correctly delegate to Autoloader::resetRuntimeState() and
     * Autoloader::clearCache().
     */
    class RuntimeGuardTest extends AutoloaderTestCase {
        // ── afterWorkUnit() ───────────────────────────────────────────────────

        #[Group("RuntimeGuard")]
        #[Define(
            name: "afterWorkUnit() — Does Not Throw",
            description: "afterWorkUnit() completes without throwing an exception."
        )]
        public function testAfterWorkUnitDoesNotThrow () : void {
            $this->assertNotThrows(\Throwable::class, fn () => RuntimeGuard::afterWorkUnit(), "afterWorkUnit() must not throw.");
        }

        #[Group("RuntimeGuard")]
        #[Define(
            name: "afterWorkUnit() — Clears In-Memory Caches",
            description: "afterWorkUnit() clears dirCache and resolvedCache."
        )]
        public function testAfterWorkUnitClearsRuntimeCaches () : void {
            $this->writeStaticProperty("dirCache", ["some/dir" => ["a.php"]]);
            $this->writeStaticProperty("resolvedCache", ["Foo\\Bar" => "/foo/bar.php"]);

            RuntimeGuard::afterWorkUnit();

            $this->assertEmpty($this->readStaticProperty("dirCache"), "dirCache should be empty after afterWorkUnit().");
            $this->assertEmpty($this->readStaticProperty("resolvedCache"), "resolvedCache should be empty after afterWorkUnit().");
        }

        #[Group("RuntimeGuard")]
        #[Define(
            name: "afterWorkUnit() — Resets Bootstrapping Flags",
            description: "afterWorkUnit() resets autoBundlingBootstrapped and autoBundlingGenerated."
        )]
        public function testAfterWorkUnitResetsBootstrapFlags () : void {
            $this->writeStaticProperty("autoBundlingBootstrapped", true);
            $this->writeStaticProperty("autoBundlingGenerated", true);

            RuntimeGuard::afterWorkUnit();

            $this->assertFalse($this->readStaticProperty("autoBundlingBootstrapped"), "autoBundlingBootstrapped should be reset by afterWorkUnit().");
            $this->assertFalse($this->readStaticProperty("autoBundlingGenerated"), "autoBundlingGenerated should be reset by afterWorkUnit().");
        }

        #[Group("RuntimeGuard")]
        #[Define(
            name: "afterWorkUnit() — Clears Autoloader Logs",
            description: "afterWorkUnit() clears the log of every registered autoloader."
        )]
        public function testAfterWorkUnitClearsAutoloaderLogs () : void {
            $al = Autoloader::register("test", fn ($c) => null);
            $al->run("Some\\Class");

            $this->assertNotEmpty($al->getLog(), "Log should have an entry before afterWorkUnit().");

            RuntimeGuard::afterWorkUnit();

            $this->assertEmpty($al->getLog(), "Log should be empty after afterWorkUnit().");
        }

        #[Group("RuntimeGuard")]
        #[Define(
            name: "afterWorkUnit(true) — Clears Registry",
            description: "Passing clearRegistry = true removes all registered autoloaders."
        )]
        public function testAfterWorkUnitWithClearRegistryEmptiesRegistry () : void {
            Autoloader::register("al1", fn ($c) => null);
            Autoloader::register("al2", fn ($c) => null);

            RuntimeGuard::afterWorkUnit(true);

            $this->assertEmpty(Autoloader::getRegistry(), "Registry should be empty after afterWorkUnit(true).");
        }

        #[Group("RuntimeGuard")]
        #[Define(
            name: "afterWorkUnit(false) — Preserves Registry",
            description: "When clearRegistry = false (default), the registry is kept."
        )]
        public function testAfterWorkUnitPreservesRegistryByDefault () : void {
            Autoloader::register("keep", fn ($c) => null);

            RuntimeGuard::afterWorkUnit(false);

            $this->assertNotNull(Autoloader::get("keep"), "Registry should be preserved after afterWorkUnit(false).");
        }

        #[Group("RuntimeGuard")]
        #[Define(
            name: "afterWorkUnit(false, true) — Resets Auto-Bundling Config",
            description: "When resetAutoBundlingConfiguration = true, the bundling settings are reset to defaults."
        )]
        public function testAfterWorkUnitResetsAutoBundlingConfig () : void {
            Autoloader::configureAutoBundling(["enabled" => true, "profile" => "staging"]);

            RuntimeGuard::afterWorkUnit(false, true);

            $this->assertFalse(Autoloader::isAutoBundlingEnabled(), "Auto-bundling should be disabled after config reset.");
            $this->assertEquals("default", $this->readStaticProperty("autoBundlingProfile"), "Profile should revert to 'default'.");
        }

        // ── afterWorkUnitFully() ──────────────────────────────────────────────

        #[Group("RuntimeGuard")]
        #[Define(
            name: "afterWorkUnitFully() — Does Not Throw",
            description: "afterWorkUnitFully() completes without throwing an exception."
        )]
        public function testAfterWorkUnitFullyDoesNotThrow () : void {
            $this->assertNotThrows(\Throwable::class, fn () => RuntimeGuard::afterWorkUnitFully(), "afterWorkUnitFully() must not throw.");
        }

        #[Group("RuntimeGuard")]
        #[Define(
            name: "afterWorkUnitFully() — Also Clears Caches",
            description: "afterWorkUnitFully() performs the same in-memory cache reset as afterWorkUnit()."
        )]
        public function testAfterWorkUnitFullyClearsRuntimeCaches () : void {
            $this->writeStaticProperty("dirCache", ["x/y" => ["z.php"]]);

            RuntimeGuard::afterWorkUnitFully();

            $this->assertEmpty($this->readStaticProperty("dirCache"), "dirCache should be empty after afterWorkUnitFully().");
        }

        #[Group("RuntimeGuard")]
        #[Define(
            name: "afterWorkUnitFully(true) — Clears Registry",
            description: "Passing clearRegistry = true to afterWorkUnitFully() removes all registered autoloaders."
        )]
        public function testAfterWorkUnitFullyWithClearRegistryEmptiesRegistry () : void {
            Autoloader::register("gone", fn ($c) => null);

            RuntimeGuard::afterWorkUnitFully(true);

            $this->assertEmpty(Autoloader::getRegistry(), "Registry should be empty after afterWorkUnitFully(true).");
        }
    }
?>