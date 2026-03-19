<?php
    /**
     * Project Name:    Wingman Vortex - Bundler Tests
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
    use Wingman\Vortex\Bundler;
    use Wingman\Vortex\Exceptions\LackOfEligibleFilesException;

    /**
     * Integration tests for Bundler using real temporary source files and artefact outputs.
     */
    class BundlerTest extends Test {
        /**
         * The temporary directory for fixture sources and generated artefacts.
         * @var string
         */
        private string $tempDirectory;

        /**
         * Creates a fresh temporary directory before each test.
         */
        public function setUp () : void {
            $this->tempDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "wm_al_bundler_" . uniqid();
            mkdir($this->tempDirectory, 0755, true);
        }

        /**
         * Deletes all temporary files and directories created by each test.
         */
        public function tearDown () : void {
            $this->deleteDirectoryRecursively($this->tempDirectory);
        }

        /**
         * Deletes a directory tree recursively.
         * @param string $path The root path.
         * @return void
         */
        private function deleteDirectoryRecursively (string $path) : void {
            if (!is_dir($path)) return;

            $entries = scandir($path);

            if (!is_array($entries)) return;

            foreach ($entries as $entry) {
                if ($entry === "." || $entry === "..") continue;

                $entryPath = $path . DIRECTORY_SEPARATOR . $entry;

                if (is_dir($entryPath)) {
                    $this->deleteDirectoryRecursively($entryPath);
                    continue;
                }

                @unlink($entryPath);
            }

            @rmdir($path);
        }

        /**
         * Creates a PHP source file under the temporary directory.
         * @param string $relativePath The file path relative to $tempDirectory.
         * @param string $content The PHP file content.
         * @return string The absolute path to the created source file.
         */
        private function createSourceFile (string $relativePath, string $content) : string {
            $absolutePath = $this->tempDirectory . DIRECTORY_SEPARATOR . $relativePath;
            $directory = dirname($absolutePath);

            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($absolutePath, $content);

            return $absolutePath;
        }

        #[Group("Bundler")]
        #[Define(
            name: "generateBundle() — Writes Bundle And Metadata",
            description: "generateBundle() writes both the output bundle and a .meta.json sidecar file."
        )]
        public function testGenerateBundleWritesBundleAndMetadata () : void {
            $fileA = $this->createSourceFile(
                "src/A.php",
                "<?php\nnamespace Wingman\\Autoloader\\Tests\\Fixtures;\nclass BundlerFixtureA {}\n"
            );

            $fileB = $this->createSourceFile(
                "src/B.php",
                "<?php\nnamespace Wingman\\Autoloader\\Tests\\Fixtures;\nclass BundlerFixtureB {}\n"
            );

            $output = $this->tempDirectory . DIRECTORY_SEPARATOR . "dist" . DIRECTORY_SEPARATOR . "bundle.php";

            $result = (new Bundler([$fileA, $fileB]))->generateBundle($output, "integration", true);

            $this->assertTrue($result["generated"], "The first generation should report generated = true.");
            $this->assertTrue(is_file($output), "The bundle output file should be created.");
            $this->assertTrue(is_file($output . ".meta.json"), "The metadata output file should be created.");
            $this->assertEquals("bundle", $result["mode"], "The mode should be bundle.");
            $this->assertEquals(2, count($result["files"]), "Two files should be included in the bundle.");
        }

        #[Group("Bundler")]
        #[Define(
            name: "generateBundle() — Metadata Contains Signature And FileCount",
            description: "The .meta.json file contains a deterministic signature and the correct fileCount."
        )]
        public function testGenerateBundleMetadataContainsSignatureAndFileCount () : void {
            $fileA = $this->createSourceFile("src/A.php", "<?php\nclass BundlerMetaA {}\n");
            $fileB = $this->createSourceFile("src/B.php", "<?php\nclass BundlerMetaB {}\n");

            $output = $this->tempDirectory . DIRECTORY_SEPARATOR . "bundle.php";

            (new Bundler([$fileA, $fileB]))->generateBundle($output, "meta", true);

            $rawMeta = file_get_contents($output . ".meta.json");
            $meta = is_string($rawMeta) ? json_decode($rawMeta, true) : null;

            $this->assertTrue(is_array($meta), "Metadata should decode to an array.");
            $this->assertArrayHasKey("signature", $meta, "Metadata should include a signature.");
            $this->assertArrayHasKey("fileCount", $meta, "Metadata should include fileCount.");
            $this->assertEquals(2, $meta["fileCount"], "fileCount should equal the number of included files.");
        }

        #[Group("Bundler")]
        #[Define(
            name: "generateBundle() — Unchanged Inputs Skip Regeneration",
            description: "With unchanged inputs and force = false, a second call returns generated = false."
        )]
        public function testGenerateBundleSkipsWhenUnchangedAndNotForced () : void {
            $fileA = $this->createSourceFile("src/A.php", "<?php\nclass BundlerSkipA {}\n");
            $output = $this->tempDirectory . DIRECTORY_SEPARATOR . "bundle.php";
            $bundler = new Bundler([$fileA]);

            $first = $bundler->generateBundle($output, "skip-check", false);
            $second = $bundler->generateBundle($output, "skip-check", false);

            $this->assertTrue($first["generated"], "The first call should generate output.");
            $this->assertFalse($second["generated"], "The second unchanged call should skip generation.");
        }

        #[Group("Bundler")]
        #[Define(
            name: "generateBundle() — Force Regeneration Overrides Signature Check",
            description: "With force = true, generation proceeds even when inputs are unchanged."
        )]
        public function testGenerateBundleForceRegeneratesWhenUnchanged () : void {
            $fileA = $this->createSourceFile("src/A.php", "<?php\nclass BundlerForceA {}\n");
            $output = $this->tempDirectory . DIRECTORY_SEPARATOR . "bundle.php";
            $bundler = new Bundler([$fileA]);

            $bundler->generateBundle($output, "force", false);
            $forced = $bundler->generateBundle($output, "force", true);

            $this->assertTrue($forced["generated"], "Forced generation should regenerate output even when unchanged.");
        }

        #[Group("Bundler")]
        #[Define(
            name: "generateBundle() — Exclusion Pattern Skips Matching Files",
            description: "setExcludePatterns() skips files whose path matches the provided pattern."
        )]
        public function testGenerateBundleExcludesMatchingFiles () : void {
            $included = $this->createSourceFile("src/Keep.php", "<?php\nclass BundlerKeep {}\n");
            $excluded = $this->createSourceFile("vendor/Skip.php", "<?php\nclass BundlerSkip {}\n");
            $output = $this->tempDirectory . DIRECTORY_SEPARATOR . "bundle.php";

            $result = (new Bundler([$included, $excluded]))
                ->setExcludePatterns(["/vendor/"])
                ->generateBundle($output, "exclude", true);

            $this->assertEquals(1, count($result["files"]), "Only one file should remain after exclusion.");
            $this->assertEquals(1, count($result["skippedFiles"]), "One file should be reported as skipped.");
            $this->assertEquals("excluded", $result["skippedFiles"][0]["reason"], "The skipped file reason should be excluded.");
        }

        #[Group("Bundler")]
        #[Define(
            name: "generateBundle() — Unsafe Files Are Skipped By Default",
            description: "Files containing unsafe tokens (e.g. require/include) are skipped when skipUnsafeFiles is enabled."
        )]
        public function testGenerateBundleSkipsUnsafeFilesByDefault () : void {
            $safe = $this->createSourceFile("src/Safe.php", "<?php\nclass BundlerSafe {}\n");
            $unsafe = $this->createSourceFile("src/Unsafe.php", "<?php\nrequire_once __DIR__ . '/x.php';\nclass BundlerUnsafe {}\n");
            $output = $this->tempDirectory . DIRECTORY_SEPARATOR . "bundle.php";

            $result = (new Bundler([$safe, $unsafe]))->generateBundle($output, "unsafe", true);

            $this->assertEquals(1, count($result["files"]), "Only the safe file should be included.");
            $this->assertEquals(1, count($result["skippedFiles"]), "One file should be skipped as unsafe.");
            $this->assertEquals("unsafe", $result["skippedFiles"][0]["reason"], "The skipped reason should be unsafe.");
        }

        #[Group("Bundler")]
        #[Define(
            name: "generateBundle() — Unsafe Files Included When Safety Disabled",
            description: "setSkipUnsafeFiles(false) allows files with unsafe tokens to be included."
        )]
        public function testGenerateBundleIncludesUnsafeFilesWhenSafetyDisabled () : void {
            $safe = $this->createSourceFile("src/Safe.php", "<?php\nclass BundlerUnsafeOffSafe {}\n");
            $unsafe = $this->createSourceFile("src/Unsafe.php", "<?php\ninclude 'other.php';\nclass BundlerUnsafeOffUnsafe {}\n");
            $output = $this->tempDirectory . DIRECTORY_SEPARATOR . "bundle.php";

            $result = (new Bundler([$safe, $unsafe]))
                ->setSkipUnsafeFiles(false)
                ->generateBundle($output, "unsafe-off", true);

            $this->assertEquals(2, count($result["files"]), "Both files should be included when safety checks are disabled.");
        }

        #[Group("Bundler")]
        #[Define(
            name: "generateBundle() — No Eligible Files Throws",
            description: "generateBundle() throws LackOfEligibleFilesException when no files are eligible."
        )]
        public function testGenerateBundleThrowsWhenNoEligibleFiles () : void {
            $excluded = $this->createSourceFile("vendor/Only.php", "<?php\nclass BundlerNone {}\n");
            $output = $this->tempDirectory . DIRECTORY_SEPARATOR . "bundle.php";

            $bundler = (new Bundler([$excluded]))->setExcludePatterns(["/vendor/"]);

            $this->assertThrows(
                LackOfEligibleFilesException::class,
                fn () => $bundler->generateBundle($output, "none", false),
                "generateBundle() should throw when no files are eligible."
            );
        }

        #[Group("Bundler")]
        #[Define(
            name: "generatePreloadScript() — Writes Preload Script And Metadata",
            description: "generatePreloadScript() writes both the preload script and a .meta.json sidecar."
        )]
        public function testGeneratePreloadScriptWritesOutputs () : void {
            $fileA = $this->createSourceFile("src/A.php", "<?php\nclass BundlerPreloadA {}\n");
            $fileB = $this->createSourceFile("src/B.php", "<?php\nclass BundlerPreloadB {}\n");
            $output = $this->tempDirectory . DIRECTORY_SEPARATOR . "preload.php";

            $result = (new Bundler([$fileA, $fileB]))->generatePreloadScript($output, "preload", true);
            $content = file_get_contents($output);

            $this->assertTrue($result["generated"], "The first preload generation should report generated = true.");
            $this->assertTrue(is_file($output), "The preload script should be created.");
            $this->assertTrue(is_file($output . ".meta.json"), "The preload metadata should be created.");
            $this->assertTrue(is_string($content) && str_contains($content, "opcache_compile_file"), "The preload script should contain opcache_compile_file calls.");
        }

        #[Group("Bundler")]
        #[Define(
            name: "generatePreloadScript() — Unchanged Inputs Skip Regeneration",
            description: "With unchanged inputs and force = false, preload generation is skipped on the second call."
        )]
        public function testGeneratePreloadScriptSkipsWhenUnchangedAndNotForced () : void {
            $fileA = $this->createSourceFile("src/A.php", "<?php\nclass BundlerPreloadSkipA {}\n");
            $output = $this->tempDirectory . DIRECTORY_SEPARATOR . "preload.php";
            $bundler = new Bundler([$fileA]);

            $first = $bundler->generatePreloadScript($output, "preload-skip", false);
            $second = $bundler->generatePreloadScript($output, "preload-skip", false);

            $this->assertTrue($first["generated"], "The first preload generation should generate output.");
            $this->assertFalse($second["generated"], "The second unchanged preload generation should be skipped.");
        }

        #[Group("Bundler")]
        #[Define(
            name: "generatePreloadScript() — No Eligible Files Throws",
            description: "generatePreloadScript() throws LackOfEligibleFilesException when no files are eligible."
        )]
        public function testGeneratePreloadScriptThrowsWhenNoEligibleFiles () : void {
            $excluded = $this->createSourceFile("vendor/Only.php", "<?php\nclass BundlerPreloadNone {}\n");
            $output = $this->tempDirectory . DIRECTORY_SEPARATOR . "preload.php";

            $bundler = (new Bundler([$excluded]))->setExcludePatterns(["/vendor/"]);

            $this->assertThrows(
                LackOfEligibleFilesException::class,
                fn () => $bundler->generatePreloadScript($output, "none", false),
                "generatePreloadScript() should throw when no files are eligible."
            );
        }

        #[Group("Bundler")]
        #[Define(
            name: "setFiles() — Ignores Non-String Entries",
            description: "setFiles() silently ignores non-string values while keeping valid files."
        )]
        public function testSetFilesIgnoresNonStringEntries () : void {
            $fileA = $this->createSourceFile("src/A.php", "<?php\nclass BundlerSetFilesA {}\n");
            $output = $this->tempDirectory . DIRECTORY_SEPARATOR . "bundle.php";

            $result = (new Bundler())
                ->setFiles([$fileA, null, 42, ["bad"]])
                ->generateBundle($output, "set-files", true);

            $this->assertEquals(1, count($result["files"]), "Only valid string file paths should be queued.");
        }
    }
?>