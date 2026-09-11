<?php

declare(strict_types=1);

namespace Kitloom\NovaMenuWeights\Tests;

use Illuminate\Http\Request;
use Kitloom\NovaMenuWeights\MenuWeights;
use Kitloom\NovaMenuWeights\Repositories\ArrayOverrideRepository;
use Kitloom\NovaMenuWeights\Support\MenuClassMap;
use Kitloom\NovaMenuWeights\Support\MenuEntries;
use Kitloom\NovaMenuWeights\Support\MenuIdentity;
use Kitloom\NovaMenuWeights\Support\MenuSignature;
use Kitloom\NovaMenuWeights\Support\MenuTree;
use Laravel\Nova\Menu\Menu;
use Laravel\Nova\Menu\MenuGroup;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuList;
use Laravel\Nova\Menu\MenuSection;
use PHPUnit\Framework\TestCase;

final class MenuWeightsTest extends TestCase
{
    public function test_it_sorts_entries_by_weight_descending(): void
    {
        $map = new MenuClassMap;
        $map->put('item:/resources/posts', 'item#Posts', 'App\Nova\Post');
        $map->put('item:/resources/users', 'item#Users', 'App\Nova\User');

        $sorted = $this->menuWeights([
            'App\Nova\Post' => 100,
            'App\Nova\User' => 900,
        ], $map)->sortItems([
            MenuItem::make('Posts', '/resources/posts'),
            MenuItem::make('Users', '/resources/users'),
        ]);

        $this->assertSame(['Users', 'Posts'], array_map(fn ($item) => (string) $item->name, $sorted));
    }

    public function test_unknown_entries_fall_back_to_the_default_weight_and_keep_their_order(): void
    {
        $map = new MenuClassMap;
        $map->put('item:/resources/posts', 'item#Posts', 'App\Nova\Post');

        $sorted = $this->menuWeights(['App\Nova\Post' => 500], $map)->sortItems([
            MenuItem::make('First', '/first'),
            MenuItem::make('Posts', '/resources/posts'),
            MenuItem::make('Second', '/second'),
        ]);

        $this->assertSame(['First', 'Posts', 'Second'], array_map(fn ($item) => (string) $item->name, $sorted));
    }

    public function test_it_sorts_nested_entries(): void
    {
        $map = new MenuClassMap;
        $map->put('item:/a', 'item#A', 'App\Nova\A');
        $map->put('item:/b', 'item#B', 'App\Nova\B');

        $section = MenuSection::make('Content', [
            new MenuList([
                MenuItem::make('A', '/a'),
                MenuItem::make('B', '/b'),
            ]),
        ]);

        $sorted = $this->menuWeights(['App\Nova\A' => 10, 'App\Nova\B' => 20], $map)
            ->sortItems([$section]);

        $names = array_map(fn ($item) => (string) $item->name, $sorted[0]->items->all()[0]->items->all());

        $this->assertSame(['B', 'A'], $names);
    }

    public function test_a_group_is_matched_by_its_label_because_it_has_no_path(): void
    {
        $map = new MenuClassMap;
        $map->put(null, 'group#Reports', 'App\Nova\ReportsTool');

        $weights = $this->menuWeights(['App\Nova\ReportsTool' => 800], $map);

        $this->assertSame(800, $weights->weightForItem(MenuGroup::make('Reports')));
    }

    public function test_the_path_wins_over_a_translated_label(): void
    {
        $map = new MenuClassMap;
        $map->put('item:/resources/posts', 'item#Posts', 'App\Nova\Post');

        $weights = $this->menuWeights(['App\Nova\Post' => 700], $map);

        // Same entry rendered in another locale: the label changed, the path did not.
        $this->assertSame(700, $weights->weightForItem(MenuItem::make('Записи', '/resources/posts')));
    }

