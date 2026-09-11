<?php

declare(strict_types=1);

namespace Kitloom\NovaAdminMenu;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Kitloom\NovaAdminMenu\Contracts\MenuOverrideRepository;
use Kitloom\NovaAdminMenu\Support\CustomEntries;
use Kitloom\NovaAdminMenu\Support\MenuClassMap;
use Kitloom\NovaAdminMenu\Support\MenuEntries;
use Kitloom\NovaAdminMenu\Support\MenuIdentity;
use Kitloom\NovaAdminMenu\Support\MenuOverride;
use Kitloom\NovaAdminMenu\Support\MenuSignature;
use Kitloom\NovaAdminMenu\Support\MenuTree;
use Laravel\Nova\Menu\Menu;
use Laravel\Nova\Menu\MenuCollection;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;

final class AdminMenu
{
    private ?MenuClassMap $classMap = null;

    /** @var array<int, callable|iterable<mixed>> */
    private array $prepends = [];

    /** @var array<int, callable(mixed, Request): bool> */
    private array $rejects = [];

    /** @var (callable(Request, array<int, string>): bool)|null */
    private $authorizer = null;

    /** @var (callable(): array<int, string>)|null */
    private $permissionSource = null;

    public function __construct(
        private readonly MenuOverrideRepository $repository,
        private readonly MenuSignature $signatures,
        private readonly MenuEntries $entries,
        private readonly MenuTree $tree,
        private readonly CustomEntries $custom,
        private readonly int $defaultWeight = 500,
        private readonly bool $allowReparenting = true,
    ) {}

    /**
     * Sort a Nova menu in place, nested entries included.
     */
    public function sort(Menu $menu, ?Request $request = null): Menu
    {
        $request ??= request();

        $menu->items = new MenuCollection(
            $this->prepare($menu->items->all(), $request, applyHidden: true, applyOverrides: true)
        );

        return $menu;
    }

    /**
     * The menu as the sidebar would show it, but with the entries the admin
     * has hidden left in - the screen that edits them has to list them, or
     * there would be no way to bring one back.
     *
     * @return array<int, mixed>
     */
    public function itemsIncludingHidden(Request $request): array
    {
        // Labels are deliberately left alone here: an entry with no path is
        // identified by its label, and rewriting it would make the next lookup
        // miss. The screen shows the original and the override side by side.
        return $this->prepare(
            Nova::defaultMainMenu($request)->items->all(),
            $request,
            applyHidden: false,
            applyOverrides: false,
        );
    }

    /**
     * Where the menu itself puts each entry, ignoring any stored move. The
     * editing screen needs it to tell a real move from the natural position -
     * only the difference is worth storing, or the structure would freeze and
     * fight every future change to the menu.
     *
     * @return array<string, string|null>
     */
    public function naturalParents(Request $request): array
    {
        $items = array_merge(
            $this->resolvePrepended($request),
            $this->custom->build(),
            $this->withoutRejected(Nova::defaultMainMenu($request)->items->all(), $request),
        );

        return $this->tree->naturalParents($items, fn ($item) => $this->identityForItem($item));
    }

    /**
     * @return array<string, string|null>
     */
    private function moves(): array
    {
        $moves = [];

        foreach ($this->repository->all() as $identity => $override) {
            if ($override->parentIdentity === null) {
                continue;
            }

            $moves[$identity] = $override->parentIdentity === MenuIdentity::TOP_LEVEL
                ? null
                : $override->parentIdentity;
        }

        return $moves;
    }

    /**
     * Is the entry somewhere the menu would not have put it?
     *
     * A stored parent alone does not mean so: it can be left over from a move
     * that was undone elsewhere, or from a menu that has since changed. Only a
     * difference from the natural parent is worth reporting.
     *
     * @param  array<string, string|null>  $naturalParents
     */
    public function isMovedFromNatural(string $identity, array $naturalParents): bool
    {
        $stored = $this->repository->find($identity)?->parentIdentity;

        if ($stored === null) {
            return false;
        }

        return ($stored === MenuIdentity::TOP_LEVEL ? null : $stored)
            !== ($naturalParents[$identity] ?? null);
    }

    /**
     * Decide whether the viewer may see an entry carrying these permission
     * names. The names mean nothing to this package - one application checks
     * them against a role table, another against gates - so the application
     * says what they mean.
     *
     * @param  callable(Request, array<int, string>): bool  $authorizer
     */
    public function authorizeUsing(callable $authorizer): self
    {
        $this->authorizer = $authorizer;

        return $this;
    }

