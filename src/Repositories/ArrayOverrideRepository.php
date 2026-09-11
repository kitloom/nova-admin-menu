<?php

declare(strict_types=1);

namespace Kitloom\NovaAdminMenu\Repositories;

use Kitloom\NovaAdminMenu\Contracts\MenuOverrideRepository;
use Kitloom\NovaAdminMenu\Support\MenuOverride;

/**
 * In-memory repository, for tests and for a config-driven setup.
 */
final class ArrayOverrideRepository implements MenuOverrideRepository
{
    /** @var array<string, MenuOverride> */
    private array $overrides = [];

    /**
     * @param  array<string, array<string, mixed>|int>  $overrides  attributes, or a bare weight, keyed by identity
     */
    public function __construct(array $overrides = [])
    {
        foreach ($overrides as $identity => $attributes) {
            $this->put($identity, is_array($attributes) ? $attributes : ['weight' => $attributes]);
        }
    }

    public function find(string $identity): ?MenuOverride
    {
        return $this->overrides[$identity] ?? null;
    }

    public function all(): array
    {
        return $this->overrides;
    }

    public function put(string $identity, array $attributes): void
    {
        $existing = $this->overrides[$identity] ?? new MenuOverride($identity);

        $this->overrides[$identity] = $existing->with($attributes);
    }

    public function putMany(array $attributesByIdentity): void
    {
        foreach ($attributesByIdentity as $identity => $attributes) {
            $this->put($identity, $attributes);
        }
    }

    public function forget(string $identity): void
    {
        unset($this->overrides[$identity]);
    }

    public function clear(): void
    {
        $this->overrides = [];
    }

    public function flush(): void
    {
        //
    }
}
