<?php
    /**
     * Project Name:    Wingman Vortex - Exception Hierarchy Tests
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
    use InvalidArgumentException;
    use LogicException;
    use RuntimeException;
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Vortex\Exceptions\AutoBundlingBuildFailureException;
    use Wingman\Vortex\Exceptions\DuplicateAutoloaderNameException;
    use Wingman\Vortex\Exceptions\InvalidExtensionTypeException;
    use Wingman\Vortex\Exceptions\LackOfEligibleFilesException;
    use Wingman\Vortex\Exceptions\OutputDirectoryCreationException;
    use Wingman\Vortex\Exceptions\OutputFileMoveException;
    use Wingman\Vortex\Exceptions\OutputLockAcquisitionException;
    use Wingman\Vortex\Exceptions\SourceFileReadException;
    use Wingman\Vortex\Exceptions\TemporaryOutputFileWriteException;
    use Wingman\Vortex\Exceptions\UndefinedInstanceMethodException;
    use Wingman\Vortex\Exceptions\UndefinedStaticMethodException;
    use Wingman\Vortex\Interfaces\VortexException;

    /**
     * Tests for the exception hierarchy: every concrete exception must implement
     * the VortexException marker interface and extend the correct SPL base class.
     * Message construction and cause chaining are also verified.
     */
    class ExceptionsTest extends Test {
        // ── Marker interface — VortexException ────────────────────────────

        #[Group("Exceptions")]
        #[Define(
            name: "UndefinedInstanceMethodException — Implements Marker",
            description: "UndefinedInstanceMethodException implements VortexException."
        )]
        public function testUndefinedInstanceMethodExceptionImplementsMarker () : void {
            $e = new UndefinedInstanceMethodException("msg");
            $this->assertImplements(VortexException::class, $e, "UndefinedInstanceMethodException must implement VortexException.");
        }

        #[Group("Exceptions")]
        #[Define(
            name: "UndefinedStaticMethodException — Implements Marker",
            description: "UndefinedStaticMethodException implements VortexException."
        )]
        public function testUndefinedStaticMethodExceptionImplementsMarker () : void {
            $e = new UndefinedStaticMethodException("msg");
            $this->assertImplements(VortexException::class, $e, "UndefinedStaticMethodException must implement VortexException.");
        }

        #[Group("Exceptions")]
        #[Define(
            name: "DuplicateAutoloaderNameException — Implements Marker",
            description: "DuplicateAutoloaderNameException implements VortexException."
        )]
        public function testDuplicateAutoloaderNameExceptionImplementsMarker () : void {
            $e = new DuplicateAutoloaderNameException("msg");
            $this->assertImplements(VortexException::class, $e, "DuplicateAutoloaderNameException must implement VortexException.");
        }

        #[Group("Exceptions")]
        #[Define(
            name: "InvalidExtensionTypeException — Implements Marker",
            description: "InvalidExtensionTypeException implements VortexException."
        )]
        public function testInvalidExtensionTypeExceptionImplementsMarker () : void {
            $e = new InvalidExtensionTypeException("msg");
            $this->assertImplements(VortexException::class, $e, "InvalidExtensionTypeException must implement VortexException.");
        }

        #[Group("Exceptions")]
        #[Define(
            name: "LackOfEligibleFilesException — Implements Marker",
            description: "LackOfEligibleFilesException implements VortexException."
        )]
        public function testLackOfEligibleFilesExceptionImplementsMarker () : void {
            $e = new LackOfEligibleFilesException("msg");
            $this->assertImplements(VortexException::class, $e, "LackOfEligibleFilesException must implement VortexException.");
        }

        #[Group("Exceptions")]
        #[Define(
            name: "SourceFileReadException — Implements Marker",
            description: "SourceFileReadException implements VortexException."
        )]
        public function testSourceFileReadExceptionImplementsMarker () : void {
            $e = new SourceFileReadException("msg");
            $this->assertImplements(VortexException::class, $e, "SourceFileReadException must implement VortexException.");
        }

        #[Group("Exceptions")]
        #[Define(
            name: "OutputDirectoryCreationException — Implements Marker",
            description: "OutputDirectoryCreationException implements VortexException."
        )]
        public function testOutputDirectoryCreationExceptionImplementsMarker () : void {
            $e = new OutputDirectoryCreationException("msg");
            $this->assertImplements(VortexException::class, $e, "OutputDirectoryCreationException must implement VortexException.");
        }

        #[Group("Exceptions")]
        #[Define(
            name: "TemporaryOutputFileWriteException — Implements Marker",
            description: "TemporaryOutputFileWriteException implements VortexException."
        )]
        public function testTemporaryOutputFileWriteExceptionImplementsMarker () : void {
            $e = new TemporaryOutputFileWriteException("msg");
            $this->assertImplements(VortexException::class, $e, "TemporaryOutputFileWriteException must implement VortexException.");
        }

        #[Group("Exceptions")]
        #[Define(
            name: "OutputFileMoveException — Implements Marker",
            description: "OutputFileMoveException implements VortexException."
        )]
        public function testOutputFileMoveExceptionImplementsMarker () : void {
            $e = new OutputFileMoveException("msg");
            $this->assertImplements(VortexException::class, $e, "OutputFileMoveException must implement VortexException.");
        }

        #[Group("Exceptions")]
        #[Define(
            name: "AutoBundlingBuildFailureException — Implements Marker",
            description: "AutoBundlingBuildFailureException implements VortexException."
        )]
        public function testAutoBundlingBuildFailureExceptionImplementsMarker () : void {
            $e = new AutoBundlingBuildFailureException("msg");
            $this->assertImplements(VortexException::class, $e, "AutoBundlingBuildFailureException must implement VortexException.");
        }

        #[Group("Exceptions")]
        #[Define(
            name: "OutputLockAcquisitionException — Implements Marker",
            description: "OutputLockAcquisitionException implements VortexException."
        )]
        public function testOutputLockAcquisitionExceptionImplementsMarker () : void {
            $e = new OutputLockAcquisitionException("msg");
            $this->assertImplements(VortexException::class, $e, "OutputLockAcquisitionException must implement VortexException.");
        }

        // ── SPL base classes ──────────────────────────────────────────────────

        #[Group("Exceptions")]
        #[Define(
            name: "Logic Exceptions — Extend LogicException",
            description: "UndefinedInstanceMethodException, UndefinedStaticMethodException and DuplicateAutoloaderNameException all extend LogicException."
        )]
        public function testLogicExceptionsExtendLogicException () : void {
            $this->assertInstanceOf(LogicException::class, new UndefinedInstanceMethodException("x"), "UndefinedInstanceMethodException must extend LogicException.");
            $this->assertInstanceOf(LogicException::class, new UndefinedStaticMethodException("x"), "UndefinedStaticMethodException must extend LogicException.");
            $this->assertInstanceOf(LogicException::class, new DuplicateAutoloaderNameException("x"), "DuplicateAutoloaderNameException must extend LogicException.");
        }

        #[Group("Exceptions")]
        #[Define(
            name: "InvalidExtensionTypeException — Extends InvalidArgumentException",
            description: "InvalidExtensionTypeException extends InvalidArgumentException."
        )]
        public function testInvalidExtensionTypeExceptionExtendsInvalidArgumentException () : void {
            $this->assertInstanceOf(InvalidArgumentException::class, new InvalidExtensionTypeException("x"), "InvalidExtensionTypeException must extend InvalidArgumentException.");
        }

        #[Group("Exceptions")]
        #[Define(
            name: "Runtime Exceptions — Extend RuntimeException",
            description: "All output and bundling exceptions extend RuntimeException."
        )]
        public function testRuntimeExceptionsExtendRuntimeException () : void {
            $candidates = [
                new LackOfEligibleFilesException("x"),
                new SourceFileReadException("x"),
                new OutputDirectoryCreationException("x"),
                new TemporaryOutputFileWriteException("x"),
                new OutputFileMoveException("x"),
                new AutoBundlingBuildFailureException("x"),
                new OutputLockAcquisitionException("x"),
            ];

            foreach ($candidates as $e) {
                $this->assertInstanceOf(RuntimeException::class, $e, get_class($e) . " must extend RuntimeException.");
            }
        }

        // ── Message and cause ─────────────────────────────────────────────────

        #[Group("Exceptions")]
        #[Define(
            name: "Exception — Message Is Preserved",
            description: "The message passed to the constructor is returned by getMessage()."
        )]
        public function testExceptionMessageIsPreserved () : void {
            $msg = "Something went wrong with the autoloader.";
            $e = new LackOfEligibleFilesException($msg);

            $this->assertEquals($msg, $e->getMessage(), "getMessage() must return the original construction message.");
        }

        #[Group("Exceptions")]
        #[Define(
            name: "Exception — Previous Cause Is Preserved",
            description: "When a previous Throwable is supplied, getPrevious() returns it."
        )]
        public function testExceptionPreviousIsPreserved () : void {
            $root = new RuntimeException("root cause");
            $e = new AutoBundlingBuildFailureException("bundling failed", 0, $root);

            $this->assertTrue($e->getPrevious() === $root, "getPrevious() must return the cause passed to the constructor.");
        }
    }
?>