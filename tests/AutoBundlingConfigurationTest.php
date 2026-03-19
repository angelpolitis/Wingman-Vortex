<?php
    /**
     * Project Name:    Wingman Vortex - Auto-Bundling Configuration Tests
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
    use Wingman\Vortex\Autoloader;

    /**
     * Tests for configureAutoBundling() and isAutoBundlingEnabled(), verifying that
     * short keys, full dotted keys, and defaults are all applied correctly.
     */
    class AutoBundlingConfigurationTest extends AutoloaderTestCase {
        // ── Defaults ──────────────────────────────────────────────────────────

        #[Group("AutoBundling")]
        #[Define(
            name: "isAutoBundlingEnabled() — Disabled by Default",
            description: "Before any configuration, isAutoBundlingEnabled() returns false."
        )]
        public function testAutoBundlingDisabledByDefault () : void {
            $this->assertFalse(Autoloader::isAutoBundlingEnabled(), "Auto-bundling should be disabled by default.");
        }

        // ── Short keys ────────────────────────────────────────────────────────

        #[Group("AutoBundling")]
        #[Define(
            name: "configureAutoBundling() — Short Key 'enabled' Enables Bundling",
            description: "Passing ['enabled' => true] with a short key enables auto-bundling."
        )]
        public function testConfigureWithShortKeyEnabled () : void {
            Autoloader::configureAutoBundling(["enabled" => true]);

            $this->assertTrue(Autoloader::isAutoBundlingEnabled(), "isAutoBundlingEnabled() should return true after setting enabled = true.");
        }

        #[Group("AutoBundling")]
        #[Define(
            name: "configureAutoBundling() — Short Key 'profile' Is Applied",
            description: "Passing ['profile' => 'prod'] with a short key sets the profile to 'prod'."
        )]
        public function testConfigureWithShortKeyProfile () : void {
            Autoloader::configureAutoBundling(["profile" => "prod"]);

            $this->assertEquals("prod", $this->readStaticProperty("autoBundlingProfile"), "The profile should be updated to 'prod'.");
        }

        #[Group("AutoBundling")]
        #[Define(
            name: "configureAutoBundling() — Short Key 'bundleFile' Is Applied",
            description: "Passing ['bundleFile' => '/tmp/bundle.php'] stores the normalised path."
        )]
        public function testConfigureWithShortKeyBundleFile () : void {
            Autoloader::configureAutoBundling(["bundleFile" => "/tmp/bundle.php"]);

            $this->assertNotNull($this->readStaticProperty("autoBundleFile"), "bundleFile should be set when a valid path is supplied.");
        }

        #[Group("AutoBundling")]
        #[Define(
            name: "configureAutoBundling() — Short Key 'excludePatterns' Is Applied",
            description: "Passing ['excludePatterns' => ['vendor/']] stores the patterns."
        )]
        public function testConfigureWithShortKeyExcludePatterns () : void {
            Autoloader::configureAutoBundling(["excludePatterns" => ["vendor/", "tests/"]]);

            $patterns = $this->readStaticProperty("autoBundlingExcludePatterns");
            $this->assertCount(2, $patterns, "Both exclude patterns should be stored.");
        }

        #[Group("AutoBundling")]
        #[Define(
            name: "configureAutoBundling() — Short Key 'stripWhitespace' Is Applied",
            description: "Passing ['stripWhitespace' => true] enables whitespace stripping."
        )]
        public function testConfigureWithShortKeyStripWhitespace () : void {
            Autoloader::configureAutoBundling(["stripWhitespace" => true]);

            $this->assertTrue($this->readStaticProperty("autoBundlingStripWhitespace"), "stripWhitespace should be true after configuration.");
        }

        // ── Dotted keys ───────────────────────────────────────────────────────

        #[Group("AutoBundling")]
        #[Define(
            name: "configureAutoBundling() — Dotted Key Enables Bundling",
            description: "Passing a full dotted key 'vortex.bundling.auto.enabled' = true enables auto-bundling."
        )]
        public function testConfigureWithDottedKeyEnabled () : void {
            Autoloader::configureAutoBundling(["vortex.bundling.auto.enabled" => true]);

            $this->assertTrue(Autoloader::isAutoBundlingEnabled(), "isAutoBundlingEnabled() should return true when using the full dotted key.");
        }

        #[Group("AutoBundling")]
        #[Define(
            name: "configureAutoBundling() — Dotted Key 'profile' Is Applied",
            description: "The full dotted key 'vortex.bundling.auto.profile' sets the profile."
        )]
        public function testConfigureWithDottedKeyProfile () : void {
            Autoloader::configureAutoBundling(["vortex.bundling.auto.profile" => "staging"]);

            $this->assertEquals("staging", $this->readStaticProperty("autoBundlingProfile"), "Profile should be 'staging' when set via the dotted key.");
        }

        // ── Edge cases ────────────────────────────────────────────────────────

        #[Group("AutoBundling")]
        #[Define(
            name: "configureAutoBundling() — Null Is a No-Op",
            description: "Passing null leaves all settings at their current values."
        )]
        public function testConfigureWithNullIsNoOp () : void {
            Autoloader::configureAutoBundling(null);

            $this->assertFalse(Autoloader::isAutoBundlingEnabled(), "Passing null should not change the enabled flag.");
        }

        #[Group("AutoBundling")]
        #[Define(
            name: "configureAutoBundling() — Empty Array Is a No-Op",
            description: "Passing an empty array leaves all settings at their current values."
        )]
        public function testConfigureWithEmptyArrayIsNoOp () : void {
            Autoloader::configureAutoBundling([]);

            $this->assertFalse(Autoloader::isAutoBundlingEnabled(), "An empty array should not change the enabled flag.");
        }

        #[Group("AutoBundling")]
        #[Define(
            name: "configureAutoBundling() — Empty bundleFile Is Stored as Null",
            description: "Passing an empty string for bundleFile results in the property being null."
        )]
        public function testConfigureEmptyBundleFileStoredAsNull () : void {
            Autoloader::configureAutoBundling(["bundleFile" => ""]);

            $this->assertNull($this->readStaticProperty("autoBundleFile"), "An empty bundleFile string should be normalised to null.");
        }

        #[Group("AutoBundling")]
        #[Define(
            name: "configureAutoBundling() — Blank-Only Profile Is Ignored",
            description: "A profile value consisting of only whitespace is rejected and the previous value is kept."
        )]
        public function testConfigureBlankProfileIsIgnored () : void {
            Autoloader::configureAutoBundling(["profile" => "custom"]);
            Autoloader::configureAutoBundling(["profile" => "   "]);

            $this->assertEquals("custom", $this->readStaticProperty("autoBundlingProfile"), "A whitespace-only profile should not overwrite an existing value.");
        }

        #[Group("AutoBundling")]
        #[Define(
            name: "configureAutoBundling() — Non-String Exclude Patterns Are Skipped",
            description: "Non-string entries in excludePatterns are silently ignored."
        )]
        public function testConfigureNonStringExcludePatternsSkipped () : void {
            Autoloader::configureAutoBundling(["excludePatterns" => ["valid/", null, 42, "also-valid/"]]);

            $patterns = $this->readStaticProperty("autoBundlingExcludePatterns");
            $this->assertCount(2, $patterns, "Only valid string patterns should be stored.");
        }

        // ── Path separator normalisation ──────────────────────────────────────

        #[Group("AutoBundling")]
        #[Define(
            name: "configureAutoBundling() — BundleFile Path Is Normalised",
            description: "Backslash separators in bundleFile are normalised to the platform separator."
        )]
        public function testConfigureBundleFilePathIsNormalised () : void {
            Autoloader::configureAutoBundling(["bundleFile" => "C:\\inetpub\\bundle.php"]);

            $stored = $this->readStaticProperty("autoBundleFile");
            $this->assertNotNull($stored, "A backslash-separated bundleFile should be stored (not null).");
            $this->assertStringNotContains("\\", $stored, "Stored bundleFile should not contain raw backslashes after normalisation.");
        }
    }
?>