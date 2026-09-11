<?php

declare(strict_types=1);

namespace Kitloom\NovaAdminMenu\Contracts;

use Kitloom\NovaAdminMenu\Support\MenuOverride;

/**
 * Storage for what the admin has changed about the menu.
 *
 * Entries are addressed by identity - the class behind them where one can be
 * found, otherwise their path, otherwise their label. See MenuIdentity.
 */
interface MenuOverrideRepository
{
    public function find(string $identity): ?MenuOverride;

    /**
     * Every stored override, keyed by identity. Implementations are expected
     * to load these in one go.
     *
     * @return array<string, MenuOverride>
     */
    public function all(): array;

    /**
     * Merge these attributes into the entry's override, creating it if needed.
     *
     * @param  array<string, mixed>  $attributes  weight, parent_identity, label, icon, hidden
     */
    public function put(string $identity, array $attributes): void;

    /**
     * Apply many at once - one write where the storage supports it.
     *
     * @param  array<string, array<string, mixed>>  $attributesByIdentity
     */
    public function putMany(array $attributesByIdentity): void;

    public function forget(string $identity): void;

    /**
     * Drop every override, putting the menu back the way the application
     * builds it.
     */
    public function clear(): void;

    public function flush(): void;
}
