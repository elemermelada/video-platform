<?php

declare(strict_types=1);

namespace VideoPlatform\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Base class giving each test a throwaway metadata directory.
 */
abstract class TempDirTestCase extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'video-platform-test-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->dir);
    }

    protected function write(string $name, string $contents): string
    {
        $path = $this->dir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $contents);

        return $path;
    }

    protected function read(string $name): string
    {
        return (string) file_get_contents($this->dir . DIRECTORY_SEPARATOR . $name);
    }
}
