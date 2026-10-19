<?php

declare(strict_types=1);

namespace BertanexFlow;

/**
 * Minimal PSR-4 autoloader when Composer's vendor/ is not present.
 */
final class Autoloader
{
    public static function register(string $baseDir, string $namespacePrefix): void
    {
        $prefixLength = strlen($namespacePrefix);

        spl_autoload_register(static function (string $class) use ($baseDir, $namespacePrefix, $prefixLength): void {
            if (strncmp($namespacePrefix, $class, $prefixLength) !== 0) {
                return;
            }

            $relative = substr($class, $prefixLength);
            $path     = $baseDir . '/' . str_replace('\\', '/', $relative) . '.php';

            if (is_readable($path)) {
                require_once $path;
            }
        });
    }
}
