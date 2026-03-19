<?php
    /**
     * Project Name:    Wingman Vortex - Registration Tests
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
    use Wingman\Vortex\Exceptions\DuplicateAutoloaderNameException;

    /**
     * Tests for static registration — Autoloader::register(), Autoloader::get(),
     * Autoloader::getRegistry(), and duplicate-name detection.
     */
    class AutoloaderRegistrationTest extends AutoloaderTestCase {
        // ── Static register ───────────────────────────────────────────────────

        #[Group("Registration")]
        #[Define(
            name: "register() — Returns Autoloader Instance",
            description: "The static register() shorthand returns the registered Autoloader."
        )]
        public function testStaticRegisterReturnsInstance () : void {
            $al = Autoloader::register("app", fn ($c) => null);

            $this->assertInstanceOf(Autoloader::class, $al, "register() must return an Autoloader instance.");
        }

        #[Group("Registration")]
        #[Define(
            name: "register() — Adds to Registry",
            description: "After register(), the autoloader appears in getRegistry()."
        )]
        public function testStaticRegisterAddsToRegistry () : void {
            Autoloader::register("app", fn ($c) => null);
            $registry = Autoloader::getRegistry();

            $this->assertArrayHasKey("app", $registry, "Registry should contain the registered autoloader.");
        }

        #[Group("Registration")]
        #[Define(
            name: "register() — Instance Method Fluent Registration",
            description: "from() followed by register() on the instance also adds to the registry."
        )]
        public function testInstanceRegisterAddsToRegistry () : void {
            $al = Autoloader::from("fluent", fn ($c) => null);
            $al->register();

            $this->assertNotNull(Autoloader::get("fluent"), "Fluent-registered autoloader should be findable via get().");
        }

        // ── get() ─────────────────────────────────────────────────────────────

        #[Group("Registration")]
        #[Define(
            name: "get() — Returns Registered Autoloader by Name",
            description: "get() returns the same instance that was registered."
        )]
        public function testGetReturnsRegisteredAutoloader () : void {
            $al = Autoloader::register("app", fn ($c) => null);

            $this->assertTrue(Autoloader::get("app") === $al, "get() should return the exact registered instance.");
        }

        #[Group("Registration")]
        #[Define(
            name: "get() — Returns Null for Unknown Name",
            description: "get() returns null for a name that was never registered."
        )]
        public function testGetReturnsNullForUnknown () : void {
            $this->assertNull(Autoloader::get("nonexistent"), "get() should return null for an unknown name.");
        }

        // ── getRegistry() ─────────────────────────────────────────────────────

        #[Group("Registration")]
        #[Define(
            name: "getRegistry() — Empty On Fresh State",
            description: "getRegistry() returns an empty array when nothing is registered."
        )]
        public function testGetRegistryEmptyByDefault () : void {
            $this->assertEmpty(Autoloader::getRegistry(), "getRegistry() should be empty when nothing is registered.");
        }

        #[Group("Registration")]
        #[Define(
            name: "getRegistry() — Contains All Registered Autoloaders",
            description: "After registering multiple autoloaders, getRegistry() contains all of them."
        )]
        public function testGetRegistryContainsAllRegistered () : void {
            Autoloader::register("al1", fn ($c) => null);
            Autoloader::register("al2", fn ($c) => null);
            Autoloader::register("al3", fn ($c) => null);
            $registry = Autoloader::getRegistry();

            $this->assertCount(3, $registry, "getRegistry() should have exactly 3 entries.");
            $this->assertArrayHasKey("al1", $registry, "Registry missing al1.");
            $this->assertArrayHasKey("al2", $registry, "Registry missing al2.");
            $this->assertArrayHasKey("al3", $registry, "Registry missing al3.");
        }

        // ── Duplicate ─────────────────────────────────────────────────────────

        #[Group("Registration")]
        #[Define(
            name: "register() — Duplicate Name Throws",
            description: "Registering two autoloaders with the same name throws DuplicateAutoloaderNameException."
        )]
        public function testDuplicateNameThrows () : void {
            Autoloader::register("app", fn ($c) => null);

            $this->assertThrows(
                DuplicateAutoloaderNameException::class,
                fn () => Autoloader::register("app", fn ($c) => null),
                "A duplicate name should throw DuplicateAutoloaderNameException."
            );
        }

        #[Group("Registration")]
        #[Define(
            name: "register() — Duplicate Name Does Not Overwrite",
            description: "After a failed duplicate registration, the original autoloader is still in the registry."
        )]
        public function testDuplicateNameDoesNotOverwrite () : void {
            $original = Autoloader::register("app", fn ($c) => null);

            try {
                Autoloader::register("app", fn ($c) => null);
            }
            catch (DuplicateAutoloaderNameException) {}

            $this->assertTrue(Autoloader::get("app") === $original, "The original autoloader must survive after a failed duplicate registration.");
        }
    }
?>