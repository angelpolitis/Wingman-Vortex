<?php
    /**
     * Project Name:    Wingman Vortex - Exceptions - Invalid Extension Type Exception
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2026-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Autoloader.Exceptions namespace.
    namespace Wingman\Vortex\Exceptions;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Wingman\Vortex\Interfaces\VortexException;

    /**
     * Thrown when an autoloader extension value is not a string.
     * @package Wingman\Vortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class InvalidExtensionTypeException extends InvalidArgumentException implements VortexException {}
?>