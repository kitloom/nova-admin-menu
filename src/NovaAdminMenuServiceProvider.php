<?php

declare(strict_types=1);

namespace Kitloom\NovaAdminMenu;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Kitloom\NovaAdminMenu\Contracts\MenuOverrideRepository;
use Kitloom\NovaAdminMenu\Facades\AdminMenu as AdminMenuFacade;
use Kitloom\NovaAdminMenu\Http\Middleware\Authorize;
use Kitloom\NovaAdminMenu\Repositories\DatabaseOverrideRepository;
use Kitloom\NovaAdminMenu\Support\CustomEntries;
use Kitloom\NovaAdminMenu\Support\MenuEntries;
use Kitloom\NovaAdminMenu\Support\MenuSignature;
use Kitloom\NovaAdminMenu\Support\MenuTree;
use Laravel\Nova\Http\Middleware\Authenticate;
use Laravel\Nova\Menu\Menu;
use Laravel\Nova\Nova;

final class NovaAdminMenuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nova-admin-menu.php', 'nova-admin-menu');

        $this->registerRoutes();

        $this->app->singleton(MenuSignature::class);
        $this->app->singleton(MenuEntries::class);
        $this->app->singleton(MenuTree::class);
        $this->app->singleton(CustomEntries::class);

        $this->app->singleton(MenuOverrideRepository::class, function (Application $app): MenuOverrideRepository {
            $config = $app['config'];
            $repository = $config->get('nova-admin-menu.repository', DatabaseOverrideRepository::class);

            if ($repository !== DatabaseOverrideRepository::class) {
                return $app->make($repository);
            }

            return new DatabaseOverrideRepository(
                connection: $app->make('db')->connection(),
                cache: $app->make('cache')->store(),
                table: (string) $config->get('nova-admin-menu.table', 'nova_menu_overrides'),
                cacheEnabled: (bool) $config->get('nova-admin-menu.cache.enabled', true),
                cacheKey: (string) $config->get('nova-admin-menu.cache.key', 'nova_menu_overrides'),
                cacheTtl: (int) $config->get('nova-admin-menu.cache.ttl', 3600),
            );
        });

        $this->app->singleton(AdminMenu::class, function (Application $app): AdminMenu {
            return new AdminMenu(
                repository: $app->make(MenuOverrideRepository::class),
                signatures: $app->make(MenuSignature::class),
                entries: $app->make(MenuEntries::class),
                tree: $app->make(MenuTree::class),
                custom: $app->make(CustomEntries::class),
                defaultWeight: (int) $app['config']->get('nova-admin-menu.default_weight', 500),
                allowReparenting: (bool) $app['config']->get('nova-admin-menu.menu.allow_reparenting', true),
            );
        });
    }

    public function boot(): void
    {
        $this->registerMainMenu();
        $this->registerTool();

        if (config('nova-admin-menu.repository') === DatabaseOverrideRepository::class) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/nova-admin-menu.php' => config_path('nova-admin-menu.php'),
            ], 'nova-admin-menu-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'nova-admin-menu-migrations');
        }
    }

    /**
     * Apply the overrides to Nova's main menu.
     *
     * Nova keeps a single mainMenu callback, so an application that registers
     * its own silently replaces this one. Such an application should set
     * `menu.register` to false and call AdminMenu::sort() itself.
     */
    private function registerMainMenu(): void
    {
        $config = $this->app['config'];

        if (! $config->get('nova-admin-menu.menu.register', true)) {
            return;
        }

        $hidden = (array) $config->get('nova-admin-menu.menu.hide_paths', []);

        if ($hidden !== []) {
            $this->app->make(AdminMenu::class)->hidePaths($hidden);
        }

        Nova::mainMenu(static fn (Request $request, Menu $menu): Menu => AdminMenuFacade::sort($menu, $request));
    }

    /**
     * The drag and drop screen, plus the endpoints it talks to.
     */
    private function registerRoutes(): void
    {
        Nova::router()->group(function ($router) {
            $path = (string) config('nova-admin-menu.tool.path', 'nova-admin-menu');

            $router
                ->get($path, fn () => inertia('NovaAdminMenu', [
                    'path' => $path,
                    // One source for the name, so the page and the sidebar
                    // entry cannot drift apart.
                    'title' => __((string) config('nova-admin-menu.tool.title', 'Admin Menu')),
                ]))
                ->middleware(['nova', Authenticate::class])
                ->domain(config('nova.domain'));
        });

        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['nova', Authorize::class])
            ->domain(config('nova.domain'))
            ->group(__DIR__.'/../routes/api.php');
    }

    /**
     * Add the tool to Nova without the host application listing it in its own
     * NovaServiceProvider::tools().
     */
    private function registerTool(): void
    {
        if (! $this->app['config']->get('nova-admin-menu.tool.register', true)) {
            return;
        }

        Nova::serving(static function (): void {
            Nova::tools([new AdminMenuTool]);
        });
    }
}