    public function test_hidden_paths_are_dropped_before_sorting(): void
    {
        $weights = $this->menuWeights([], new MenuClassMap);
        $weights->hidePaths(['/dashboards/main']);

        $menu = Menu::make([
            MenuItem::make('Dashboard', '/dashboards/main'),
            MenuItem::make('Posts', '/resources/posts'),
        ]);

        $sorted = $weights->sort($menu, new Request)->items->all();

        $this->assertSame(['Posts'], array_map(fn ($item) => (string) $item->name, $sorted));
    }

    public function test_prepended_entries_take_part_in_the_sorting(): void
    {
        $map = new MenuClassMap;
        $map->put('item:/a', 'item#A', 'App\Nova\A');
        $map->put('item:/b', 'item#B', 'App\Nova\B');

        $weights = $this->menuWeights(['App\Nova\A' => 10, 'App\Nova\B' => 900], $map);
        $weights->prepend(fn (): array => [MenuItem::make('B', '/b')]);

        $menu = Menu::make([MenuItem::make('A', '/a')]);

        $sorted = $weights->sort($menu, new Request)->items->all();

        $this->assertSame(['B', 'A'], array_map(fn ($item) => (string) $item->name, $sorted));
    }

    public function test_an_entry_without_a_class_is_weighted_by_its_path(): void
    {
        $identity = MenuIdentity::forPath('/resources/vendors');

        $weights = $this->menuWeights([$identity => 900], new MenuClassMap);

        $this->assertSame($identity, $weights->identityForItem(MenuItem::make('Vendors', '/resources/vendors')));
        $this->assertSame(900, $weights->weightForItem(MenuItem::make('Vendors', '/resources/vendors')));
    }

    public function test_an_entry_with_neither_class_nor_path_falls_back_to_its_label(): void
    {
        $identity = MenuIdentity::forLabel('Instant Indexing');

        $weights = $this->menuWeights([$identity => 700], new MenuClassMap);

        $this->assertSame($identity, $weights->identityForItem(MenuGroup::make('Instant Indexing')));
        $this->assertSame(700, $weights->weightForItem(MenuGroup::make('Instant Indexing')));
    }

    public function test_nested_entries_are_sorted_by_their_path_weights(): void
    {
        $weights = $this->menuWeights([
            MenuIdentity::forPath('/a') => 10,
            MenuIdentity::forPath('/b') => 900,
        ], new MenuClassMap);

        $section = MenuSection::make('Content', [
            MenuItem::make('A', '/a'),
            MenuItem::make('B', '/b'),
        ]);

        $sorted = $weights->sortItems([$section]);
        $names = array_map(fn ($item) => (string) $item->name, $sorted[0]->items->all());

        $this->assertSame(['B', 'A'], $names);
    }

    public function test_a_hidden_entry_is_dropped_from_the_menu(): void
    {
        $map = new MenuClassMap;
        $map->put('item:/a', 'item#A', 'App\Nova\A');

        $weights = $this->menuWeights([
            'App\Nova\A' => ['hidden' => true],
        ], $map);

        $menu = Menu::make([
            MenuItem::make('A', '/a'),
            MenuItem::make('B', '/b'),
        ]);

        $sorted = $weights->sort($menu, new Request)->items->all();

        $this->assertSame(['B'], array_map(fn ($item) => (string) $item->name, $sorted));
    }

    public function test_hiding_a_section_hides_its_children_with_it(): void
    {
        $weights = $this->menuWeights([
            MenuIdentity::forLabel('Reports') => ['hidden' => true],
        ], new MenuClassMap);

        $menu = Menu::make([
            MenuSection::make('Reports', [MenuItem::make('Daily', '/daily')]),
            MenuItem::make('Posts', '/resources/posts'),
        ]);

        $sorted = $weights->sort($menu, new Request)->items->all();

        $this->assertSame(['Posts'], array_map(fn ($item) => (string) $item->name, $sorted));
    }

