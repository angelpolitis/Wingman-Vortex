<?php
    /**
     * Project Name:    Wingman Vortex - Autoloader Tests
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
    use Wingman\Vortex\Exceptions\InvalidExtensionTypeException;

    /**
     * Tests for basic Autoloader instance construction, getters, enable/disable,
     * priority management, extension handling, and the dump/log API.
     */
    class AutoloaderTest extends AutoloaderTestCase {
        // ── Construction ──────────────────────────────────────────────────────

        #[Group("Autoloader")]
        #[Define(
            name: "from() — Returns Autoloader Instance",
            description: "from() returns an Autoloader object."
        )]
        public function testFromCreatesInstance () : void {
            $al = Autoloader::from("test", fn ($c) => null);

            $this->assertInstanceOf(Autoloader::class, $al, "from() must return an Autoloader instance.");
        }

        #[Group("Autoloader")]
        #[Define(
            name: "from() — Priority Defaults to Zero",
            description: "When no priority is given, from() sets priority to 0."
        )]
        public function testFromDefaultsPriorityToZero () : void {
            $al = Autoloader::from("test", fn ($c) => null);

            $this->assertEquals(0, $al->getPriority(), "Default priority should be 0.");
        }

        #[Group("Autoloader")]
        #[Define(
            name: "from() — Priority Is Configurable",
            description: "A priority passed to from() is reflected by getPriority()."
        )]
        public function testFromSetsPriority () : void {
            $al = Autoloader::from("test", fn ($c) => null, 42);

            $this->assertEquals(42, $al->getPriority(), "Priority should match the value passed to from().");
        }

        // ── Priority ──────────────────────────────────────────────────────────

        #[Group("Autoloader")]
        #[Define(
            name: "setPriority() — Updates Priority",
            description: "setPriority() replaces the existing priority."
        )]
        public function testSetPriorityUpdatesValue () : void {
            $al = Autoloader::from("test", fn ($c) => null, 5);
            $al->setPriority(99);

            $this->assertEquals(99, $al->getPriority(), "getPriority() should return the newly set value.");
        }

        #[Group("Autoloader")]
        #[Define(
            name: "setPriority() — Returns Self",
            description: "setPriority() returns the same Autoloader instance for fluent chaining."
        )]
        public function testSetPriorityReturnsSelf () : void {
            $al = Autoloader::from("test", fn ($c) => null);

            $this->assertTrue($al->setPriority(5) === $al, "setPriority() must return the same instance for chaining.");
        }

        // ── Enable / Disable ──────────────────────────────────────────────────

        #[Group("Autoloader")]
        #[Define(
            name: "isEnabled() — Enabled by Default",
            description: "A freshly created autoloader is enabled."
        )]
        public function testIsEnabledByDefault () : void {
            $al = Autoloader::from("test", fn ($c) => null);

            $this->assertTrue($al->isEnabled(), "A new autoloader should be enabled by default.");
        }

        #[Group("Autoloader")]
        #[Define(
            name: "disable() — Disables the Autoloader",
            description: "After disable(), isEnabled() returns false."
        )]
        public function testDisableDisables () : void {
            $al = Autoloader::from("test", fn ($c) => null);
            $al->disable();

            $this->assertFalse($al->isEnabled(), "After disable(), isEnabled() should return false.");
        }

        #[Group("Autoloader")]
        #[Define(
            name: "enable() — Re-Enables After Disable",
            description: "After disable() then enable(), isEnabled() returns true."
        )]
        public function testEnableReEnables () : void {
            $al = Autoloader::from("test", fn ($c) => null);
            $al->disable();
            $al->enable();

            $this->assertTrue($al->isEnabled(), "After enable(), isEnabled() should return true.");
        }

        #[Group("Autoloader")]
        #[Define(
            name: "disable() — Returns Self",
            description: "disable() returns the same Autoloader instance for fluent chaining."
        )]
        public function testDisableReturnsSelf () : void {
            $al = Autoloader::from("test", fn ($c) => null);

            $this->assertTrue($al->disable() === $al, "disable() must return the same instance.");
        }

        #[Group("Autoloader")]
        #[Define(
            name: "enable() — Returns Self",
            description: "enable() returns the same Autoloader instance for fluent chaining."
        )]
        public function testEnableReturnsSelf () : void {
            $al = Autoloader::from("test", fn ($c) => null);

            $this->assertTrue($al->enable() === $al, "enable() must return the same instance.");
        }

        // ── Extensions ────────────────────────────────────────────────────────

        #[Group("Autoloader")]
        #[Define(
            name: "getExtensions() — Returns Default Extensions",
            description: "By default an autoloader supports php and inc extensions."
        )]
        public function testGetExtensionsReturnsDefaults () : void {
            $al = Autoloader::from("test", fn ($c) => null);

            $this->assertEquals(["php", "inc"], $al->getExtensions(), "Default extensions should be [php, inc].");
        }

        #[Group("Autoloader")]
        #[Define(
            name: "setExtensions() — Replaces Extensions",
            description: "setExtensions() fully replaces the extension list."
        )]
        public function testSetExtensionsUpdates () : void {
            $al = Autoloader::from("test", fn ($c) => null);
            $al->setExtensions(["php", "module"]);

            $this->assertEquals(["php", "module"], $al->getExtensions(), "Extensions should be updated.");
        }

        #[Group("Autoloader")]
        #[Define(
            name: "setExtensions() — Returns Self",
            description: "setExtensions() returns the same Autoloader instance for fluent chaining."
        )]
        public function testSetExtensionsReturnsSelf () : void {
            $al = Autoloader::from("test", fn ($c) => null);

            $this->assertTrue($al->setExtensions(["php"]) === $al, "setExtensions() must return the same instance.");
        }

        #[Group("Autoloader")]
        #[Define(
            name: "setExtensions() — Non-String Element Throws",
            description: "setExtensions() throws InvalidExtensionTypeException when an element is not a string."
        )]
        public function testSetExtensionsWithNonStringThrows () : void {
            $al = Autoloader::from("test", fn ($c) => null);

            $this->assertThrows(
                InvalidExtensionTypeException::class,
                fn () => $al->setExtensions(["php", 42]),
                "A non-string extension should throw InvalidExtensionTypeException."
            );
        }

        // ── Log & Status ──────────────────────────────────────────────────────

        #[Group("Autoloader")]
        #[Define(
            name: "getLog() — Empty Before Any Run",
            description: "A freshly created autoloader has an empty log."
        )]
        public function testGetLogReturnsEmptyInitially () : void {
            $al = Autoloader::from("test", fn ($c) => null);

            $this->assertEmpty($al->getLog(), "Log should be empty before any run.");
        }

        #[Group("Autoloader")]
        #[Define(
            name: "wasLastAttemptSuccessful() — False Before Any Run",
            description: "wasLastAttemptSuccessful() returns false before any run has occurred."
        )]
        public function testWasLastAttemptSuccessfulReturnsFalseWhenLogEmpty () : void {
            $al = Autoloader::from("test", fn ($c) => null);

            $this->assertFalse($al->wasLastAttemptSuccessful(), "Should be false when no run has occurred.");
        }

        #[Group("Autoloader")]
        #[Define(
            name: "wasLastAttemptErred() — False Before Any Run",
            description: "wasLastAttemptErred() returns false before any run has occurred."
        )]
        public function testWasLastAttemptErredReturnsFalseWhenLogEmpty () : void {
            $al = Autoloader::from("test", fn ($c) => null);

            $this->assertFalse($al->wasLastAttemptErred(), "Should be false when no run has occurred.");
        }

        #[Group("Autoloader")]
        #[Define(
            name: "getLastError() — Null Before Any Run",
            description: "getLastError() returns null before any run has occurred."
        )]
        public function testGetLastErrorReturnsNullWhenLogEmpty () : void {
            $al = Autoloader::from("test", fn ($c) => null);

            $this->assertNull($al->getLastError(), "getLastError() should be null when no run has occurred.");
        }

        #[Group("Autoloader")]
        #[Define(
            name: "getLastFoundClass() — Null Before Any Run",
            description: "getLastFoundClass() returns null before any run has occurred."
        )]
        public function testGetLastFoundClassReturnsNullWhenLogEmpty () : void {
            $al = Autoloader::from("test", fn ($c) => null);

            $this->assertNull($al->getLastFoundClass(), "getLastFoundClass() should be null when no run has occurred.");
        }

        // ── dump() ────────────────────────────────────────────────────────────

        #[Group("Autoloader")]
        #[Define(
            name: "dump() — Returns All Expected Keys",
            description: "dump() returns an array containing name, priority, enabled, creationDate, log, error, classFound, lastClass and lastError."
        )]
        public function testDumpReturnsExpectedStructure () : void {
            $al = Autoloader::from("test", fn ($c) => null);
            $data = $al->dump();

            foreach (["name", "priority", "enabled", "creationDate", "log", "error", "classFound", "lastClass", "lastError"] as $key) {
                $this->assertArrayHasKey($key, $data, "dump() must include the '$key' key.");
            }
        }

        #[Group("Autoloader")]
        #[Define(
            name: "dump() — Enabled Flag Reflects Real State",
            description: "dump()['enabled'] reflects isEnabled() at the time of the call."
        )]
        public function testDumpEnabledReflectsState () : void {
            $al = Autoloader::from("test", fn ($c) => null);
            $al->disable();

            $this->assertFalse($al->dump()["enabled"], "dump()[enabled] should be false after disable().");
        }
    }
?>