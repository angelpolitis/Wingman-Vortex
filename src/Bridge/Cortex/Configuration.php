<?php
    /**
     * Project Name:    Wingman Vortex - Cortex Bridge - Configuration
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2026-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Autoloader.Bridge.Cortex namespace.
    namespace Wingman\Vortex\Bridge\Cortex;

    # Guard against double-inclusion (e.g. via symlinked paths resolving to different strings
    # under require_once). If the alias or stub is already in place there is nothing to do.
    if (class_exists(__NAMESPACE__ . '\\Configuration', false)) return;

    # If Cortex is installed, alias the Cortex Configuration class to this namespace.
    if (class_exists(\Wingman\Cortex\Configuration::class)) {
        class_alias(\Wingman\Cortex\Configuration::class, __NAMESPACE__ . '\\Configuration');
        return;
    }

    # Import the following classes to the current scope.
    use ReflectionObject;

    /**
     * A no-op stub used when Cortex is not available.
     * Mirrors the static and instance API surface of `Wingman\Cortex\Configuration` that
     * Autoloader consumers rely on, so all call sites stay valid without Cortex installed.
     * @package Wingman\Vortex\Bridge\Cortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Configuration {
        /**
         * Returns `null`; Cortex is unavailable so no named configuration can be found.
         * @param string|null $name Ignored.
         * @return static|null Always `null`.
         */
        public static function find (?string $name = null) : ?static {
            return null;
        }

        /**
         * Returns `false`; Cortex is unavailable so no named configuration can exist.
         * @param string|null $name Ignored.
         * @return bool Always `false`.
         */
        public static function exists (?string $name = null) : bool {
            return false;
        }

        /**
         * Returns an empty array; Cortex is unavailable so there are no registered configurations.
         * @return array Always an empty array.
         */
        public static function getAll () : array {
            return [];
        }

        /**
         * Returns an empty array; Cortex is unavailable so there are no registered configurations.
         * @return array Always an empty array.
         */
        public static function getAllNames () : array {
            return [];
        }

        /**
         * Returns a new stub instance; Cortex is unavailable so no data is actually stored.
         * @param iterable $data Ignored.
         * @param string|null $name Ignored.
         * @return static A fresh stub instance.
         */
        public static function fromIterable (iterable $data, ?string $name = null) : static {
            return new static();
        }

        /**
         * Hydrates `#[Configurable]`-annotated properties of `$target` from a flat dot-notation array.
         * When `$source` is already a stub instance it is returned as-is (nothing to read from it).
         * @param object $target The object whose properties should be hydrated.
         * @param array|self $source A flat dot-notation key-value array, or a stub instance.
         * @param array $map Ignored in the stub; present for API compatibility.
         * @param bool $strict Ignored in the stub; present for API compatibility.
         * @return self A stub instance.
         */
        public static function hydrate (object $target, array|self $source = [], array $map = [], bool $strict = false) : self {
            if (is_array($source) && !empty($source)) {
                $reflection = new ReflectionObject($target);
                $groupKey = null;

                foreach ($reflection->getAttributes() as $attribute) {
                    if ($attribute->getName() !== \Wingman\Vortex\Bridge\Cortex\Attributes\ConfigGroup::class) continue;

                    $instance = $attribute->newInstance();

                    if (method_exists($instance, "getKey")) {
                        $groupKey = $instance->getKey();
                        break;
                    }
                }

                foreach ($reflection->getProperties() as $property) {
                    foreach ($property->getAttributes() as $attribute) {
                        if ($attribute->getName() !== \Wingman\Vortex\Bridge\Cortex\Attributes\Configurable::class) continue;

                        $configurable = $attribute->newInstance();

                        if (method_exists($configurable, "getKey")) {
                            $key = $configurable->getKey();
                        }
                        else {
                            $key = $property->getName();
                        }

                        $candidateKeys = [$key];

                        if (is_string($groupKey) && trim($groupKey) !== "") {
                            $candidateKeys[] = $groupKey . "." . $key;
                        }

                        $resolvedKey = null;

                        foreach ($candidateKeys as $candidateKey) {
                            if (!array_key_exists($candidateKey, $source)) continue;
                            $resolvedKey = $candidateKey;
                            break;
                        }

                        if ($resolvedKey === null) continue;

                        $value = $source[$resolvedKey];
                        $type = $property->getType();

                        if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
                            $value = match ($type->getName()) {
                                'bool' => (bool) $value,
                                'int' => (int) $value,
                                'float' => (float) $value,
                                'string' => (string) $value,
                                default => $value,
                            };
                        }

                        $property->setValue($target, $value);
                    }
                }
            }

            return $source instanceof self ? $source : new static();
        }

        /**
         * Returns `null`; Cortex is unavailable so the configuration has no name.
         * @return string|null Always `null`.
         */
        public function getName () : ?string {
            return null;
        }

        /**
         * Returns the default value because Cortex is unavailable.
         * @param string $key Ignored.
         * @param mixed $default The default fallback.
         * @return mixed The default value.
         */
        public function get (string $key, mixed $default = null) : mixed {
            return $default;
        }

        /**
         * No-op import; Cortex is unavailable so source files cannot be parsed.
         * @param string|array $sources Ignored.
         * @param array $options Ignored.
         * @return static The stub, for fluent chaining.
         */
        public function import (string|array $sources, array $options = []) : static {
            return $this;
        }

        /**
         * No-op; Cortex is unavailable so object state cannot be captured.
         * @param object $object Ignored.
         * @param string $name Ignored.
         * @return static The stub, for fluent chaining.
         */
        public function captureObject (object $object, string $name) : static {
            return $this;
        }

        /**
         * No-op; Cortex is unavailable so object state cannot be restored.
         * @param object $object Ignored.
         * @param string $name Ignored.
         * @return static The stub, for fluent chaining.
         */
        public function restoreObject (object $object, string $name) : static {
            return $this;
        }
    }
?>