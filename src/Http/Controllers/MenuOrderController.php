<?php

declare(strict_types=1);

namespace Kitloom\NovaMenuWeights\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Kitloom\NovaMenuWeights\Contracts\MenuOverrideRepository;
use Kitloom\NovaMenuWeights\MenuWeights;
use Kitloom\NovaMenuWeights\Support\MenuIdentity;
use Kitloom\NovaMenuWeights\Support\MenuSignature;

class MenuOrderController extends Controller
{
    public function __construct(
        private readonly MenuWeights $menuWeights,
        private readonly MenuOverrideRepository $repository,
        private readonly MenuSignature $signatures,
    ) {}

    /**
     * The menu as it currently renders, nesting and all. Weights only ever
     * order siblings, so the tree is what has to be edited - an entry cannot
     * be moved into another section by changing a number.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'tree' => $this->tree($request),
            'permissions' => $this->menuWeights->availablePermissions(),
        ]);
    }

    /**
     * Store the menu: every sortable entry, depth first, in the order it is
     * displayed, each with whatever the admin changed about it. Weights come
     * from the position, so the numbers never have to be typed by hand.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entries' => ['required', 'array'],
            'entries.*.id' => ['required', 'string'],
            'entries.*.hidden' => ['sometimes', 'boolean'],
            'entries.*.parent' => ['nullable', 'string'],
            'entries.*.path' => ['nullable', 'string', 'max:2048'],
            'entries.*.permissions' => ['sometimes', 'array'],
            'entries.*.permissions.*' => ['required', 'string', 'max:191'],
            'entries.*.label' => ['nullable', 'string', 'max:255'],
            'entries.*.icon' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
        ], [
            'entries.*.icon.regex' => __('An icon is a heroicon name, such as "squares-2x2".'),
        ]);

        // Only entries the menu actually contains may be written, so a crafted
        // payload cannot create arbitrary rows.
        $known = [];
        $this->collectIdentities($this->tree($request), $known);

        $entries = collect($validated['entries'])
            ->filter(static fn (array $entry): bool => isset($known[$entry['id']]))
            ->values();

        if ($entries->isEmpty()) {
            return response()->json(['message' => __('No known menu entries were submitted.')], 422);
        }

        $step = (int) config('nova-menu-weights.tool.step', 100);
        $total = $entries->count();
        $reparenting = (bool) config('nova-menu-weights.menu.allow_reparenting', true);
        $natural = $reparenting ? $this->menuWeights->naturalParents($request) : [];

        $this->repository->putMany(
            $entries->mapWithKeys(function (array $entry, int $index) use ($total, $step, $reparenting, $natural, $known): array {
                return [
                    $entry['id'] => [
                        'weight' => ($total - $index) * $step,
                        'hidden' => (bool) ($entry['hidden'] ?? false),
                        'label' => $entry['label'] ?? null,
                        'icon' => $entry['icon'] ?? null,
                        'permissions' => array_values($entry['permissions'] ?? []),
                        // Only an entry of the admin's own has a path to edit;
                        // everything else gets its path from the application.
                        'path' => MenuIdentity::isCustom($entry['id']) ? ($entry['path'] ?? null) : null,
                        'parent_identity' => $reparenting
                            ? $this->menuWeights->parentToStore(
                                $entry['id'],
                                $entry['parent'] ?? null,
                                $natural,
                                $known,
                            )
                            : null,
                    ],
                ];
            })->all()
        );

        $this->menuWeights->flush();

        return response()->json(['saved' => $entries->count()]);
    }

    /**
     * Add an entry of the admin's own. It has nothing in the application
     * behind it, so it carries an id of its own and is rebuilt from the row
     * on every request.
     */
    public function storeCustom(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:section,group,item'],
            'label' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'path' => ['nullable', 'string', 'max:2048', 'required_if:type,item'],
        ], [
            'path.required_if' => __('A link needs a path or a URL.'),
            'icon.regex' => __('An icon is a heroicon name, such as "squares-2x2".'),
        ]);

        $identity = MenuIdentity::forCustom((string) Str::uuid());

        $this->repository->put($identity, [
            'type' => $validated['type'],
            'label' => $validated['label'],
            'icon' => $validated['icon'] ?? null,
            'path' => $validated['path'] ?? null,
        ]);

        $this->menuWeights->flush();

        return response()->json(['id' => $identity], 201);
    }

    /**
     * Remove one the admin added. Entries belonging to the application are
     * not deletable - hiding is what they get.
     */
    public function destroyCustom(Request $request, string $id): JsonResponse
    {
        $identity = MenuIdentity::forCustom($id);

        if ($this->repository->find($identity)?->type === null) {
            return response()->json(['message' => __('No such entry.')], 404);
        }

        $this->repository->forget($identity);
        $this->menuWeights->flush();

        return response()->json(['deleted' => $identity]);
    }

    /**
     * Throw every override away. The menu goes back to what the application
     * builds on its own: the order the tools register in, their own labels,
     * icons and nesting.
     */
    public function destroy(Request $request): JsonResponse
    {
        $removed = count($this->repository->all());

        $this->repository->clear();
        $this->menuWeights->flush();

        return response()->json(['removed' => $removed]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tree(Request $request): array
    {
        return $this->nodes(
            $this->menuWeights->itemsIncludingHidden($request),
            $request,
            $this->menuWeights->naturalParents($request),
        );
    }

    /**
     * @param  iterable<mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function nodes(iterable $items, Request $request, array $natural): array
    {
        $classMap = $this->menuWeights->classMap($request);
        $nodes = [];

        foreach ($items as $item) {
            if (! is_object($item)) {
                continue;
            }

            $identity = $this->menuWeights->identityForItem($item, $classMap);

            $override = $identity === null ? null : $this->menuWeights->overrideFor($identity);

            $nodes[] = [
                'id' => $identity,
                // What the menu itself calls this entry, never the override -
                // the screen shows both, and the override is edited against it.
                'label' => (string) ($item->name ?? class_basename($item)),
                'label_override' => $override?->label,
                'icon' => $item->icon ?? null,
                'icon_override' => $override?->icon,
                'supports_icon' => $this->menuWeights->supportsIcon($item),
                'custom' => $identity !== null && MenuIdentity::isCustom($identity),
                'path' => $identity !== null && MenuIdentity::isCustom($identity) ? $override?->path : null,
                'can_accept_children' => $this->signatures->canAcceptChildren($item),
                // Nova draws a container only when it holds something or
                // links somewhere, so an empty one silently never appears.
                'empty' => $this->signatures->canAcceptChildren($item)
                    && $item->items->isEmpty()
                    && ($item->path ?? null) === null,
                'moved' => $identity !== null && $this->menuWeights->isMovedFromNatural($identity, $natural),
                'type' => strtolower(str_replace('Menu', '', class_basename($item))),
                'via' => $identity === null ? null : $this->via($identity),
                'weight' => $this->menuWeights->weightForItem($item, $classMap),
                'hidden' => $this->menuWeights->isHidden($item),
                'permissions' => $identity === null ? [] : ($override?->permissions ?? []),
                'sortable' => $identity !== null,
                'children' => $this->signatures->isNestable($item)
                    ? $this->nodes($item->items->all(), $request, $natural)
                    : [],
            ];
        }

        return $nodes;
    }

    /**
     * How stable this entry's name is, for the UI to explain itself.
     */
    private function via(string $identity): string
    {
        return match (true) {
            str_starts_with($identity, MenuIdentity::PATH_PREFIX) => 'path',
            str_starts_with($identity, MenuIdentity::LABEL_PREFIX) => 'label',
            default => 'class',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, true>  $found
     */
    private function collectIdentities(array $nodes, array &$found): void
    {
        foreach ($nodes as $node) {
            if ($node['id'] !== null) {
                $found[$node['id']] = true;
            }

            $this->collectIdentities($node['children'], $found);
        }
    }
}
