<?php

namespace App\Support;

/** Small WordPress-style action and filter registry used by plugins. */
class Hooks
{
    private static array $hooks = [];

    public static function add(string $name, callable $callback, int $priority = 10): void
    {
        self::$hooks[$name][$priority][] = $callback;
    }

    public static function action(string $name, mixed ...$args): void
    {
        foreach (self::callbacks($name) as $callback) {
            $callback(...$args);
        }
    }

    public static function filter(string $name, mixed $value, mixed ...$args): mixed
    {
        foreach (self::callbacks($name) as $callback) {
            $value = $callback($value, ...$args);
        }

        return $value;
    }

    private static function callbacks(string $name): array
    {
        $byPriority = self::$hooks[$name] ?? [];
        ksort($byPriority);

        return $byPriority ? array_merge(...array_values($byPriority)) : [];
    }
}
