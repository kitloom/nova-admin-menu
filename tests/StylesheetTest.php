<?php

declare(strict_types=1);

namespace Kitloom\NovaAdminMenu\Tests;

use PHPUnit\Framework\TestCase;

/**
 * A Nova tool gets no stylesheet of its own unless it ships one, and Nova's
 * app.css carries only the utilities Nova itself uses. A Tailwind class that
 * is not in either file does nothing at all - no error, no warning, just a
 * style that never arrives. This walks the templates and checks every class
 * is one of the two stylesheets can draw.
 */
class StylesheetTest extends TestCase
{
    /** Utilities whose name carries no dash, so the shape test cannot spot them. */
    private const DASHLESS = [
        'flex', 'block', 'inline', 'hidden', 'shadow', 'border', 'truncate',
        'italic', 'uppercase', 'relative', 'absolute', 'fixed', 'static',
    ];

    public function test_every_class_used_is_one_the_stylesheets_define(): void
    {
        $nova = dirname(__DIR__).'/vendor/laravel/nova/public/app.css';

        if (! file_exists($nova)) {
            $this->markTestSkipped('Nova is not installed, so its stylesheet cannot be read.');
        }

        $css = file_get_contents($nova).file_get_contents(dirname(__DIR__).'/dist/css/entry.css');

        $missing = [];

        foreach ($this->classesUsed() as $class => $files) {
            if (! $this->definedIn($class, $css)) {
                $missing[] = sprintf('%s (%s)', $class, implode(', ', $files));
            }
        }

        $this->assertSame([], $missing, "These classes are not defined anywhere, so they do nothing:\n".implode("\n", $missing));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function classesUsed(): array
    {
        $found = [];

        foreach ($this->templates() as $file) {
            $source = file_get_contents($file);

            $chunks = [];
            preg_match_all('/\bclass="([^"]*)"/', $source, $matches);
            $chunks = $matches[1];

            // Class lists built in code: the strings inside :class="[...]"
            // bindings, and the constants buttons.js composes them from.
            preg_match_all('/:class="(.*?)"/s', $source, $bindings);
            foreach ($bindings[1] as $binding) {
                preg_match_all("/'([^']*)'/", $binding, $literals);
                $chunks = array_merge($chunks, $literals[1]);
            }

            if (str_ends_with($file, 'buttons.js')) {
                preg_match_all("/^\s*'([^']*)'/m", $source, $constants);
                $chunks = array_merge($chunks, $constants[1]);
            }

            foreach ($chunks as $chunk) {
                foreach (preg_split('/\s+/', trim($chunk)) as $token) {
                    if ($this->looksLikeAClass($token)) {
                        $found[$token][] = basename($file);
                    }
                }
            }
        }

        return array_map('array_unique', $found);
    }

    private function looksLikeAClass(string $token): bool
    {
        if ($token === '' || ! preg_match('/^[a-z][a-z0-9:\/\[\]&().\-]*$/', $token)) {
            return false;
        }

        return str_contains($token, '-') || str_contains($token, ':') || in_array($token, self::DASHLESS, true);
    }

    private function definedIn(string $class, string $css): bool
    {
        $escaped = preg_replace('/([:\/\[\]&()!.,%])/', '\\\\$1', $class);

        return (bool) preg_match('/\.'.preg_quote($escaped, '/').'(?![\w-])/', $css);
    }

    /**
     * @return array<int, string>
     */
    private function templates(): array
    {
        $js = dirname(__DIR__).'/resources/js';

        return array_merge(
            glob($js.'/**/*.vue') ?: [],
            glob($js.'/*.vue') ?: [],
            [$js.'/buttons.js'],
        );
    }
}
