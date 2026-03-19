<?php
    /**
     * Project Name:    Wingman Vortex - Queue Tests
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
     * Tests for getQueue() ordering: priority-descending, creation-date-ascending
     * for ties, disabled exclusion, and minimum-priority filtering.
     */
    class AutoloaderQueueTest extends AutoloaderTestCase {
        // ── Queue ordering ────────────────────────────────────────────────────

        #[Group("Queue")]
        #[Define(
            name: "getQueue() — Empty When Registry Is Empty",
            description: "getQueue() returns an empty array when nothing is registered."
        )]
        public function testGetQueueEmptyForEmptyRegistry () : void {
            $this->assertEmpty(Autoloader::getQueue(), "Queue should be empty when nothing is registered.");
        }

        #[Group("Queue")]
        #[Define(
            name: "getQueue() — Higher Priority First",
            description: "Autoloaders with higher priority appear before those with lower priority."
        )]
        public function testGetQueueHigherPriorityFirst () : void {
            Autoloader::register("low", fn ($c) => null, 1);
            Autoloader::register("medium", fn ($c) => null, 5);
            Autoloader::register("high", fn ($c) => null, 10);

            $queue = Autoloader::getQueue();

            $this->assertEquals("high", $queue[0], "Highest priority should be first.");
            $this->assertEquals("medium", $queue[1], "Medium priority should be second.");
            $this->assertEquals("low", $queue[2], "Lowest priority should be last.");
        }

        #[Group("Queue")]
        #[Define(
            name: "getQueue() — Equal Priority Ordered by Registration Date",
            description: "When two autoloaders share a priority, the one registered first appears first."
        )]
        public function testGetQueueTieOrderedByCreationDate () : void {
            Autoloader::register("first", fn ($c) => null, 5);
            usleep(1000);
            Autoloader::register("second", fn ($c) => null, 5);

            $queue = Autoloader::getQueue();

            $this->assertEquals("first",  $queue[0], "Earlier-registered autoloader should precede a later one at the same priority.");
            $this->assertEquals("second", $queue[1], "Later-registered autoloader should follow at equal priority.");
        }

        #[Group("Queue")]
        #[Define(
            name: "getQueue() — Disabled Autoloaders Are Excluded",
            description: "A disabled autoloader is not included in the queue."
        )]
        public function testGetQueueExcludesDisabled () : void {
            Autoloader::register("active", fn ($c) => null, 5);
            $disabled = Autoloader::register("inactive", fn ($c) => null, 5);
            $disabled->disable();

            $queue = Autoloader::getQueue();

            $this->assertNotContains("inactive", $queue, "A disabled autoloader should not appear in the queue.");
            $this->assertContains("active", $queue, "An enabled autoloader must appear in the queue.");
        }

        #[Group("Queue")]
        #[Define(
            name: "getQueue() — Re-enabling Restores Autoloader to Queue",
            description: "After enable(), a previously disabled autoloader appears in the queue again."
        )]
        public function testGetQueueIncludesAfterReEnable () : void {
            $al = Autoloader::register("al", fn ($c) => null, 5);
            $al->disable();
            $al->enable();

            $queue = Autoloader::getQueue();

            $this->assertContains("al", $queue, "A re-enabled autoloader must be back in the queue.");
        }

        #[Group("Queue")]
        #[Define(
            name: "getQueue() — Minimum Priority Filter",
            description: "Autoloaders below the minimum priority threshold are excluded from the queue."
        )]
        public function testGetQueueRespectsMinPriority () : void {
            Autoloader::register("below", fn ($c) => null, 0);
            Autoloader::register("at", fn ($c) => null, 5);
            Autoloader::register("above", fn ($c) => null, 10);

            $queue = Autoloader::getQueue(5);

            $this->assertNotContains("below", $queue, "Autoloader below minPriority should be excluded.");
            $this->assertContains("at", $queue, "Autoloader at minPriority should be included.");
            $this->assertContains("above", $queue, "Autoloader above minPriority should be included.");
        }

        #[Group("Queue")]
        #[Define(
            name: "dequeueRegistryBasedOnPriority() — Stops on First Success",
            description: "dequeueRegistryBasedOnPriority() stops running autoloaders once a class is found."
        )]
        public function testDequeueStopsOnFirstSuccess () : void {
            $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "wm_al_queue_" . uniqid();
            mkdir($tempDir, 0755, true);

            $className = "WingmanAlQueueStopTestCls_" . str_replace(".", "_", uniqid("", true));
            $filePath = $tempDir . DIRECTORY_SEPARATOR . $className . ".php";

            file_put_contents($filePath, "<?php class $className {}");

            $secondCalled = 0;

            Autoloader::register("winner", fn ($c) => $c === $className ? $filePath : null, 10);
            Autoloader::register("loser", function ($c) use (&$secondCalled) { $secondCalled++; return null; }, 5);

            Autoloader::dequeueRegistryBasedOnPriority($className);

            @unlink($filePath);
            @rmdir($tempDir);

            $this->assertEquals(0, $secondCalled, "The second autoloader should not run after the first one succeeds.");
        }
    }
?>