    /**
     * Names to suggest on the editing screen.
     *
     * @param  callable(): array<int, string>  $source
     */
    public function permissionsUsing(callable $source): self
    {
        $this->permissionSource = $source;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function availablePermissions(): array
    {
        return $this->permissionSource === null ? [] : array_values(($this->permissionSource)());
    }

    /**
     * @return array<int, string>
     */
    public function permissionsFor(mixed $item): array
    {
        $identity = $this->identityForItem($item);

        return $identity === null ? [] : ($this->repository->find($identity)?->permissions ?? []);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function viewerMaySee(array $permissions, Request $request): bool
    {
        if ($permissions === []) {
            return true;
        }

        if ($this->authorizer !== null) {
            return (bool) ($this->authorizer)($request, $permissions);
        }

        $user = $request->user();

        // Nothing was configured: fall back to the framework's own gates,
        // which is what a stock application - and Spatie's permissions - use.
        return $user !== null && Gate::forUser($user)->any($permissions);
    }

    /**
     * Drop entries the viewer has no permission for.
     *
     * This only ever removes. Attaching a canSee callback instead would
     * replace whatever the tool already set - AuthorizedToSee::canSee
     * overwrites - and a section closed to a role would quietly open up.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function withoutUnauthorized(array $items, Request $request): array
    {
        $kept = [];

        foreach ($items as $item) {
            if (is_object($item) && ! $this->viewerMaySee($this->permissionsFor($item), $request)) {
                continue;
            }

            if (is_object($item) && $this->signatures->isNestable($item)) {
                $item->items = new MenuCollection($this->withoutUnauthorized($item->items->all(), $request));
            }

            $kept[] = $item;
        }

        return $kept;
    }

    /**
     * What to store as an entry's parent after a save.
     *
     * Only a parent that differs from where the menu itself puts the entry is
     * worth keeping: storing the natural one would freeze the structure and
     * fight every later change to the menu.
     *
     * @param  array<string, string|null>  $naturalParents
     * @param  array<string, true>  $presentInMenu  identities the menu currently contains
     */
    public function parentToStore(
        string $identity,
        ?string $submitted,
        array $naturalParents,
        array $presentInMenu,
    ): ?string {
        $stored = $this->repository->find($identity)?->parentIdentity;

        // A move whose target is missing from this menu was never applied, so
        // the entry is showing at its natural place and comes back that way.
        // Reading that as "put back" would destroy the move: the target may be
        // a tool switched off for now, or a section this admin cannot see.
        if ($stored !== null && $stored !== MenuIdentity::TOP_LEVEL && ! isset($presentInMenu[$stored])) {
            return $stored;
        }

        if ($submitted !== null && ! isset($presentInMenu[$submitted])) {
            return null;
        }

        if ($submitted === ($naturalParents[$identity] ?? null)) {
            return null;
        }

        return $submitted ?? MenuIdentity::TOP_LEVEL;
    }

    public function isHidden(mixed $item): bool
    {
        $identity = $this->identityForItem($item);

        return $identity !== null && ($this->repository->find($identity)?->hidden ?? false);
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function prepare(array $items, Request $request, bool $applyHidden, bool $applyOverrides): array
    {
        $items = array_merge(
            $this->resolvePrepended($request),
            $this->custom->build(),
            $this->withoutRejected($items, $request),
        );

        if ($this->allowReparenting) {
            $items = $this->tree->reparent($items, $this->moves(), fn ($item) => $this->identityForItem($item));
        }

        if ($applyHidden) {
            $items = $this->withoutHidden($items);
            $items = $this->withoutUnauthorized($items, $request);
        }

        $items = $this->sortItems($items, $request);

        if (! $applyOverrides) {
            return $items;
        }

        // Last, once every identity has been resolved: renaming an entry
        // before that would change what it is identified by, and re-typing a
        // nested section would do the same.
        return $this->tree->adaptNesting($this->applyOverrides($items));
    }

    /**
     * Put the stored label and icon on the entries.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function applyOverrides(array $items): array
    {
        foreach ($items as $item) {
            if (! is_object($item)) {
                continue;
            }

            $identity = $this->identityForItem($item);
            $override = $identity === null ? null : $this->repository->find($identity);

            if ($override?->label !== null) {
                $item->name = $override->label;
            }

            // Only a MenuSection has an icon - items, groups and lists render
            // without one, so there is nothing to override on them.
            if ($override?->icon !== null && $item instanceof MenuSection) {
                $item->icon($override->icon);
            }

            if ($this->signatures->isNestable($item)) {
                $this->applyOverrides($item->items->all());
            }
        }

        return $items;
    }

    /**
     * Whether an icon can be put on this entry at all.
     */
    public function supportsIcon(mixed $item): bool
    {
        return $item instanceof MenuSection;
    }

    /**
     * Drops hidden entries at every level. A hidden section takes its children
     * with it, which is what dropping the branch means.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function withoutHidden(array $items): array
    {
        $kept = [];

        foreach ($items as $item) {
            if ($this->isHidden($item)) {
                continue;
            }

            if (is_object($item) && $this->signatures->isNestable($item)) {
                $item->items = new MenuCollection($this->withoutHidden($item->items->all()));
            }

            $kept[] = $item;
        }

        return $kept;
    }

    /**
     * Add entries built elsewhere to the menu before it is sorted. Accepts a
     * list, or a callable returning one so it can be built per request.
     *
     * @param  callable(Request): iterable<mixed>|iterable<mixed>  $items
     */
    public function prepend(callable|iterable $items): self
    {
        $this->prepends[] = $items;

        return $this;
    }

    /**
     * Drop entries before sorting.
     *
     * @param  callable(mixed, Request): bool  $filter  true removes the entry
     */
    public function reject(callable $filter): self
    {
        $this->rejects[] = $filter;

        return $this;
    }

    /**
     * Drop the entries pointing at any of these paths — the usual way to hide
     * Nova's default dashboard.
     *
     * @param  array<int, string>  $paths
     */
    public function hidePaths(array $paths): self
    {
        return $this->reject(static fn ($item): bool => in_array($item->path ?? null, $paths, true));
    }

    /**
     * @return array<int, mixed>
     */
    private function resolvePrepended(Request $request): array
    {
        $items = [];

        foreach ($this->prepends as $prepend) {
            $resolved = is_callable($prepend) ? $prepend($request) : $prepend;

            $items = array_merge($items, is_array($resolved) ? $resolved : iterator_to_array($resolved));
        }

        return $items;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function withoutRejected(array $items, Request $request): array
    {
        if ($this->rejects === []) {
            return $items;
        }

        return array_values(array_filter($items, function ($item) use ($request): bool {
            foreach ($this->rejects as $reject) {
                if ($reject($item, $request)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Sort a plain list of menu entries. Use this when entries built elsewhere
     * have to be merged into the menu before sorting.
     *
     * @param  iterable<mixed>  $items
     * @return array<int, mixed>
     */
    public function sortItems(iterable $items, ?Request $request = null): array
    {
        $classMap = $this->classMap($request);

        return Collection::make($items)
            ->map(function ($item) use ($request) {
                if (is_object($item) && $this->signatures->isNestable($item)) {
                    $item->items = new MenuCollection($this->sortItems($item->items->all(), $request));
                }

                return $item;
            })
            // Equal weights keep their original order: PHP sorts are stable.
            ->sortByDesc(fn ($item): int => $this->weightForItem($item, $classMap))
            ->values()
            ->all();
    }

    public function weightForItem(mixed $item, ?MenuClassMap $classMap = null): int
    {
        $identity = $this->identityForItem($item, $classMap);

        return $identity === null ? $this->defaultWeight : $this->weightForIdentity($identity);
    }

    /**
     * How the entry is named in storage: by its class where one can be found,
     * otherwise by its path, otherwise by its label. Null for entries that can
     * hold no weight at all, such as a MenuList.
     */
    public function identityForItem(mixed $item, ?MenuClassMap $classMap = null): ?string
    {
        if (! is_object($item) || ! $this->signatures->isWeighable($item)) {
            return null;
        }

        // An entry this package built knows its own row; nothing about the
        // object itself would lead back to it.
        $custom = $this->custom->identityOf($item);

        if ($custom !== null) {
            return $custom;
        }

        $classMap ??= $this->classMap();

        $className = $classMap->find(
            $this->signatures->pathSignature($item),
            $this->signatures->labelSignature($item),
        );

        if ($className !== null) {
            return $className;
        }

        $path = $item->path ?? null;

        if (is_string($path) && $path !== '') {
            return MenuIdentity::forPath($path);
        }

        $label = (string) ($item->name ?? '');

        return $label === '' ? null : MenuIdentity::forLabel($label);
    }

    public function weightForIdentity(string $identity): int
    {
        return $this->repository->find($identity)?->weight ?? $this->defaultWeight;
    }

    /**
     * Everything stored about an entry, or null when nothing has been changed.
     */
    public function overrideFor(string $identity): ?MenuOverride
    {
        return $this->repository->find($identity);
    }

    /**
     * @param  class-string  $className
     */
    public function weightForClass(string $className): int
    {
        return $this->weightForIdentity($className);
    }

    public function defaultWeight(): int
    {
        return $this->defaultWeight;
    }

    public function entries(): MenuEntries
    {
        return $this->entries;
    }

    public function classMap(?Request $request = null): MenuClassMap
    {
        return $this->classMap ??= $this->signatures->classMap($request ?? request());
    }

    /**
     * Supply the class map instead of letting it be discovered. Useful for
     * tests and for applications that cache the map themselves.
     */
    public function usingClassMap(MenuClassMap $classMap): self
    {
        $this->classMap = $classMap;

        return $this;
    }

    /**
     * Forget the memoised class map and any cached weights.
     */
    public function flush(): void
    {
        $this->classMap = null;
        $this->custom->forget();
        $this->repository->flush();
    }
}
