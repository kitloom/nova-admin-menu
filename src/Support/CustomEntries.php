<?php

declare(strict_types=1);

namespace Kitloom\NovaMenuWeights\Support;

use Kitloom\NovaMenuWeights\Contracts\MenuOverrideRepository;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use SplObjectStorage;

/**
 * Entries the admin added, which the application knows nothing about.
 *
 * They are built fresh on every pass and dropped into the menu before the
 * other steps run, so ordering, nesting, hiding and permissions apply to them
 * exactly as they do to everything else.
 */
final class CustomEntries
{
    /** @var SplObjectStorage<object, string> */
    private SplObjectStorage $identities;

    public function __construct(private readonly MenuOverrideRepository $repository)
    {
        $this->identities = new SplObjectStorage;
    }

    /**
     * @return array<int, MenuItem|MenuSection>
     */
    public function build(): array
    {
        $built = [];

        foreach ($this->repository->all() as $identity => $override) {
            if (! MenuIdentity::isCustom($identity) || $override->type === null) {
                continue;
            }

            $entry = $this->make($override);

            if ($entry === null) {
                continue;
            }

            // A built entry carries nothing that leads back to its row - no
            // class, and a label the admin may reuse - so the mapping is kept
            // here rather than derived. Earlier objects stay in the map: a
            // pass may hold on to them after the next build.
            $this->identities[$entry] = $identity;

            $built[] = $entry;
        }

        return $built;
    }

    public function identityOf(object $entry): ?string
    {
        return $this->identities[$entry] ?? null;
    }

    public function forget(): void
    {
        $this->identities = new SplObjectStorage;
    }

    private function make(MenuOverride $override): MenuItem|MenuSection|null
    {
        $label = $override->label ?? '';

        if ($label === '') {
            return null;
        }

        return match ($override->type) {
            // A container is always built as a section. Nova renders a group
            // only inside a section, so where it ends up - not what it was
            // called on creation - decides how it is drawn. MenuTree demotes
            // it to a group once it is nested.
            'section', 'group' => $this->makeSection($label, $override),
            'item' => $this->makeItem($label, $override->path),
            default => null,
        };
    }

    private function makeSection(string $label, MenuOverride $override): MenuSection
    {
        $section = MenuSection::make($label);

        if ($override->path !== null) {
            $section->path($override->path);
        }

        // Only sections show an icon in Nova; the default keeps a new one from
        // sitting there without any mark at all.
        $section->icon($override->icon ?? 'bars-3');

        return $section->collapsable();
    }

    private function makeItem(string $label, ?string $path): ?MenuItem
    {
        if ($path === null) {
            return null;
        }

        return $this->isExternal($path)
            ? MenuItem::externalLink($label, $path)
            : MenuItem::link($label, $path);
    }

    private function isExternal(string $path): bool
    {
        return (bool) preg_match('#^(https?:)?//#i', $path);
    }
}
