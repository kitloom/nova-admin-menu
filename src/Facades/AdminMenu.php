<?php

declare(strict_types=1);

namespace Kitloom\NovaAdminMenu\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\Nova\Menu\Menu sort(\Laravel\Nova\Menu\Menu $menu, ?\Illuminate\Http\Request $request = null)
 * @method static array sortItems(iterable $items, ?\Illuminate\Http\Request $request = null)
 * @method static \Kitloom\NovaAdminMenu\AdminMenu prepend(callable|iterable $items)
 * @method static \Kitloom\NovaAdminMenu\AdminMenu reject(callable $filter)
 * @method static \Kitloom\NovaAdminMenu\AdminMenu hidePaths(array $paths)
 * @method static \Kitloom\NovaAdminMenu\AdminMenu authorizeUsing(callable $authorizer)
 * @method static \Kitloom\NovaAdminMenu\AdminMenu permissionsUsing(callable $source)
 * @method static array availablePermissions()
 * @method static ?string parentToStore(string $identity, ?string $submitted, array $naturalParents, array $presentInMenu)
 * @method static int weightForItem(mixed $item, ?\Kitloom\NovaAdminMenu\Support\MenuClassMap $classMap = null)
 * @method static int weightForClass(string $className)
 * @method static int weightForIdentity(string $identity)
 * @method static ?string identityForItem(mixed $item, ?\Kitloom\NovaAdminMenu\Support\MenuClassMap $classMap = null)
 * @method static ?\Kitloom\NovaAdminMenu\Support\MenuOverride overrideFor(string $identity)
 * @method static int defaultWeight()
 * @method static \Kitloom\NovaAdminMenu\Support\MenuEntries entries()
 * @method static \Kitloom\NovaAdminMenu\Support\MenuClassMap classMap(?\Illuminate\Http\Request $request = null)
 * @method static void flush()
 *
 * @see \Kitloom\NovaAdminMenu\AdminMenu
 */
final class AdminMenu extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Kitloom\NovaAdminMenu\AdminMenu::class;
    }
}