    public function test_a_hidden_nested_entry_leaves_its_siblings_alone(): void
    {
        $weights = $this->menuWeights([
            MenuIdentity::forPath('/a') => ['hidden' => true],
        ], new MenuClassMap);

        $menu = Menu::make([
            MenuSection::make('Content', [
                MenuItem::make('A', '/a'),
                MenuItem::make('B', '/b'),
            ]),
        ]);

        $sorted = $weights->sort($menu, new Request)->items->all();
        $names = array_map(fn ($item) => (string) $item->name, $sorted[0]->items->all());

        $this->assertSame(['B'], $names);
    }

    public function test_a_stored_label_replaces_the_one_the_menu_gives(): void
    {
        $map = new MenuClassMap;
        $map->put('item:/a', 'item#A', 'App\Nova\A');

        $weights = $this->menuWeights([
            'App\Nova\A' => ['label' => 'Renamed'],
        ], $map);

        $sorted = $weights->sort(Menu::make([MenuItem::make('A', '/a')]), new Request)->items->all();

        $this->assertSame('Renamed', (string) $sorted[0]->name);
    }

    public function test_renaming_an_entry_identified_by_its_label_still_finds_it_next_time(): void
    {
        // The trap this guards: applying the override before resolving the
        // identity would make the entry look up menu_label:Renamed on the
        // next request and lose everything stored against it.
        $identity = MenuIdentity::forLabel('Reports');

        $weights = $this->menuWeights([
            $identity => ['label' => 'Renamed', 'weight' => 900],
        ], new MenuClassMap);

        foreach ([1, 2] as $_) {
            $sorted = $weights->sort(Menu::make([MenuGroup::make('Reports')]), new Request)->items->all();
            $this->assertSame('Renamed', (string) $sorted[0]->name);
        }
    }

    public function test_an_icon_is_only_put_on_a_section(): void
    {
        $weights = $this->menuWeights([
            MenuIdentity::forLabel('Reports') => ['icon' => 'chart-bar'],
            MenuIdentity::forPath('/a') => ['icon' => 'chart-bar'],
        ], new MenuClassMap);

        $sorted = $weights->sort(
            Menu::make([MenuSection::make('Reports'), MenuItem::make('A', '/a')]),
            new Request
        )->items->all();

        $section = $sorted[0] instanceof MenuSection ? $sorted[0] : $sorted[1];

        $this->assertSame('chart-bar', $section->icon);
        $this->assertFalse($weights->supportsIcon(MenuItem::make('A', '/a')));
    }

    public function test_an_entry_can_be_moved_to_the_top_level(): void
    {
        $weights = $this->menuWeights([
            MenuIdentity::forPath('/a') => ['parent_identity' => MenuIdentity::TOP_LEVEL, 'weight' => 900],
        ], new MenuClassMap);

        $menu = Menu::make([
            MenuSection::make('Content', [MenuItem::make('A', '/a'), MenuItem::make('B', '/b')]),
        ]);

        $sorted = $weights->sort($menu, new Request)->items->all();

        $this->assertSame(['A', 'Content'], array_map(fn ($i) => (string) $i->name, $sorted));
        $this->assertSame(['B'], array_map(fn ($i) => (string) $i->name, $sorted[1]->items->all()));
    }

    public function test_an_entry_can_be_moved_into_another_section(): void
    {
        $weights = $this->menuWeights([
            MenuIdentity::forPath('/a') => ['parent_identity' => MenuIdentity::forLabel('Other')],
        ], new MenuClassMap);

        $menu = Menu::make([
            MenuSection::make('Content', [MenuItem::make('A', '/a')]),
            MenuSection::make('Other', [MenuItem::make('B', '/b')]),
        ]);

        $sorted = $weights->sort($menu, new Request)->items->all();
        $other = array_values(array_filter($sorted, fn ($i) => (string) $i->name === 'Other'))[0];

        $this->assertSame(['B', 'A'], array_map(fn ($i) => (string) $i->name, $other->items->all()));
    }

