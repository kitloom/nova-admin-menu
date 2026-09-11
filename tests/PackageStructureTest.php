<?php

declare(strict_types=1);

namespace Kitloom\NovaAdminMenu\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guards the package against a rename that misses a file.
 *
 * A namespace that no longer matches the psr-4 prefix produces no error until
 * something tries to autoload the class - which, for a service provider, is
 * on boot, in the host application, long after the tests went green.
 */
final class PackageStructureTest extends TestCase
{
    private const PREFIX = 'Kitloom\\NovaAdminMenu';

    public function test_every_source_file_declares_the_namespace_its_path_implies(): void
    {
        foreach ($this->sourceFiles() as $path => $relative) {
            $contents = (string) file_get_contents($path);

            preg_match('/^namespace\s+([^;]+);/m', $contents, $matches);

            $expected = rtrim(self::PREFIX.'\\'.str_replace('/', '\\', dirname($relative)), '\\.');

            $this->assertSame(
                $expected,
                trim($matches[1] ?? ''),
                "{$relative} declares the wrong namespace",
            );
        }
    }

    public function test_every_source_file_can_be_autoloaded(): void
    {
        foreach ($this->sourceFiles() as $relative) {
            $class = self::PREFIX.'\\'.str_replace(['/', '.php'], ['\\', ''], $relative);

            $this->assertTrue(
                class_exists($class) || interface_exists($class) || trait_exists($class),
                "{$class} cannot be autoloaded",
            );
        }
    }

    /**
     * @return array<string, string> absolute path => path relative to src/
     */
    private function sourceFiles(): array
    {
        $src = dirname(__DIR__).'/src';
        $files = [];

        /** @var iterable<\SplFileInfo> $iterator */
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[$file->getPathname()] = ltrim(str_replace($src, '', $file->getPathname()), '/');
            }
        }

        ksort($files);

        return $files;
    }
}
