# Nova Menu Weights

Reorder the Laravel Nova main menu from the admin panel instead of hardcoding the order in
`NovaServiceProvider`.

Every tool, resource and dashboard gets a numeric weight stored in the database. Heavier entries
float to the top, nested sections and groups are sorted recursively, and entries without a weight
keep the position Nova gave them.

- Works with **tools, resources and dashboards** — not just resources
- Sorts **nested** entries too — submenus, not just the top level
- A **drag and drop screen**: reorder the menu by hand, weights are derived from the position
- **Hide** entries you do not want in the sidebar, without touching any code
- **Restrict** an entry to the permissions you name, whatever your permission system is
- **Rename** entries and change the **icon** of top level sections
- **Move** entries between sections, not just up and down within one
- Storage is a **contract**: one small table by default, or plug in your own
- No changes to your resource classes

## Requirements

- PHP 8.2+
- Laravel Nova 5

## Installation

```bash
composer require kitloom/nova-admin-menu
php artisan migrate
```

Then sort the menu from your `NovaServiceProvider`:

```php
use Kitloom\NovaAdminMenu\Facades\AdminMenu;
use Laravel\Nova\Menu\Menu;
use Laravel\Nova\Nova;

Nova::mainMenu(fn (Request $request, Menu $menu) => AdminMenu::sort($menu, $request));
```

If you build menu entries of your own, merge them in first and sort the result:

```php
Nova::mainMenu(function (Request $request, Menu $menu) {
    $items = array_merge($myCustomSections, $menu->items->all());

    $menu->items = new MenuCollection(AdminMenu::sortItems($items, $request));

    return $menu;
});
```

## Editing the weights

The package registers a Nova tool at `/nova-admin-menu` showing the menu as a tree. Drag the rows,
hit save, and the weights are recalculated from the position — in steps of 100, so there is room to
slot things in by hand later. Nothing to wire up: the tool, its route and its sidebar entry are
registered by the service provider.

Drop an entry on the **top or bottom edge** of a row to place it next to that row, or on the
**middle of a section** to put it inside. An entry that ended up somewhere the menu would not have
put it is marked `moved`.

Each row has an **edit** button for renaming the entry and, on a top level section, for setting its
icon, picked from a list of the 324 names Nova can actually draw. Choosing the empty option puts
the original back.

That list is generated from the icon components Nova bundles rather than copied from the heroicons
docs, so it never offers something that would render blank. Regenerate it after a Nova upgrade:

```bash
grep -o '\b[A-Z][A-Za-z0-9]*Icon\b' vendor/laravel/nova/public/vendor.js | sort -u
``` Icons apply to sections only: `MenuItem`, `MenuGroup` and `MenuList` render
without one in Nova, so there is nothing to override on them.

**Add entry** creates one of your own: a section to group things under, a group heading inside a
section, or a link. A link pointing at something starting with `http://` or `https://` opens as an
external link. Created entries are ordinary rows on this screen — drag, rename, hide and restrict
them like anything else — and only they can be deleted; entries the application provides can only
be hidden.

Nothing checks that a link goes anywhere. That is the admin's call, as is who gets to see it.

**Reset** in the header throws every override away after a confirmation — the order, the moves,
renamed entries, icons, hidden entries and permissions — and reloads the page. The menu goes back
to what the application builds on its own. There is no undo.

Each row also has a **hide** button. A hidden entry disappears from the sidebar — a hidden section
takes its children with it — but stays listed on this screen, struck through, so it can be brought
back. That is the difference from `menu.hide_paths` in the config, which removes an entry outright
and gives no way to restore it from the UI.

```php
// config/nova-admin-menu.php
'tool' => [
    'register' => true,
    'path' => 'nova-admin-menu',
    'title' => 'Admin Menu',
    'show_in_sidebar' => true,
    'step' => 100,
],
```

Only classes that are actually in the menu are accepted by the save endpoint, so a crafted payload
cannot create arbitrary settings rows.

### Your own screen

```php
use Kitloom\NovaAdminMenu\Facades\AdminMenu;

foreach (AdminMenu::entries()->all($request) as $entry) {
    // $entry['class'], $entry['label'], $entry['type']
    $weight = AdminMenu::weightForClass($entry['class']);
}
```

The tool's own endpoints return the menu as a tree instead, which is what nested entries need:

```
GET  /nova-vendor/nova-admin-menu/entries
POST /nova-vendor/nova-admin-menu/entries   {"order": ["<identity>", ...]}
```

## Storage

Overrides live in one table, `nova_menu_overrides`, a row per changed entry:

| column | |
|---|---|
| `identity` | how the entry is named — see below |
| `weight` | position among its siblings |
| `hidden` | entry is kept out of the sidebar |
| `permissions` | names the viewer needs one of, empty for everyone |
| `label`, `icon` | replace what the menu itself renders |
| `parent_identity` | section the entry was moved into, or `__top__` for the top level |
| `type`, `path` | set only on entries created here: `section`, `group` or `item`, and where a link goes |

A parent is written **only when it differs** from where the menu itself puts the entry. Storing the
natural one would freeze the structure, and a later version of a tool adding a child would spend
its life fighting a stale override.

