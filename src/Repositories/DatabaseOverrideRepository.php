<?php

declare(strict_types=1);

namespace Kitloom\NovaAdminMenu\Repositories;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Kitloom\NovaAdminMenu\Contracts\MenuOverrideRepository;
use Kitloom\NovaAdminMenu\Support\MenuOverride;

final class DatabaseOverrideRepository implements MenuOverrideRepository
{
    /** @var array<string, MenuOverride>|null */
    private ?array $overrides = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly Cache $cache,
        private readonly string $table = 'nova_menu_overrides',
        private readonly bool $cacheEnabled = true,
        private readonly string $cacheKey = 'nova_menu_overrides',
        private readonly int $cacheTtl = 3600,
    ) {}

    public function find(string $identity): ?MenuOverride
    {
        return $this->all()[$identity] ?? null;
    }

    /**
     * @return array<string, MenuOverride>
     */
    public function all(): array
    {
        if ($this->overrides !== null) {
            return $this->overrides;
        }

        if (! $this->cacheEnabled) {
            return $this->overrides = $this->fetch();
        }

        $rows = $this->cache->remember($this->cacheKey, $this->cacheTtl, fn (): array => $this->fetchRows());

        return $this->overrides = $this->hydrate($rows);
    }

    public function put(string $identity, array $attributes): void
    {
        $this->putMany([$identity => $attributes]);
    }

    public function putMany(array $attributesByIdentity): void
    {
        if ($attributesByIdentity === []) {
            return;
        }

        $now = now();

        $this->connection->transaction(function () use ($attributesByIdentity, $now): void {
            foreach ($attributesByIdentity as $identity => $attributes) {
                $existing = $this->all()[$identity] ?? new MenuOverride($identity);

                $row = $existing->with($attributes)->toArray();
                $row['permissions'] = $row['permissions'] === [] ? null : json_encode($row['permissions']);

                $this->connection->table($this->table)->updateOrInsert(
                    ['identity' => $identity],
                    $row + ['updated_at' => $now, 'created_at' => $now],
                );
            }
        });

        $this->flush();
    }

    public function forget(string $identity): void
    {
        $this->connection->table($this->table)->where('identity', $identity)->delete();

        $this->flush();
    }

    public function clear(): void
    {
        $this->connection->table($this->table)->delete();

        $this->flush();
    }

    public function flush(): void
    {
        $this->overrides = null;

        if ($this->cacheEnabled) {
            $this->cache->forget($this->cacheKey);
        }
    }

    /**
     * @return array<string, MenuOverride>
     */
    private function fetch(): array
    {
        return $this->hydrate($this->fetchRows());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(): array
    {
        try {
            return $this->connection->table($this->table)->get()->map(
                static fn ($row): array => (array) $row
            )->all();
        } catch (QueryException) {
            // The menu has to render even when the migration has not run yet.
            return [];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, MenuOverride>
     */
    private function hydrate(array $rows): array
    {
        $overrides = [];

        foreach ($rows as $row) {
            $identity = (string) $row['identity'];
            $overrides[$identity] = MenuOverride::fromArray($identity, $row);
        }

        return $overrides;
    }
}