    public function test_a_move_to_a_target_that_is_gone_leaves_the_entry_alone(): void
    {
        $weights = $this->menuWeights([
            MenuIdentity::forPath('/a') => ['parent_identity' => MenuIdentity::forLabel('Removed tool')],
        ], new MenuClassMap);

        $menu = Menu::make([MenuSection::make('Content', [MenuItem::make('A', '/a')])]);

        $sorted = $weights->sort($menu, new Request)->items->all();

        $this->assertSame(['A'], array_map(fn ($i) => (string) $i->name, $sorted[0]->items->all()));
    }

    public function test_an_entry_cannot_be_moved_into_something_that_holds_no_children(): void
    {
        $weights = $this->menuWeights([
            MenuIdentity::forPath('/a') => ['parent_identity' => MenuIdentity::forPath('/b')],
        ], new MenuClassMap);

        $menu = Menu::make([
            MenuSection::make('Content', [MenuItem::make('A', '/a'), MenuItem::make('B', '/b')]),
        ]);

        $sorted = $weights->sort($menu, new Request)->items->all();

        $this->assertSame(['A', 'B'], array_map(fn ($i) => (string) $i->name, $sorted[0]->items->all()));
    }

    public function test_a_section_cannot_be_moved_inside_itself(): void
    {
        $weights = $this->menuWeights([
            MenuIdentity::forLabel('Content') => ['parent_identity' => MenuIdentity::forLabel('Inner')],
        ], new MenuClassMap);

        $menu = Menu::make([
            MenuSection::make('Content', [MenuSection::make('Inner', [MenuItem::make('A', '/a')])]),
        ]);

        $sorted = $weights->sort($menu, new Request)->items->all();

        $this->assertSame(['Content'], array_map(fn ($i) => (string) $i->name, $sorted));
        $this->assertSame(['Inner'], array_map(fn ($i) => (string) $i->name, $sorted[0]->items->all()));
    }

    public function test_a_section_nested_inside_another_renders_as_a_group(): void
    {
        $weights = $this->menuWeights([
            MenuIdentity::forLabel('Games') => ['parent_identity' => MenuIdentity::forLabel('Content')],
        ], new MenuClassMap);

        $menu = Menu::make([
            MenuSection::make('Content', [MenuItem::make('A', '/a')]),
            MenuSection::make('Games', [MenuItem::make('Vendors', '/vendors')])->icon('puzzle-piece'),
        ]);

        $sorted = $weights->sort($menu, new Request)->items->all();
        $content = $sorted[0];
        $nested = $content->items->all()[1];

        // A nested MenuSection renders with top level styling and reads as a
        // second root; MenuGroup is Nova's heading inside a section.
        $this->assertInstanceOf(MenuGroup::class, $nested);
        $this->assertSame('Games', (string) $nested->name);
        $this->assertSame(['Vendors'], array_map(fn ($i) => (string) $i->name, $nested->items->all()));
    }

    public function test_clearing_drops_every_override(): void
    {
        $repository = new ArrayOverrideRepository([
            'App\Nova\A' => ['weight' => 900, 'hidden' => true],
            'App\Nova\B' => ['label' => 'Renamed'],
        ]);

        $this->assertCount(2, $repository->all());

        $repository->clear();

        $this->assertSame([], $repository->all());
        $this->assertNull($repository->find('App\Nova\A'));
    }

    public function test_the_menu_falls_back_to_its_own_order_once_cleared(): void
    {
        $map = new MenuClassMap;
        $map->put('item:/a', 'item#A', 'App\Nova\A');

        $repository = new ArrayOverrideRepository(['App\Nova\A' => ['weight' => 9000]]);
        $signatures = new MenuSignature;
        $weights = (new MenuWeights(
            repository: $repository,
            signatures: $signatures,
            entries: new MenuEntries($signatures),
            tree: new MenuTree($signatures),
            defaultWeight: 500,
        ))->usingClassMap($map);

        $this->assertSame(9000, $weights->weightForItem(MenuItem::make('A', '/a')));

        $repository->clear();

        $this->assertSame(500, $weights->weightForItem(MenuItem::make('A', '/a')));
    }