The whole table is loaded in one query and cached. To keep the overrides
somewhere else, implement `Kitloom\NovaAdminMenu\Contracts\MenuOverrideRepository`
and name it in `nova-admin-menu.repository`.

Publish the config to change any of this:

```bash
php artisan vendor:publish --tag=nova-admin-menu-config
```

## Restricting entries

Each row takes a list of permission names. An entry is shown when the viewer has **any one** of
them; an empty list means everyone. A restricted section takes its children with it.

The names mean nothing to this package — one application checks them against a role table, another
against gates — so the application says what they mean:

```php
// AppServiceProvider or NovaServiceProvider
AdminMenu::authorizeUsing(fn (Request $request, array $permissions) => $request->user()->isSuperAdmin()
    || collect($permissions)->contains(fn (string $p) => $request->user()->hasPermissionTo($p)));
```

Without that the package falls back to `Gate::any()`, which suits a stock application or Spatie's
permissions. **Check which one you need**: a permission system that registers no gates — pktharindu's,
for instance — will fail every check through the fallback, and restricted entries then disappear
even for a super admin.

To fill the suggestion list on the editing screen:

```php
AdminMenu::permissionsUsing(fn () => Permission::pluck('name')->all());
```

### This hides links, it does not protect pages

The entry disappears from the sidebar; the page behind it is authorised separately by Nova, through
the resource's policy or the tool's own `canSee`. Anyone who knows the URL still gets whatever that
authorisation allows. Use this to keep menus tidy per role — and where it has to be a restriction,
gate the resource too.

The check is applied by dropping entries from the menu, never by attaching a `canSee` callback:
`AuthorizedToSee::canSee` **replaces** the callback, so attaching one would throw away whatever the
tool had already set, and a section closed to a role would quietly open up. Filtering can only ever
remove.

An entry restricted to someone else stays on the editing screen — otherwise there would be no way
to lift the restriction.

## Moving entries, and what it does to visibility

Nova rebuilds the menu from the tools on every request, so a move is an override replayed each
time. It has to survive the menu changing underneath it, and it does:

| | |
|---|---|
| the target is gone | the entry stays where the menu put it |
| the target cannot hold children — a `MenuItem` has no `items` | the move is ignored |
| a section is dropped inside its own descendant | the move is ignored |
| a section is emptied by moves | Nova stops rendering it — `MenuSection.vue` draws nothing without children or a path |

### Three levels

Nova renders the menu recursively, so nesting works — but it styles `MenuSection` for the top
level: icon slot, full size text, no indent. A section dropped inside another one would read as a
second root rather than a sub-menu.

Nova's own vocabulary for a heading inside a section is `MenuGroup`, so that is what a nested
section is drawn as:

| nested section | drawn as |
|---|---|
| with children | `MenuGroup` — the small upper-case heading |
| just a link | `MenuItem` |

A group has neither icon nor link, so a section loses both on the way down. This is a rendering
step, run after every identity is resolved: nothing stored changes, and dragging the section back
out restores it.

A `MenuList` is never a drop target — its Vue component renders `MenuItem` children and nothing
else, so anything else put inside would silently vanish.

**Visibility is the part to think about.** Nova filters the menu at every level: an entry is shown
only when its own `canSee` passes *and* every section above it passes too. Moving an entry
therefore changes which rules wrap it — out of a restricted section, its link becomes visible to
more people. The page behind the link is authorised separately by Nova, so this is about who learns
the entry exists, not about who can open it.

The package cannot warn you precisely: `canSee` is a closure, two of them cannot be compared, and
either can only be evaluated for the admin currently looking. The screen says so plainly instead.
To take the feature off the table:

```php
'menu' => ['allow_reparenting' => false],
```

## How entries are named

Nova hands the `mainMenu` callback fully built menu entries with no reference back to whatever
produced them, so a weight has to be tied to something stable on the entry itself. The package tries
three things, in order:

| | Identity | Stable across |
|---|---|---|
| 1 | the **class** behind the entry, e.g. `App\Nova\Tools\Posts` | renames of labels and paths |
| 2 | its **path**, e.g. `menu_path:/resources/posts` | renames of labels, locale changes |
| 3 | its **label**, e.g. `menu_label:Reports` | nothing — a locale switch loses it |

An entry created on the screen has none of these: nothing in the application produces it. It gets
an id of its own, `menu_custom:<uuid>`, and is rebuilt from its row on every request.

Only top level entries can be traced back to a class: the package asks every registered tool,
resource and dashboard what it would render and indexes the result by path and by label. A tool
builds its own children, though, and nothing connects those to a class — which is why nested entries
are named by path. That is what makes submenus sortable at all.

The third case is rare and worth avoiding: it applies to entries with no path, such as a
`MenuGroup`. The tool marks those with a "by label" badge.

Renaming is applied *after* the identity is resolved, and the editing screen always shows the label
the menu itself produces. Without that, renaming an entry of the third kind would change what it is
identified by, and everything stored against it would be orphaned on the next request.

Class names keep the key shape they have always had, so weights stored by earlier versions are still
found after upgrading.

## Testing

```bash
composer test
```

## License

MIT.
