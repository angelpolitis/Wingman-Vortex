<?php
    /**
     * Project Name:    Wingman Vortex - Test Runner
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2026-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    use Wingman\Argus\Tester;

    /**
     * Bootstrap order is intentional:
     *
     * 1. Argus is loaded first — because this is a standalone context and
     *    Wingman\Vortex\Autoloader has not yet been defined, Argus's own
     *    autoload.php registers its PSR-4 handler without returning early.
     *
     * 2. The Autoloader module's autoload.php is loaded second — it registers
     *    a PSR-4 handler for Wingman\Vortex\*. At this point Argus is already
     *    discoverable, so it does not need to be re-bootstrapped.
     */
    require_once __DIR__ . "/../../Argus/autoload.php";
    require_once __DIR__ . "/../autoload.php";
    require_once __DIR__ . "/AutoloaderTestCase.php";

    if (!class_exists(Tester::class)) {
        http_response_code(500);
        echo "Argus test framework not found. Install wingman/argus alongside wingman/vortex.";
        exit(1);
    }

    Tester::runTestsInDirectory(__DIR__, "Wingman\\Vortex\\Tests");
?>