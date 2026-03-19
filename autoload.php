<?php
    /**
     * Project Name:    Wingman Vortex - Autoloader
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2026-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    /**
     * PSR-4 standalone bootstrap for the Wingman Vortex package itself.
     *
     * This file is a special case: the Autoloader module is the foundation package
     * that all other Wingman modules delegate to, so it cannot use itself to load
     * its own classes. Instead, it registers a direct PSR-4 spl_autoload_register
     * that maps Wingman\Vortex\* → src/{...}.php.
     *
     * Usage in test runners and one-off scripts:
     *   require_once __DIR__ . '/autoload.php';
     *
     * When the full Wingman Vortex is already active (i.e. this file is
     * included after a Wingman application bootstrap), the handler registered here
     * is a harmless no-op — PHP will use whichever handler resolves a class first.
     */
    spl_autoload_register(function (string $class) : void {
        if (!str_starts_with($class, "Wingman\\Vortex\\")) return;

        $relative = substr($class, strlen("Wingman\\Vortex\\"));
        $path = __DIR__ . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR
            . str_replace("\\", DIRECTORY_SEPARATOR, $relative) . ".php";

        if (file_exists($path)) require_once $path;
    });
?>