<?php

declare(strict_types=1);

namespace Kitloom\NovaMenuWeights\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\Nova\Menu\Menu sort(\Laravel\Nova\Menu\Menu $menu, ?\Illuminate\Http\Request $request = null)
 * @method static array sortItems(iterable $items, ?\Illuminate\Http\Request $request = null)
 * @method static \Kitloom\NovaMenuWeights\MenuWeights prepend(callable|iterable $items)
 * @method static \Kitloom\NovaMenuWeights\MenuWeights reject(callable $filter)
 * @method static \Kitloom\NovaMenuWeights\MenuWeights hidePaths(array $paths)
 * @method static \Kitloom\NovaMenuWeights\MenuWeights authorizeUsing(callable $authorizer)
 * @method static \Kitloom\NovaMenuWeights\MenuWeights permissionsUsing(callable $source)
 * @method static array availablePermissions()
 * @method static ?string parentToStore(string $identity, ?string $submitted, array $naturalParents, array $presentInMenu)
 * @method static int weightForItem(mixed $item, ?\Kitloom\NovaMenuWeights\Support\MenuClassMap $classMap = null)
 * @method static int weightForClass(string $className)
 * @method static int weightForIdentity(string $identity)
 * @method static ?string identityForItem(mixed $item, ?\Kitloom\NovaMenuWeights\Support\MenuClassMap $classMap = null)
 * @method static ?\Kitloom\NovaMenuWeights\Support\MenuOverride overrideFor(string $identity)
 * @method static int defaultWeight()
 * @method static \Kitloom\NovaMenuWeights\Support\MenuEntries entries()
 * @method static \Kitloom\NovaMenuWeights\Support\MenuClassMap classMap(?\Illuminate\Http\Request $request = null)
 * @method static void flush()
 *
 * @see \Kitloom\NovaMenuWeights\MenuWeights
 */
final class MenuWeights extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Kitloom\NovaMenuWeights\MenuWeights::class;
    }
}
