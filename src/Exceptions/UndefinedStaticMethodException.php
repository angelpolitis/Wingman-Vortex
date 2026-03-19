<?php
    /**
     * Project Name:    Wingman Vortex - Exceptions - Undefined Static Method Exception
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
    use LogicException;
    use Wingman\Vortex\Interfaces\VortexException;

    /**
     * Thrown when a dynamic static method call targets an undefined method.
     * @package Wingman\Vortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class UndefinedStaticMethodException extends LogicException implements VortexException {}
?>