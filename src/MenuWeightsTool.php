<?php

declare(strict_types=1);

namespace Kitloom\NovaMenuWeights;

use Illuminate\Http\Request;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;

/**
 * The drag and drop screen for reordering the main menu.
 */
class MenuWeightsTool extends Tool
{
    public function boot(): void
    {
        Nova::script('nova-menu-weights', __DIR__.'/../dist/js/entry.js');
    }

    public function menu(Request $request): ?MenuSection
    {
        if (! config('nova-menu-weights.tool.show_in_sidebar', true)) {
            return null;
        }

        return MenuSection::make(__(config('nova-menu-weights.tool.title', 'Menu Order')))
            ->path(config('nova-menu-weights.tool.path', 'nova-menu-weights'))
            ->icon('bars-3');
    }
}
