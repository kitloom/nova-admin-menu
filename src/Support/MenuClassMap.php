<?php

declare(strict_types=1);

namespace Kitloom\NovaMenuWeights\Support;

/**
 * Maps a rendered menu entry back to the tool / resource / dashboard class it
 * came from.
 *
 * Two indexes are kept on purpose. The path is stable but not every entry has
 * one (menu groups never do); the label is always there but is translated, so
 * it stops matching the moment the admin switches locale. Looking the path up
 * first and only then falling back to the label keeps weights attached to
 * their entries in both cases.
 */
final class MenuClassMap
{
    /** @var array<string, class-string> */
    private array $byPath = [];

    /** @var array<string, class-string> */
    private array $byLabel = [];

    /**
     * @param  class-string  $className
     */
    public function put(?string $pathSignature, ?string $labelSignature, string $className): void
    {
        if ($pathSignature !== null) {
            $this->byPath[$pathSignature] = $className;
        }

        if ($labelSignature !== null && ! isset($this->byLabel[$labelSignature])) {
            $this->byLabel[$labelSignature] = $className;
        }
    }

    /**
     * @return class-string|null
     */
    public function find(?string $pathSignature, ?string $labelSignature): ?string
    {
        if ($pathSignature !== null && isset($this->byPath[$pathSignature])) {
            return $this->byPath[$pathSignature];
        }

        if ($labelSignature !== null) {
            return $this->byLabel[$labelSignature] ?? null;
        }

        return null;
    }

    public function isEmpty(): bool
    {
        return $this->byPath === [] && $this->byLabel === [];
    }
}
