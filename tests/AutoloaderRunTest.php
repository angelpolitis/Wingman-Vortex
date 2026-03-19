<?php
    /**
     * Project Name:    Wingman Vortex - Run Tests
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
    use RuntimeException;
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Vortex\Autoloader;

    /**
     * Tests for Autoloader::run(), covering the full resolution lifecycle:
     * null paths, missing files, successful class loading, exception capture,
     * array path-finder results, log entries, and resolved-path tracking.
     */
    class AutoloaderRunTest extends AutoloaderTestCase {
        /**
         * A temporary directory used to create fixture PHP files.
         * @var string
         */
        private string $tempDir;

        /**
         * The path of the fixture PHP file created for each test.
         * @var string|null
         */
        private ?string $tempFile = null;

        /**
         * Delegates to the base lifecycle, then creates a unique temporary directory for fixture files.
         */
        public function setUp () : void {
            parent::setUp();

            $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "wm_al_run_" . uniqid();
            mkdir($this->tempDir, 0755, true);
            $this->tempFile = null;
        }

        /**
         * Cleans up temporary fixture files, then delegates to the base lifecycle.
         */
        public function tearDown () : void {
            if ($this->tempFile !== null && file_exists($this->tempFile)) {
                @unlink($this->tempFile);
            }

            if (is_dir($this->tempDir)) {
                @rmdir($this->tempDir);
            }

            parent::tearDown();
        }

        // ── Helpers ───────────────────────────────────────────────────────────

        /**
         * Creates a PHP fixture file that defines a class with the given name.
         * @param string $className The bare (unqualified) class name to define.
         * @return string The absolute path to the created file.
         */
        private function createFixtureClass (string $className) : string {
            $path = $this->tempDir . DIRECTORY_SEPARATOR . $className . ".php";
            file_put_contents($path, "<?php class $className {}");
            $this->tempFile = $path;
            return $path;
        }

        /**
         * Generates a unique class name that will not collide across test runs.
         * @return string A collision-safe class name.
         */
        private function generateClassName () : string {
            return "WingmanAlRunCls_" . str_replace(".", "_", uniqid("", true));
        }

        // ── run() — Null Path ─────────────────────────────────────────────────

        #[Group("Run")]
        #[Define(
            name: "run() — Null Path Leaves classFound False",
            description: "When the pathFinder returns null, run() marks the attempt as unsuccessful."
        )]
        public function testRunWithNullPathResultIsUnsuccessful () : void {
            $al = Autoloader::from("test", fn ($c) => null);
            $al->run("Some\\Class");

            $this->assertFalse($al->wasLastAttemptSuccessful(), "A null path should result in an unsuccessful attempt.");
        }

        #[Group("Run")]
        #[Define(
            name: "run() — Null Path Appends Log Entry",
            description: "run() always appends an entry to the log, even when the path is null."
        )]
        public function testRunWithNullPathAppendsLogEntry () : void {
            $al = Autoloader::from("test", fn ($c) => null);
            $al->run("Some\\Class");

            $this->assertCount(1, $al->getLog(), "The log should have exactly one entry after a single run.");
        }

        // ── run() — Invalid Path ──────────────────────────────────────────────

        #[Group("Run")]
        #[Define(
            name: "run() — Non-Existent File Leaves classFound False",
            description: "When the pathFinder returns a path to a file that does not exist, the attempt fails."
        )]
        public function testRunWithNonExistentFileIsUnsuccessful () : void {
            $al = Autoloader::from("test", fn ($c) => "/tmp/this_file_does_not_exist_ever_" . uniqid() . ".php");
            $al->run("Some\\Class");

            $this->assertFalse($al->wasLastAttemptSuccessful(), "A path to a non-existent file should result in an unsuccessful attempt.");
        }

        // ── run() — Successful Class Load ─────────────────────────────────────

        #[Group("Run")]
        #[Define(
            name: "run() — Valid File Causes classFound True",
            description: "When the pathFinder returns a valid PHP file defining the target class, wasLastAttemptSuccessful() returns true."
        )]
        public function testRunWithValidFileSucceeds () : void {
            $className = $this->generateClassName();
            $path = $this->createFixtureClass($className);

            $al = Autoloader::from("test", fn ($c) => $path);
            $al->run($className);

            $this->assertTrue($al->wasLastAttemptSuccessful(), "A valid file defining the class should result in a successful attempt.");
        }

        #[Group("Run")]
        #[Define(
            name: "run() — Returns Self",
            description: "run() returns the same Autoloader instance for fluent chaining."
        )]
        public function testRunReturnsSelf () : void {
            $al = Autoloader::from("test", fn ($c) => null);

            $this->assertTrue($al->run("Any\\Class") === $al, "run() must return the same instance.");
        }

        #[Group("Run")]
        #[Define(
            name: "run() — getLastFoundClass Returns Class Name After Success",
            description: "After a successful run, getLastFoundClass() returns the resolved class name."
        )]
        public function testGetLastFoundClassAfterSuccessfulRun () : void {
            $className = $this->generateClassName();
            $path = $this->createFixtureClass($className);

            $al = Autoloader::from("test", fn ($c) => $path);
            $al->run($className);

            $this->assertEquals($className, $al->getLastFoundClass(), "getLastFoundClass() should return the loaded class name.");
        }

        #[Group("Run")]
        #[Define(
            name: "run() — getLastFoundClass Returns Null After Failure",
            description: "After an unsuccessful run, getLastFoundClass() returns null."
        )]
        public function testGetLastFoundClassNullAfterFailure () : void {
            $al = Autoloader::from("test", fn ($c) => null);
            $al->run("Does\\Not\\Exist");

            $this->assertNull($al->getLastFoundClass(), "getLastFoundClass() should be null after a failed attempt.");
        }

        // ── run() — Path Finder Exception ─────────────────────────────────────

        #[Group("Run")]
        #[Define(
            name: "run() — PathFinder Exception Is Captured in Log",
            description: "When the pathFinder throws, run() captures the exception in the log entry and does not rethrow."
        )]
        public function testRunPathFinderExceptionCapturedInLog () : void {
            $al = Autoloader::from("test", function ($c) {
                throw new RuntimeException("Path finder blew up.");
            });

            $al->run("Any\\Class");

            $this->assertTrue($al->wasLastAttemptErred(), "wasLastAttemptErred() should be true when pathFinder throws.");
            $this->assertNotNull($al->getLastError(), "getLastError() should contain the captured exception.");
            $this->assertFalse($al->wasLastAttemptSuccessful(), "A pathFinder exception should not count as a successful resolution.");
        }

        #[Group("Run")]
        #[Define(
            name: "run() — PathFinder Exception Does Not Propagate",
            description: "A pathFinder exception is swallowed by run() and does not throw to the caller."
        )]
        public function testRunDoesNotRethrowPathFinderException () : void {
            $al = Autoloader::from("test", function ($c) {
                throw new RuntimeException("Boom.");
            });

            $this->assertNotThrows(RuntimeException::class, fn () => $al->run("Any\\Class"), "run() must not rethrow pathFinder exceptions.");
        }

        // ── run() — Array path-finder result ──────────────────────────────────

        #[Group("Run")]
        #[Define(
            name: "run() — Array Result Remaps Class Name",
            description: "When the pathFinder returns an array with 'class' and 'path' keys, the class key overrides the looked-up name."
        )]
        public function testRunArrayResultRemapsClassName () : void {
            $className = $this->generateClassName();
            $path = $this->createFixtureClass($className);

            $al = Autoloader::from("test", fn ($c) => ["class" => $className, "path" => $path]);
            $al->run("Original\\Class\\Name");

            $this->assertTrue($al->wasLastAttemptSuccessful(), "Array result with remapped class name should succeed.");
            $this->assertEquals($className, $al->getLastFoundClass(), "getLastFoundClass() must return the remapped class name.");
        }

        // ── Resolved path tracking ────────────────────────────────────────────

        #[Group("Run")]
        #[Define(
            name: "getSuccessfullyResolvedPaths() — Returns Loaded File Paths",
            description: "getSuccessfullyResolvedPaths() returns the file path of every successfully loaded class."
        )]
        public function testGetSuccessfullyResolvedPathsReturnsPath () : void {
            $className = $this->generateClassName();
            $path = $this->createFixtureClass($className);

            $al = Autoloader::from("test", fn ($c) => $path);
            $al->run($className);
            $paths = $al->getSuccessfullyResolvedPaths();

            $this->assertCount(1, $paths, "Should have exactly one resolved path after one successful run.");
            $this->assertStringContains($className, $paths[0], "Resolved path should relate to the loaded class file.");
        }

        #[Group("Run")]
        #[Define(
            name: "getSuccessfullyResolvedPaths() — Failed Run Yields No Path",
            description: "A failed run does not add a path to getSuccessfullyResolvedPaths()."
        )]
        public function testGetSuccessfullyResolvedPathsExcludesFailures () : void {
            $al = Autoloader::from("test", fn ($c) => null);
            $al->run("Missing\\Class");

            $this->assertEmpty($al->getSuccessfullyResolvedPaths(), "Failed runs must not appear in resolved paths.");
        }

        #[Group("Run")]
        #[Define(
            name: "getLoadedFiles() — Aggregates Paths Across Autoloaders",
            description: "getLoadedFiles() returns the union of resolved paths across all registered autoloaders."
        )]
        public function testGetLoadedFilesAggregatesAcrossAutoloaders () : void {
            $cls1 = $this->generateClassName();
            $cls2 = $this->generateClassName();

            $path1 = $this->createFixtureClass($cls1);

            $path2 = $this->tempDir . DIRECTORY_SEPARATOR . $cls2 . ".php";
            file_put_contents($path2, "<?php class $cls2 {}");

            Autoloader::register("al1", fn ($c) => $c === $cls1 ? $path1 : null);
            Autoloader::register("al2", fn ($c) => $c === $cls2 ? $path2 : null);

            Autoloader::dequeueRegistryBasedOnPriority($cls1);
            Autoloader::dequeueRegistryBasedOnPriority($cls2);

            @unlink($path2);

            $loaded = Autoloader::getLoadedFiles();

            $this->assertCount(2, $loaded, "getLoadedFiles() should aggregate paths from all autoloaders.");
        }

        // ── Multiple log entries ───────────────────────────────────────────────

        #[Group("Run")]
        #[Define(
            name: "run() — Each Call Appends a New Log Entry",
            description: "Calling run() N times appends exactly N entries to the log."
        )]
        public function testRunAppendsMultipleLogEntries () : void {
            $al = Autoloader::from("test", fn ($c) => null);
            $al->run("A\\Class");
            $al->run("B\\Class");
            $al->run("C\\Class");

            $this->assertCount(3, $al->getLog(), "Three run() calls should produce three log entries.");
        }
    }
?>