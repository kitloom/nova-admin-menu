<?php

declare(strict_types=1);

namespace Kitloom\NovaAdminMenu\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Nova\Menu\MenuGroup;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuList;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;

/**
 * Builds the signatures used to recognise a menu entry, and the map from those
 * signatures to the classes that produced them.
 */
final class MenuSignature
{
    /**
     * Nova hands the menu callback fully built entries with no reference back
     * to their origin, so the map is built by asking every tool, resource and
     * dashboard what it would have rendered.
     */
    public function classMap(Request $request): MenuClassMap
    {
        $map = new MenuClassMap;

        foreach (Nova::availableTools($request) as $tool) {
            foreach ($this->normalize($tool->menu($request)) as $entry) {
                $map->put($this->pathSignature($entry), $this->labelSignature($entry), $tool::class);
            }
        }

        foreach (Nova::resourcesForNavigation($request) as $resourceClass) {
            $entry = MenuItem::resource($resourceClass);
            $map->put($this->pathSignature($entry), $this->labelSignature($entry), $resourceClass);
        }

        foreach (Nova::availableDashboards($request) as $dashboard) {
            // A dashboard renders as a section when it is the only one and as
            // an item otherwise, so both shapes are registered.
            foreach ([MenuItem::dashboard($dashboard::class), MenuSection::dashboard($dashboard::class)] as $entry) {
                $map->put($this->pathSignature($entry), $this->labelSignature($entry), $dashboard::class);
            }
        }

        return $map;
    }

    public function pathSignature(object $entry): ?string
    {
        if (! $this->isWeighable($entry)) {
            return null;
        }

        $path = $entry->path ?? null;

        if ($path === null || $path === '') {
            return null;
        }

        return $this->type($entry).':'.$path;
    }

    public function labelSignature(object $entry): ?string
    {
        if (! $this->isWeighable($entry)) {
            return null;
        }

        $name = (string) $entry->name;

        return $name === '' ? null : $this->type($entry).'#'.$name;
    }

    /**
     * A MenuList carries neither name nor path, so it can never hold a weight
     * of its own - only its children take part in the sorting.
     */
    public function isWeighable(object $entry): bool
    {
        return $entry instanceof MenuItem
            || $entry instanceof MenuSection
            || $entry instanceof MenuGroup;
    }

    /**
     * Can this entry be given children?
     *
     * Narrower than isNestable on purpose: a MenuList is walked into, but its
     * Vue component renders MenuItem children and nothing else, so dropping a
     * section into one would silently produce a blank.
     */
    public function canAcceptChildren(object $entry): bool
    {
        return $entry instanceof MenuSection || $entry instanceof MenuGroup;
    }

    public function isNestable(object $entry): bool
    {
        return $entry instanceof MenuSection
            || $entry instanceof MenuGroup
            || $entry instanceof MenuList;
    }

    /**
     * @return array<int, MenuGroup|MenuItem|MenuSection>
     */
    private function normalize(mixed $menu): array
    {
        return array_values(array_filter(
            Arr::wrap($menu),
            fn ($entry): bool => is_object($entry) && $this->isWeighable($entry),
        ));
    }

    private function type(object $entry): string
    {
        return match (true) {
            $entry instanceof MenuSection => 'section',
            $entry instanceof MenuGroup => 'group',
            default => 'item',
        };
    }

    /**
     * Label a tool shows in the menu, used by settings UIs.
     */
    public function toolLabel(Tool $tool, Request $request): string
    {
        $first = $this->normalize($tool->menu($request))[0] ?? null;

        return $first === null ? class_basename($tool::class) : (string) $first->name;
    }
}
