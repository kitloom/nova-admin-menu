<?php

declare(strict_types=1);

namespace Kitloom\NovaAdminMenu\Support;

use Illuminate\Http\Request;
use Laravel\Nova\Nova;

/**
 * Everything currently present in the menu that can be given a weight,
 * flattened into rows a settings screen can render.
 */
final class MenuEntries
{
    public function __construct(private readonly MenuSignature $signatures) {}

    /**
     * @return array<int, array{class: class-string, label: string, type: string}>
     */
    public function all(Request $request): array
    {
        return [...$this->tools($request), ...$this->resources($request), ...$this->dashboards($request)];
    }

    /**
     * @return array<int, array{class: class-string, label: string, type: string}>
     */
    public function tools(Request $request): array
    {
        $entries = [];

        foreach (Nova::availableTools($request) as $tool) {
            $entries[] = [
                'class' => $tool::class,
                'label' => $this->signatures->toolLabel($tool, $request),
                'type' => 'tool',
            ];
        }

        return $entries;
    }

    /**
     * @return array<int, array{class: class-string, label: string, type: string}>
     */
    public function resources(Request $request): array
    {
        $entries = [];

        foreach (Nova::resourcesForNavigation($request) as $resourceClass) {
            $entries[] = [
                'class' => $resourceClass,
                'label' => (string) $resourceClass::label(),
                'type' => 'resource',
            ];
        }

        return $entries;
    }

    /**
     * @return array<int, array{class: class-string, label: string, type: string}>
     */
    public function dashboards(Request $request): array
    {
        $entries = [];

        foreach (Nova::availableDashboards($request) as $dashboard) {
            $entries[] = [
                'class' => $dashboard::class,
                'label' => (string) $dashboard->label(),
                'type' => 'dashboard',
            ];
        }

        return $entries;
    }
}
