<?php
    /**
     * Project Name:    Wingman Vortex - Case-Insensitive Resolution Tests
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
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Vortex\Autoloader;

    /**
     * Tests for Autoloader::resolveCI() — the case-insensitive path resolution utility
     * that maps fuzzy paths to their exact on-disk counterparts.
     */
    class ResolveCITest extends Test {
        /**
         * The temporary directory tree used by each test.
         * @var string
         */
        private string $tempDir;

        /**
         * An exact path to a file planted inside $tempDir.
         * @var string
         */
        private string $plantedFile;

        /**
         * Creates a fresh temporary directory with a known file and clears the
         * in-memory directory cache before each test.
         */
        public function setUp () : void {
            Autoloader::clearRuntimeCaches();

            $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "wm_al_ci_" . uniqid();

            $subDir = $this->tempDir . DIRECTORY_SEPARATOR . "SomeDir";
            mkdir($subDir, 0755, true);

            $this->plantedFile = $subDir . DIRECTORY_SEPARATOR . "MyFile.php";
            file_put_contents($this->plantedFile, "<?php // fixture");
        }

        /**
         * Removes the temporary directory tree and clears caches after each test.
         */
        public function tearDown () : void {
            Autoloader::clearRuntimeCaches();

            if (file_exists($this->plantedFile)) @unlink($this->plantedFile);

            $subDir = $this->tempDir . DIRECTORY_SEPARATOR . "SomeDir";
            if (is_dir($subDir)) @rmdir($subDir);
            if (is_dir($this->tempDir)) @rmdir($this->tempDir);
        }

        // ── Exact match ───────────────────────────────────────────────────────

        #[Group("ResolveCI")]
        #[Define(
            name: "resolveCI() — Exact Path Resolves",
            description: "resolveCI() returns the real path when the path casing is already correct."
        )]
        public function testExactPathResolves () : void {
            $result = Autoloader::resolveCI($this->plantedFile);

            $this->assertNotNull($result, "An exact-case path to a real file should be resolved.");
            $this->assertTrue(is_file($result), "resolveCI() should return a valid file path.");
            $this->assertStringEndsWith("SomeDir" . DIRECTORY_SEPARATOR . "MyFile.php", $result, "The resolved path should point to MyFile.php.");
        }

        // ── Case-insensitive match ─────────────────────────────────────────────

        #[Group("ResolveCI")]
        #[Define(
            name: "resolveCI() — Different Case Resolves",
            description: "resolveCI() finds the real file even when the path uses the wrong case."
        )]
        public function testDifferentCaseResolves () : void {
            $upperPath = strtoupper($this->plantedFile);
            $result = Autoloader::resolveCI($upperPath);

            $this->assertNotNull($result, "An uppercase version of a real path should still resolve.");
            $this->assertTrue(is_file($result), "A case-insensitive match should still produce a real file path.");
        }

        // ── Extension fallback ────────────────────────────────────────────────

        #[Group("ResolveCI")]
        #[Define(
            name: "resolveCI() — Extension Fallback Resolves File",
            description: "When the last segment has no extension, resolveCI() appends each supplied extension until a match is found."
        )]
        public function testExtensionFallbackResolves () : void {
            $pathWithoutExtension = $this->tempDir . DIRECTORY_SEPARATOR . "SomeDir" . DIRECTORY_SEPARATOR . "MyFile";

            $result = Autoloader::resolveCI($pathWithoutExtension, null, ["php", "inc"]);

            $this->assertNotNull($result, "resolveCI() should find the file when the extension is supplied as a fallback.");
            $this->assertStringEndsWith("SomeDir" . DIRECTORY_SEPARATOR . "MyFile.php", $result, "The resolved path should point to the .php file.");
        }

        #[Group("ResolveCI")]
        #[Define(
            name: "resolveCI() — Wrong Extension Falls Back to Correct One",
            description: "resolveCI() skips extensions that do not match and uses the one that does."
        )]
        public function testWrongExtensionFallsBackToCorrect () : void {
            $pathWithoutExtension = $this->tempDir . DIRECTORY_SEPARATOR . "SomeDir" . DIRECTORY_SEPARATOR . "MyFile";

            $result = Autoloader::resolveCI($pathWithoutExtension, null, ["ts", "inc", "php"]);

            $this->assertNotNull($result, "resolveCI() should eventually find the .php file even after trying wrong extensions.");
        }

        // ── Non-existent path ─────────────────────────────────────────────────

        #[Group("ResolveCI")]
        #[Define(
            name: "resolveCI() — Non-Existent Path Returns Null",
            description: "resolveCI() returns null when no matching file or directory exists."
        )]
        public function testNonExistentPathReturnsNull () : void {
            $result = Autoloader::resolveCI($this->tempDir . DIRECTORY_SEPARATOR . "DoesNotExist.php");

            $this->assertNull($result, "A path to a non-existent file should return null.");
        }

        #[Group("ResolveCI")]
        #[Define(
            name: "resolveCI() — Non-Existent Deep Path Returns Null",
            description: "resolveCI() returns null when an intermediate directory segment does not exist."
        )]
        public function testNonExistentIntermediateDirectoryReturnsNull () : void {
            $result = Autoloader::resolveCI($this->tempDir . DIRECTORY_SEPARATOR . "Ghost" . DIRECTORY_SEPARATOR . "File.php");

            $this->assertNull($result, "A path with a non-existent intermediate directory should return null.");
        }

        // ── Directory-relative path ───────────────────────────────────────────

        #[Group("ResolveCI")]
        #[Define(
            name: "resolveCI() — Directory-Relative Path Resolves",
            description: "When a base directory is supplied, resolveCI() resolves the relative remainder inside it."
        )]
        public function testDirectoryRelativePathResolves () : void {
            $relative = "SomeDir" . DIRECTORY_SEPARATOR . "MyFile.php";
            $result = Autoloader::resolveCI($relative, $this->tempDir);

            $this->assertNotNull($result, "A directory-relative path should resolve against the provided base directory.");
            $this->assertEquals($this->plantedFile, $result, "The resolved path must match the planted file.");
        }

        #[Group("ResolveCI")]
        #[Define(
            name: "resolveCI() — Directory-Relative Case-Insensitive",
            description: "resolveCI() resolves a case-incorrect relative path against a base directory."
        )]
        public function testDirectoryRelativeCaseInsensitive () : void {
            $relative = strtolower("SomeDir") . DIRECTORY_SEPARATOR . strtolower("MyFile.php");
            $result = Autoloader::resolveCI($relative, $this->tempDir);

            $this->assertNotNull($result, "Case-insensitive directory-relative resolution should succeed.");
        }

        // ── Absolute path ─────────────────────────────────────────────────────

        #[Group("ResolveCI")]
        #[Define(
            name: "resolveCI() — Absolute Path Resolves",
            description: "An absolute path with correct casing is resolved to itself."
        )]
        public function testAbsolutePathResolves () : void {
            $result = Autoloader::resolveCI($this->plantedFile);

            $this->assertNotNull($result, "An absolute path to a real file should resolve.");
        }
    }
?>