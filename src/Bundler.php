<?php
    /**
     * Project Name:    Wingman Vortex - Bundler
     * Created by:      Angel Politis
     * Creation Date:   Mar 18 2026
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2026-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Autoloader namespace.
    namespace Wingman\Vortex;

    # Import the following classes to the current scope.
    use Wingman\Vortex\Exceptions\LackOfEligibleFilesException;
    use Wingman\Vortex\Exceptions\OutputDirectoryCreationException;
    use Wingman\Vortex\Exceptions\OutputLockAcquisitionException;
    use Wingman\Vortex\Exceptions\OutputFileMoveException;
    use Wingman\Vortex\Exceptions\SourceFileReadException;
    use Wingman\Vortex\Exceptions\TemporaryOutputFileWriteException;

    /**
     * Compiles discovered PHP source files into deterministic bundle artefacts.
     *
     * This service is intentionally offline-oriented: it should be called during
     * a warm-up/build step and not from the hot request path. Two artefact modes
     * are supported:
     * - Bundle mode: concatenates class files into a single require-able file.
     * - Preload mode: generates an OPcache preload script for server start-up.
     *
     * Safety controls:
     * - Files that look unsafe for concatenation can be rejected automatically.
     * - Exclusion patterns can skip vendor/runtime-sensitive files.
     * - A side-car metadata signature is written so unchanged inputs are not
     *   rebuilt repeatedly.
     *
     * @package Wingman\Vortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.1
     */
    class Bundler {
        /**
         * Patterns used to exclude files from generated artefacts.
         * @var string[]
         */
        private array $excludePatterns = [];

        /**
         * Files currently queued for bundling.
         * @var string[]
         */
        private array $files = [];

        /**
         * Whether unsafe files should be skipped from bundle artefacts.
         * @var bool
         */
        private bool $skipUnsafeFiles = true;

        /**
         * Whether bundle payloads should be stripped with php_strip_whitespace().
         * @var bool
         */
        private bool $stripWhitespace = false;

        /**
         * Creates a new bundler.
         * @param string[] $files Optional initial file list.
         */
        public function __construct (array $files = []) {
            $this->setFiles($files);
        }

        /**
         * Adds one file to the bundler queue.
         * @param string $file The file path to add.
         * @return static The bundler instance.
         */
        public function addFile (string $file) : static {
            $normalised = $this->normaliseFilePath($file);

            if ($normalised !== null && !isset($this->files[$normalised])) {
                $this->files[$normalised] = $normalised;
            }

            return $this;
        }

        /**
         * Captures successfully loaded files from the Autoloader registry.
         * @param int $minPriority The minimum autoloader priority to consider.
         * @return static The bundler instance.
         */
        public function captureLoadedFiles (int $minPriority = -PHP_INT_MAX) : static {
            return $this->setFiles(Autoloader::getLoadedFiles($minPriority));
        }

        /**
         * Generates a monolithic PHP bundle file from queued files.
         *
         * A metadata side-car is written next to the output file at
         * "{outputFile}.meta.json". When the signature is unchanged, generation is
         * skipped unless force is enabled.
         *
         * @param string $outputFile The bundle output file.
         * @param string $profile The profile name of the generated artefact.
         * @param bool $force Whether to force generation even when unchanged.
         * @return array Summary metadata for the generation run.
         */
        public function generateBundle (string $outputFile, string $profile = "default", bool $force = false) : array {
            $selection = $this->selectEligibleFiles(true);
            $eligibleFiles = $selection["eligible"];
            $skippedFiles = $selection["skipped"];

            if (empty($eligibleFiles)) {
                throw new LackOfEligibleFilesException("No eligible files were found for bundle generation.");
            }

            $signature = $this->buildSignature($eligibleFiles, "bundle", [
                "profile" => $profile,
                "stripWhitespace" => $this->stripWhitespace,
            ]);

            $metaFile = $outputFile . ".meta.json";
            $existingMeta = $this->readMetadata($metaFile);

            if (!$force && is_file($outputFile) && ($existingMeta["signature"] ?? null) === $signature) {
                return [
                    "generated" => false,
                    "mode" => "bundle",
                    "outputFile" => $outputFile,
                    "metaFile" => $metaFile,
                    "profile" => $profile,
                    "signature" => $signature,
                    "files" => $eligibleFiles,
                    "skippedFiles" => $skippedFiles,
                ];
            }

            $content = "<?php\n";
            $content .= "/**\n";
            $content .= " * Wingman Vortex Bundle\n";
            $content .= " * Profile: " . $profile . "\n";
            $content .= " * Generated: " . gmdate("Y-m-d H:i:s") . " UTC\n";
            $content .= " * Signature: " . $signature . "\n";
            $content .= " */\n\n";

            foreach ($eligibleFiles as $file) {
                $content .= "/* Source: " . $file . " */\n";
                $content .= $this->extractPhpPayload($file) . "\n\n";
            }

            $metadata = [
                "mode" => "bundle",
                "profile" => $profile,
                "generatedAt" => gmdate("c"),
                "signature" => $signature,
                "fileCount" => count($eligibleFiles),
                "files" => array_values($eligibleFiles),
                "skippedFiles" => $skippedFiles,
                "phpVersion" => PHP_VERSION,
            ];

            $metaContent = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $this->writeFileSetAtomically($outputFile, $content, $metaFile, $metaContent === false ? "{}" : $metaContent);

            return [
                "generated" => true,
                "mode" => "bundle",
                "outputFile" => $outputFile,
                "metaFile" => $metaFile,
                "profile" => $profile,
                "signature" => $signature,
                "files" => $eligibleFiles,
                "skippedFiles" => $skippedFiles,
            ];
        }

        /**
         * Generates an OPcache preload script from queued files.
         *
         * A metadata side-car is written next to the output file at
         * "{outputFile}.meta.json". When the signature is unchanged, generation is
         * skipped unless force is enabled.
         *
         * @param string $outputFile The preload script output file.
         * @param string $profile The profile name of the generated artefact.
         * @param bool $force Whether to force generation even when unchanged.
         * @return array Summary metadata for the generation run.
         */
        public function generatePreloadScript (string $outputFile, string $profile = "default", bool $force = false) : array {
            $selection = $this->selectEligibleFiles(false);
            $eligibleFiles = $selection["eligible"];
            $skippedFiles = $selection["skipped"];

            if (empty($eligibleFiles)) {
                throw new LackOfEligibleFilesException("No eligible files were found for preload script generation.");
            }

            $signature = $this->buildSignature($eligibleFiles, "preload", [
                "profile" => $profile,
            ]);

            $metaFile = $outputFile . ".meta.json";
            $existingMeta = $this->readMetadata($metaFile);

            if (!$force && is_file($outputFile) && ($existingMeta["signature"] ?? null) === $signature) {
                return [
                    "generated" => false,
                    "mode" => "preload",
                    "outputFile" => $outputFile,
                    "metaFile" => $metaFile,
                    "profile" => $profile,
                    "signature" => $signature,
                    "files" => $eligibleFiles,
                    "skippedFiles" => $skippedFiles,
                ];
            }

            $content = "<?php\n";
            $content .= "/**\n";
            $content .= " * Wingman Vortex Preload Script\n";
            $content .= " * Profile: " . $profile . "\n";
            $content .= " * Generated: " . gmdate("Y-m-d H:i:s") . " UTC\n";
            $content .= " * Signature: " . $signature . "\n";
            $content .= " */\n\n";
            $content .= "if (!function_exists(\"opcache_compile_file\")) return;\n\n";

            foreach ($eligibleFiles as $file) {
                $escapedPath = addslashes($file);
                $content .= "opcache_compile_file(\"" . $escapedPath . "\");\n";
            }

            $metadata = [
                "mode" => "preload",
                "profile" => $profile,
                "generatedAt" => gmdate("c"),
                "signature" => $signature,
                "fileCount" => count($eligibleFiles),
                "files" => array_values($eligibleFiles),
                "skippedFiles" => $skippedFiles,
                "phpVersion" => PHP_VERSION,
            ];

            $metaContent = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $this->writeFileSetAtomically($outputFile, $content, $metaFile, $metaContent === false ? "{}" : $metaContent);

            return [
                "generated" => true,
                "mode" => "preload",
                "outputFile" => $outputFile,
                "metaFile" => $metaFile,
                "profile" => $profile,
                "signature" => $signature,
                "files" => $eligibleFiles,
                "skippedFiles" => $skippedFiles,
            ];
        }

        /**
         * Sets exclusion patterns used to skip files.
         * @param string[] $patterns The exclusion patterns.
         * @return static The bundler instance.
         */
        public function setExcludePatterns (array $patterns) : static {
            $this->excludePatterns = [];

            foreach ($patterns as $pattern) {
                if (!is_string($pattern) || trim($pattern) === "") continue;
                $this->excludePatterns[] = $pattern;
            }

            return $this;
        }

        /**
         * Sets the queued file list.
         * @param string[] $files The files to queue.
         * @return static The bundler instance.
         */
        public function setFiles (array $files) : static {
            $this->files = [];

            foreach ($files as $file) {
                if (!is_string($file)) continue;
                $this->addFile($file);
            }

            return $this;
        }

        /**
         * Sets whether files deemed unsafe should be skipped from bundles.
         * @param bool $skipUnsafeFiles Whether unsafe files should be skipped.
         * @return static The bundler instance.
         */
        public function setSkipUnsafeFiles (bool $skipUnsafeFiles) : static {
            $this->skipUnsafeFiles = $skipUnsafeFiles;
            return $this;
        }

        /**
         * Sets whether bundle payloads should be whitespace-stripped.
         * @param bool $stripWhitespace Whether whitespace should be stripped.
         * @return static The bundler instance.
         */
        public function setStripWhitespace (bool $stripWhitespace) : static {
            $this->stripWhitespace = $stripWhitespace;
            return $this;
        }

        /**
         * Builds a deterministic signature for a selected file set.
         * @param string[] $files The eligible file list.
         * @param string $mode The generation mode.
         * @param array $options Additional options affecting output.
         * @return string The deterministic signature.
         */
        private function buildSignature (array $files, string $mode, array $options = []) : string {
            $payload = [
                "mode" => $mode,
                "phpVersion" => PHP_VERSION,
                "options" => $options,
                "files" => [],
            ];

            foreach ($files as $file) {
                $payload["files"][] = [
                    "path" => $file,
                    "mtime" => filemtime($file),
                    "hash" => hash_file("sha256", $file),
                ];
            }

            return hash("sha256", json_encode($payload, JSON_UNESCAPED_SLASHES));
        }

        /**
         * Extracts PHP payload from a source file for concatenation.
         * @param string $file The source file.
         * @return string The extracted payload.
         */
        private function extractPhpPayload (string $file) : string {
            $code = $this->stripWhitespace ? php_strip_whitespace($file) : file_get_contents($file);

            if ($code === false) {
                throw new SourceFileReadException("Failed to read source file: " . $file);
            }

            $code = preg_replace('/^\s*<\?php\b/i', "", $code, 1);
            $code = preg_replace('/\?>\s*$/', "", $code);

            return trim((string) $code);
        }

        /**
         * Gets whether a file should be excluded by configured patterns.
         * @param string $file The file path to check.
         * @return bool Whether the file should be excluded.
         */
        private function isExcludedByPattern (string $file) : bool {
            foreach ($this->excludePatterns as $pattern) {
                if (@preg_match($pattern, "") !== false && preg_match($pattern, $file) === 1) {
                    return true;
                }

                if (str_contains($file, $pattern)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Gets whether a file appears safe for monolithic concatenation.
         * @param string $file The file to inspect.
         * @return bool Whether the file appears safe to bundle.
         */
        private function isSafeForBundle (string $file) : bool {
            $code = file_get_contents($file);

            if ($code === false) {
                return false;
            }

            $tokens = token_get_all($code);

            foreach ($tokens as $token) {
                if (!is_array($token)) continue;

                $type = $token[0];

                if (
                    $type === T_DIR ||
                    $type === T_FILE ||
                    $type === T_EXIT ||
                    $type === T_EVAL ||
                    $type === T_INCLUDE ||
                    $type === T_INCLUDE_ONCE ||
                    $type === T_REQUIRE ||
                    $type === T_REQUIRE_ONCE
                ) {
                    return false;
                }
            }

            return true;
        }

        /**
         * Normalises a candidate file path.
         * @param string $file The candidate file path.
         * @return string|null The normalised path, or null when invalid.
         */
        private function normaliseFilePath (string $file) : ?string {
            if ($file === "") return null;

            $realPath = realpath($file);

            if ($realPath === false || !is_file($realPath) || !is_readable($realPath)) {
                return null;
            }

            return $realPath;
        }

        /**
         * Reads metadata for a previously generated artefact.
         * @param string $metaFile The metadata file path.
         * @return array The decoded metadata, or an empty array when unavailable.
         */
        private function readMetadata (string $metaFile) : array {
            if (!is_file($metaFile)) {
                return [];
            }

            $raw = file_get_contents($metaFile);

            if ($raw === false) {
                return [];
            }

            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        /**
         * Selects eligible files according to configuration.
         * @param bool $applySafetyChecks Whether bundle safety checks should run.
         * @return array{eligible: string[], skipped: array<int, array<string, string>>} Eligible and skipped files.
         */
        private function selectEligibleFiles (bool $applySafetyChecks) : array {
            $eligible = [];
            $skipped = [];

            foreach ($this->files as $file) {
                if ($this->isExcludedByPattern($file)) {
                    $skipped[] = ["file" => $file, "reason" => "excluded"];
                    continue;
                }

                if ($applySafetyChecks && $this->skipUnsafeFiles && !$this->isSafeForBundle($file)) {
                    $skipped[] = ["file" => $file, "reason" => "unsafe"];
                    continue;
                }

                $eligible[] = $file;
            }

            return [
                "eligible" => $eligible,
                "skipped" => $skipped,
            ];
        }

        /**
         * Writes an output/meta artefact set atomically with rollback support.
         * @param string $outputFile The main artefact output file.
         * @param string $outputContent The main artefact content.
         * @param string $metaFile The metadata output file.
         * @param string $metaContent The metadata content.
         * @return void
         */
        private function writeFileSetAtomically (string $outputFile, string $outputContent, string $metaFile, string $metaContent) : void {
            $targetDirectory = dirname($outputFile);

            if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
                throw new OutputDirectoryCreationException("Failed to create output directory: " . $targetDirectory);
            }

            $lockFile = $outputFile . ".lock";
            $lockHandle = fopen($lockFile, "c");

            if ($lockHandle === false) {
                throw new OutputLockAcquisitionException("Failed to create output lock file: " . $lockFile);
            }

            if (!flock($lockHandle, LOCK_EX)) {
                fclose($lockHandle);
                throw new OutputLockAcquisitionException("Failed to acquire output lock: " . $lockFile);
            }

            try {

                $identifier = uniqid("", true);
                $tempOutputFile = $targetDirectory . DIRECTORY_SEPARATOR . basename($outputFile) . ".tmp." . $identifier;
                $tempMetaFile = $targetDirectory . DIRECTORY_SEPARATOR . basename($metaFile) . ".tmp." . $identifier;

                if (file_put_contents($tempOutputFile, $outputContent) === false) {
                    throw new TemporaryOutputFileWriteException("Failed to write temporary output file: " . $tempOutputFile);
                }

                if (file_put_contents($tempMetaFile, $metaContent) === false) {
                    @unlink($tempOutputFile);
                    throw new TemporaryOutputFileWriteException("Failed to write temporary output file: " . $tempMetaFile);
                }

                $backupOutputFile = null;
                $backupMetaFile = null;

                if (is_file($outputFile)) {
                    $backupOutputFile = $outputFile . ".bak." . $identifier;

                    if (!rename($outputFile, $backupOutputFile)) {
                        @unlink($tempOutputFile);
                        @unlink($tempMetaFile);
                        throw new OutputFileMoveException("Failed to stage output file for atomic swap: " . $outputFile);
                    }
                }

                if (is_file($metaFile)) {
                    $backupMetaFile = $metaFile . ".bak." . $identifier;

                    if (!rename($metaFile, $backupMetaFile)) {
                        @unlink($tempOutputFile);
                        @unlink($tempMetaFile);

                        if ($backupOutputFile !== null) {
                            @rename($backupOutputFile, $outputFile);
                        }

                        throw new OutputFileMoveException("Failed to stage metadata file for atomic swap: " . $metaFile);
                    }
                }

                if (!rename($tempOutputFile, $outputFile)) {
                    @unlink($tempOutputFile);
                    @unlink($tempMetaFile);

                    if ($backupOutputFile !== null) {
                        @rename($backupOutputFile, $outputFile);
                    }

                    if ($backupMetaFile !== null) {
                        @rename($backupMetaFile, $metaFile);
                    }

                    throw new OutputFileMoveException("Failed to move output file into place: " . $outputFile);
                }

                if (!rename($tempMetaFile, $metaFile)) {
                    @unlink($tempMetaFile);
                    @unlink($outputFile);

                    if ($backupOutputFile !== null) {
                        @rename($backupOutputFile, $outputFile);
                    }

                    if ($backupMetaFile !== null) {
                        @rename($backupMetaFile, $metaFile);
                    }

                    throw new OutputFileMoveException("Failed to move output file into place: " . $metaFile);
                }

                if ($backupOutputFile !== null) {
                    @unlink($backupOutputFile);
                }

                if ($backupMetaFile !== null) {
                    @unlink($backupMetaFile);
                }
            }
            finally {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }
        }
    }
?>