    public function test_an_entry_with_no_permissions_is_visible(): void
    {
        $weights = $this->menuWeights([
            MenuIdentity::forPath('/a') => ['weight' => 100],
        ], new MenuClassMap)->authorizeUsing(fn (): bool => false);

        $sorted = $weights->sort(Menu::make([MenuItem::make('A', '/a')]), new Request)->items->all();

        $this->assertCount(1, $sorted);
    }

    public function test_an_entry_is_kept_when_the_viewer_has_any_one_permission(): void
    {
        $granted = ['can see reports'];

        $weights = $this->menuWeights([
            MenuIdentity::forPath('/a') => ['permissions' => ['can see reports', 'can see everything']],
        ], new MenuClassMap)->authorizeUsing(
            fn (Request $request, array $needed): bool => array_intersect($needed, $granted) !== []
        );

        $sorted = $weights->sort(Menu::make([MenuItem::make('A', '/a')]), new Request)->items->all();

        $this->assertSame(['A'], array_map(fn ($i) => (string) $i->name, $sorted));
    }

    public function test_an_entry_is_dropped_when_the_viewer_has_none_of_them(): void
    {
        $weights = $this->menuWeights([
            MenuIdentity::forPath('/a') => ['permissions' => ['can see reports']],
        ], new MenuClassMap)->authorizeUsing(
            fn (Request $request, array $needed): bool => in_array('something else', $needed, true)
        );

        $menu = Menu::make([MenuItem::make('A', '/a'), MenuItem::make('B', '/b')]);
        $sorted = $weights->sort($menu, new Request)->items->all();

        $this->assertSame(['B'], array_map(fn ($i) => (string) $i->name, $sorted));
    }

    public function test_restricting_a_section_takes_its_children_with_it(): void
    {
        $weights = $this->menuWeights([
            MenuIdentity::forLabel('Reports') => ['permissions' => ['can see reports']],
        ], new MenuClassMap)->authorizeUsing(fn (): bool => false);

        $menu = Menu::make([
            MenuSection::make('Reports', [MenuItem::make('Daily', '/daily')]),
            MenuItem::make('Posts', '/posts'),
        ]);

        $sorted = $weights->sort($menu, new Request)->items->all();

        $this->assertSame(['Posts'], array_map(fn ($i) => (string) $i->name, $sorted));
    }

    public function test_the_editing_screen_still_lists_what_the_viewer_may_not_see(): void
    {
        // Restricting an entry to another role must not make it vanish from
        // the screen that restricted it.
        $weights = $this->menuWeights([
            MenuIdentity::forPath('/a') => ['permissions' => ['nobody has this']],
        ], new MenuClassMap)->authorizeUsing(fn (): bool => false);

        $this->assertFalse($weights->viewerMaySee(['nobody has this'], new Request));
        $this->assertTrue($weights->viewerMaySee([], new Request));
    }

    public function test_a_parent_matching_the_natural_one_is_not_stored(): void
    {
        $weights = $this->menuWeights([], new MenuClassMap);
        $present = ['Section' => true];

        $this->assertNull($weights->parentToStore('A', null, ['A' => null], $present));
        $this->assertNull($weights->parentToStore('A', 'Section', ['A' => 'Section'], $present));
    }

    public function test_a_parent_differing_from_the_natural_one_is_stored(): void
    {
        $weights = $this->menuWeights([], new MenuClassMap);
        $present = ['Section' => true];

        $this->assertSame('Section', $weights->parentToStore('A', 'Section', ['A' => null], $present));
        $this->assertSame(MenuIdentity::TOP_LEVEL, $weights->parentToStore('A', null, ['A' => 'Section'], $present));
    }

    public function test_a_move_survives_a_save_made_while_its_target_is_missing(): void
    {
        // The target was not in the menu, so the move was never applied and
        // the entry came back at its natural place. Overwriting here would
        // throw the move away for good.
        $weights = $this->menuWeights([
            'A' => ['parent_identity' => 'SwitchedOffTool'],
        ], new MenuClassMap);

        $this->assertSame(
            'SwitchedOffTool',
            $weights->parentToStore('A', null, ['A' => null], ['Section' => true]),
        );
    }

