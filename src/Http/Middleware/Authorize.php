<?php

declare(strict_types=1);

namespace Kitloom\NovaAdminMenu\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kitloom\NovaAdminMenu\AdminMenuTool;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;

class Authorize
{
    public function handle(Request $request, Closure $next): mixed
    {
        $tool = collect(Nova::registeredTools())->first([$this, 'matchesTool']);

        return optional($tool)->authorize($request) ? $next($request) : abort(403);
    }

    public function matchesTool(Tool $tool): bool
    {
        return $tool instanceof AdminMenuTool;
    }
}
