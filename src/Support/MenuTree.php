<?php

declare(strict_types=1);

namespace Kitloom\NovaAdminMenu\Support;

use Laravel\Nova\Menu\MenuCollection;
use Laravel\Nova\Menu\MenuGroup;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;

/**
 * Moving entries between parents.
 *
 * Nova rebuilds the menu from the tools on every request, so a move is an
 * override replayed each time rather than a change to a stored structure. It
 * has to survive the menu changing underneath it: a target that is gone, a
 * target that cannot hold children, or a move that would put a branch inside
 * itself. In all three cases the entry stays where the menu put it.
 */
final class MenuTree
{
    public function __construct(private readonly MenuSignature $signatures) {}

    /**
     * Where the menu itself puts each entry, before any override is applied.
     *
     * @param  array<int, mixed>  $items
     * @return array<string, string|null> identity => parent identity, null at top level
     */
    public function naturalParents(array $items, callable $identityFor): array
    {
        $parents = [];

        $walk = function (array $items, ?string $parent) use (&$walk, $identityFor, &$parents): void {
            foreach ($items as $item) {
                if (! is_object($item)) {
                    continue;
                }

                $identity = $identityFor($item);

                if ($identity !== null) {
                    $parents[$identity] = $parent;
                }

                if ($this->signatures->isNestable($item)) {
                    $walk($item->items->all(), $identity ?? $parent);
                }
            }
        };

        $walk($items, null);

        return $parents;
    }

    /**
     * Apply the stored moves.
     *
     * @param  array<int, mixed>  $items
     * @param  array<string, string|null>  $moves  identity => target parent identity, null for top level
     * @return array<int, mixed>
     */
    public function reparent(array $items, array $moves, callable $identityFor): array
    {
        if ($moves === []) {
            return $items;
        }

        $nodes = $this->index($items, $identityFor);
        $top = $items;

        foreach ($moves as $identity => $target) {
            $node = $nodes[$identity] ?? null;

            if ($node === null || ! $this->targetIsUsable($node, $target, $nodes, $identityFor)) {
                continue;
            }

            $top = $this->detach($top, $node);

            if ($target === null) {
                $top[] = $node;

                continue;
            }

            $parent = $nodes[$target];
            $parent->items = new MenuCollection([...$parent->items->all(), $node]);
        }

        return $top;
    }

    /**
     * @param  array<string, object>  $nodes
     */
    private function targetIsUsable(object $node, ?string $target, array $nodes, callable $identityFor): bool
    {
        if ($target === null) {
            return true;
        }

        $parent = $nodes[$target] ?? null;

        // Gone with the tool that produced it, or hidden from this user.
        if ($parent === null) {
            return false;
        }

        if (! $this->signatures->canAcceptChildren($parent)) {
            return false;
        }

        return $parent !== $node && ! $this->contains($node, $parent, $identityFor);
    }

    /**
     * Is $needle somewhere inside $haystack? Guards a branch being moved into
     * itself, which would take it out of the menu entirely.
     */
    private function contains(object $haystack, object $needle, callable $identityFor): bool
    {
        if (! $this->signatures->isNestable($haystack)) {
            return false;
        }

        foreach ($haystack->items->all() as $child) {
            if (! is_object($child)) {
                continue;
            }

            if ($child === $needle || $this->contains($child, $needle, $identityFor)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<string, object>
     */
    private function index(array $items, callable $identityFor): array
    {
        $nodes = [];

        $walk = function (array $items) use (&$walk, $identityFor, &$nodes): void {
            foreach ($items as $item) {
                if (! is_object($item)) {
                    continue;
                }

                $identity = $identityFor($item);

                if ($identity !== null) {
                    $nodes[$identity] = $item;
                }

                if ($this->signatures->isNestable($item)) {
                    $walk($item->items->all());
                }
            }
        };

        $walk($items);

        return $nodes;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function detach(array $items, object $node): array
    {
        $kept = [];

        foreach ($items as $item) {
            if ($item === $node) {
                continue;
            }

            if (is_object($item) && $this->signatures->isNestable($item)) {
                $item->items = new MenuCollection($this->detach($item->items->all(), $node));
            }

            $kept[] = $item;
        }

        return $kept;
    }

    /**
     * Make a moved branch render the way Nova draws sub-menus.
     *
     * Nova renders the tree recursively, so a MenuSection nested inside
     * another one does appear - but it is styled for the top level: icon
     * slot, full size text, no indent. The result reads as a second root
     * rather than a sub-menu. Nova's own vocabulary for a heading inside a
     * section is MenuGroup, so that is what a nested section becomes:
     *
     *   with children  -> MenuGroup, the small upper-case heading
     *   just a link    -> MenuItem
     *
     * A section carries an icon and may carry a path; a group has neither, so
     * both are dropped on the way down. That is a rendering step only, run
     * after every identity is resolved, so nothing stored is affected.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    public function adaptNesting(array $items, int $depth = 0): array
    {
        $adapted = [];

        foreach ($items as $item) {
            if (! is_object($item)) {
                $adapted[] = $item;

                continue;
            }

            if ($this->signatures->isNestable($item)) {
                $item->items = new MenuCollection($this->adaptNesting($item->items->all(), $depth + 1));
            }

            $adapted[] = $depth > 0 && $item instanceof MenuSection
                ? $this->demote($item)
                : $item;
        }

        return $adapted;
    }

    private function demote(MenuSection $section): MenuGroup|MenuItem
    {
        if ($section->items->isEmpty()) {
            return MenuItem::make($section->name, $section->path);
        }

        $group = MenuGroup::make($section->name, $section->items->all());

        // Carry the folding over. Tools generally make their sections
        // collapsable, and a sub-menu that cannot fold next to ones that can
        // looks like a mistake.
        if ($section->collapsable) {
            $group->collapsable();
        }

        if ($section->collapsedByDefault) {
            $group->collapsedByDefault();
        }

        return $group;
    }
}