    public function test_a_parent_pointing_at_something_absent_is_not_accepted_from_the_payload(): void
    {
        $weights = $this->menuWeights([], new MenuClassMap);

        $this->assertNull($weights->parentToStore('A', 'NotInTheMenu', ['A' => null], ['Section' => true]));
    }

    public function test_a_stored_parent_matching_the_natural_one_is_not_reported_as_moved(): void
    {
        // Left over from a move that was undone, or from a menu that has
        // changed since. The badge would be lying.
        $weights = $this->menuWeights([
            'App\Nova\A' => ['parent_identity' => MenuIdentity::TOP_LEVEL],
            'App\Nova\B' => ['parent_identity' => 'App\Nova\Section'],
        ], new MenuClassMap);

        $natural = ['App\Nova\A' => null, 'App\Nova\B' => 'App\Nova\Other'];

        $this->assertFalse($weights->isMovedFromNatural('App\Nova\A', $natural));
        $this->assertTrue($weights->isMovedFromNatural('App\Nova\B', $natural));
        $this->assertFalse($weights->isMovedFromNatural('App\Nova\C', $natural));
    }

    public function test_a_demoted_section_keeps_its_folding(): void
    {
        $weights = $this->menuWeights([
            MenuIdentity::forLabel('Games') => ['parent_identity' => MenuIdentity::forLabel('Content')],
        ], new MenuClassMap);

        $menu = Menu::make([
            MenuSection::make('Content', [MenuItem::make('A', '/a')]),
            MenuSection::make('Games', [MenuItem::make('Vendors', '/vendors')])
                ->collapsable()
                ->collapsedByDefault(),
        ]);

        $sorted = $weights->sort($menu, new Request)->items->all();
        $group = $sorted[0]->items->all()[1];

        $this->assertInstanceOf(MenuGroup::class, $group);
        $this->assertTrue($group->collapsable);
        $this->assertTrue($group->collapsedByDefault);
    }

    public function test_a_nested_section_with_no_children_becomes_a_plain_item(): void
    {
        $weights = $this->menuWeights([
            MenuIdentity::forPath('/sliders') => ['parent_identity' => MenuIdentity::forLabel('Content')],
        ], new MenuClassMap);

        $menu = Menu::make([
            MenuSection::make('Content', [MenuItem::make('A', '/a')]),
            MenuSection::make('Sliders')->path('/sliders'),
        ]);

        $sorted = $weights->sort($menu, new Request)->items->all();
        $nested = $sorted[0]->items->all()[1];

        $this->assertInstanceOf(MenuItem::class, $nested);
        $this->assertSame('/sliders', $nested->path);
    }

    public function test_top_level_sections_are_left_as_they_are(): void
    {
        $weights = $this->menuWeights([], new MenuClassMap);

        $menu = Menu::make([MenuSection::make('Content', [MenuItem::make('A', '/a')])]);

        $sorted = $weights->sort($menu, new Request)->items->all();

        $this->assertInstanceOf(MenuSection::class, $sorted[0]);
    }

    public function test_a_menu_list_cannot_be_given_children(): void
    {
        // Its Vue component renders MenuItem children only, so anything else
        // dropped in would silently not appear.
        $signatures = new MenuSignature;

        $this->assertFalse($signatures->canAcceptChildren(new MenuList([])));
        $this->assertTrue($signatures->canAcceptChildren(MenuSection::make('S')));
        $this->assertTrue($signatures->canAcceptChildren(MenuGroup::make('G')));
    }

    /**
     * @param  array<string, array<string, mixed>|int>  $overrides
     */
    private function menuWeights(array $overrides, MenuClassMap $map): MenuWeights
    {
        $signatures = new MenuSignature;

        return (new MenuWeights(
            repository: new ArrayOverrideRepository($overrides),
            signatures: $signatures,
            entries: new MenuEntries($signatures),
            tree: new MenuTree($signatures),
            defaultWeight: 500,
        ))->usingClassMap($map);
    }
}
