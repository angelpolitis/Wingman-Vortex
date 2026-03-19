<?php
    /**
     * Project Name:    Wingman Vortex - Base Test Case
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
    use ReflectionClass;
    use Wingman\Argus\Test;
    use Wingman\Vortex\Autoloader;

    /**
     * Abstract base class for all Wingman Vortex test suites.
     *
     * Centralises Reflection helpers for accessing protected static Autoloader
     * properties, and provides a setUp/tearDown pair that snapshots the live
     * registry before each test and restores it afterwards, ensuring production
     * autoloaders registered by the application bootstrap are never lost after
     * test teardown.
     */
    abstract class AutoloaderTestCase extends Test {
        /**
         * The registry snapshot taken before each test, used to restore the live
         * production autoloaders after the test clears the registry.
         */
        private array $registrySnapshot = [];

        // ── Helpers ───────────────────────────────────────────────────────────

        /**
         * Returns the value of a protected static property on Autoloader via Reflection.
         * @param string $property The property name.
         * @return mixed The property value.
         */
        protected function readStaticProperty (string $property) : mixed {
            $prop = (new ReflectionClass(Autoloader::class))->getProperty($property);
            /**
             * @disregard
             * @psalm-suppress DeprecatedMethod
             * @noinspection PhpDeprecatedApiInspection
             */
            if (method_exists($prop, "setAccessible")) $prop->setAccessible(true);
            return $prop->getValue(null);
        }

        /**
         * Sets the value of a protected static property on Autoloader via Reflection.
         * @param string $property The property name.
         * @param mixed $value The value to set.
         * @return void
         */
        protected function writeStaticProperty (string $property, mixed $value) : void {
            $prop = (new ReflectionClass(Autoloader::class))->getProperty($property);
            /**
             * @disregard
             * @psalm-suppress DeprecatedMethod
             * @noinspection PhpDeprecatedApiInspection
             */
            if (method_exists($prop, "setAccessible")) $prop->setAccessible(true);
            $prop->setValue(null, $value);
        }

        // ── Lifecycle ─────────────────────────────────────────────────────────

        /**
         * Snapshots the live registry, then resets all static Autoloader state.
         */
        public function setUp () : void {
            $this->registrySnapshot = $this->readStaticProperty("registry");

            Autoloader::resetRuntimeState(true, true);
        }

        /**
         * Resets all static Autoloader state, then restores the live registry.
         */
        public function tearDown () : void {
            Autoloader::resetRuntimeState(true, true);

            $this->writeStaticProperty("registry", $this->registrySnapshot);
        }
    }
?>