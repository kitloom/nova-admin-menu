<?php

declare(strict_types=1);

namespace Kitloom\NovaMenuWeights\Support;

/**
 * Names a menu entry for storage.
 *
 * Three kinds, in order of preference:
 *
 *   1. the class behind the entry     App\Nova\Tools\Posts
 *   2. its path                       menu_path:/resources/posts
 *   3. its label                      menu_label:Instant Indexing
 *
 * A class is the most stable name, but only top level entries can be traced
 * back to one: Nova asks a tool for its menu and the tool builds the children
 * itself, so nothing connects them to a class. Those children do have paths,
 * which is what makes nested entries sortable at all.
 *
 * Labels are the last resort, used by entries with neither - a MenuGroup, for
 * instance. They are translated, so such a weight is lost when the admin
 * switches locale.
 */
final class MenuIdentity
{
    public const PATH_PREFIX = 'menu_path:';

    public const LABEL_PREFIX = 'menu_label:';

    /**
     * Stored as the parent of an entry moved out to the top level. A null
     * parent means "not moved at all", so the two need telling apart.
     */
    public const TOP_LEVEL = '__top__';

    public static function forPath(string $path): string
    {
        return self::PATH_PREFIX.$path;
    }

    public static function forLabel(string $label): string
    {
        return self::LABEL_PREFIX.$label;
    }
}
