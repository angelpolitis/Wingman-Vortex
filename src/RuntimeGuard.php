<?php
    /**
     * Project Name:    Wingman Vortex - Runtime Guard
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2026-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Autoloader namespace.
    namespace Wingman\Vortex;

    /**
     * A helper for resetting Autoloader runtime state in long-running processes.
     *
     * This class is intended for worker loops, queue consumers, and daemons where
     * the PHP process does not terminate between jobs.
     * @package Wingman\Vortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class RuntimeGuard {
        /**
         * Creates a new runtime guard.
         */
        public function __construct () {}

        /**
         * Resets in-memory runtime state after a unit of work.
         *
         * This clears directory/path caches and per-request bootstrapping flags.
         * Optionally clears the autoloader registry as well.
         * @param bool $clearRegistry Whether to clear all registered autoloaders.
         * @param bool $resetAutoBundlingConfiguration Whether automatic bundling options should be reset to defaults.
         * @return void
         */
        public static function afterWorkUnit (bool $clearRegistry = false, bool $resetAutoBundlingConfiguration = false) : void {
            Autoloader::resetRuntimeState($clearRegistry, $resetAutoBundlingConfiguration);
        }

        /**
         * Performs a full process reset after a unit of work.
         *
         * This includes `afterWorkUnit()` and APCu cache cleanup.
         * @param bool $clearRegistry Whether to clear all registered autoloaders.
         * @param bool $resetAutoBundlingConfiguration Whether automatic bundling options should be reset to defaults.
         * @return void
         */
        public static function afterWorkUnitFully (bool $clearRegistry = false, bool $resetAutoBundlingConfiguration = false) : void {
            static::afterWorkUnit($clearRegistry, $resetAutoBundlingConfiguration);
            Autoloader::clearCache();
        }
    }
?>