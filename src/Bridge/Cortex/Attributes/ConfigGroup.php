<?php
    /**
     * Project Name:    Wingman Vortex - Cortex Bridge - Configuration Group
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2026-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Autoloader.Bridge.Cortex.Attributes namespace.
    namespace Wingman\Vortex\Bridge\Cortex\Attributes;

    # Guard against double-inclusion (e.g. via symlinked paths resolving to different strings
    # under require_once). If the alias or stub is already in place there is nothing to do.
    if (class_exists(__NAMESPACE__ . '\\ConfigGroup', false)) return;

    # Import the following classes to the current scope.
    use Attribute;

    # If Cortex isn't available, define a dummy attribute to avoid errors.
    if (!class_exists(\Wingman\Cortex\Attributes\ConfigGroup::class)) {
        #[Attribute(Attribute::TARGET_CLASS)]
        class ConfigGroup {
            /**
             * The configuration group key.
             * @var string
             */
            private string $key;

            /**
             * Creates a new configuration group attribute.
             * @param string $key The group key.
             */
            public function __construct (string $key, ...$args) {
                $this->key = $key;
            }

            /**
             * Gets the group key.
             * @return string The group key.
             */
            public function getKey () : string {
                return $this->key;
            }
        }
    }
    else {
        class_alias(\Wingman\Cortex\Attributes\ConfigGroup::class, __NAMESPACE__ . '\\ConfigGroup');
    }
?>