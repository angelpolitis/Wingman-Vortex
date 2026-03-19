<?php
    /**
     * Project Name:    Wingman Vortex - Interfaces - Autoloader Exception
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2026-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Autoloader.Interfaces namespace.
    namespace Wingman\Vortex\Interfaces;

    # Import the following classes to the current scope.
    use Throwable;

    /**
     * The marker interface for all package-level exceptions.
     * @package Wingman\Vortex\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface VortexException extends Throwable {}
?>