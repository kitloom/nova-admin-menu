<?php

declare(strict_types=1);

namespace Kitloom\NovaAdminMenu;

use Illuminate\Http\Request;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;

/**
 * The drag and drop screen for reordering the main menu.
 */
class AdminMenuTool extends Tool
{
    public function boot(): void
    {
        Nova::script('nova-admin-menu', __DIR__.'/../dist/js/entry.js');

        // Nova's stylesheet carries only the utilities Nova itself uses, so
        // the ones this screen needs on top of those are shipped with it.
        Nova::style('nova-admin-menu', __DIR__.'/../dist/css/entry.css');
    }

    public function menu(Request $request): ?MenuSection
    {
        if (! config('nova-admin-menu.tool.show_in_sidebar', true)) {
            return null;
        }

        return MenuSection::make(__(config('nova-admin-menu.tool.title', 'Admin Menu')))
            ->path(config('nova-admin-menu.tool.path', 'nova-admin-menu'))
            ->icon('bars-3');
    }
}
