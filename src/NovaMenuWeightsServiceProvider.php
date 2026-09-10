<?php

declare(strict_types=1);

namespace Kitloom\NovaMenuWeights;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Kitloom\NovaMenuWeights\Contracts\MenuOverrideRepository;
use Kitloom\NovaMenuWeights\Facades\MenuWeights as MenuWeightsFacade;
use Kitloom\NovaMenuWeights\Http\Middleware\Authorize;
use Kitloom\NovaMenuWeights\Repositories\DatabaseOverrideRepository;
use Kitloom\NovaMenuWeights\Support\MenuEntries;
use Kitloom\NovaMenuWeights\Support\MenuSignature;
use Kitloom\NovaMenuWeights\Support\MenuTree;
use Laravel\Nova\Http\Middleware\Authenticate;
use Laravel\Nova\Menu\Menu;
use Laravel\Nova\Nova;

final class NovaMenuWeightsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nova-menu-weights.php', 'nova-menu-weights');

        $this->registerRoutes();

        $this->app->singleton(MenuSignature::class);
        $this->app->singleton(MenuEntries::class);
        $this->app->singleton(MenuTree::class);

        $this->app->singleton(MenuOverrideRepository::class, function (Application $app): MenuOverrideRepository {
            $config = $app['config'];
            $repository = $config->get('nova-menu-weights.repository', DatabaseOverrideRepository::class);

            if ($repository !== DatabaseOverrideRepository::class) {
                return $app->make($repository);
            }

            return new DatabaseOverrideRepository(
                connection: $app->make('db')->connection(),
                cache: $app->make('cache')->store(),
                table: (string) $config->get('nova-menu-weights.table', 'nova_menu_overrides'),
                cacheEnabled: (bool) $config->get('nova-menu-weights.cache.enabled', true),
                cacheKey: (string) $config->get('nova-menu-weights.cache.key', 'nova_menu_overrides'),
                cacheTtl: (int) $config->get('nova-menu-weights.cache.ttl', 3600),
            );
        });

        $this->app->singleton(MenuWeights::class, function (Application $app): MenuWeights {
            return new MenuWeights(
                repository: $app->make(MenuOverrideRepository::class),
                signatures: $app->make(MenuSignature::class),
                entries: $app->make(MenuEntries::class),
                tree: $app->make(MenuTree::class),
                defaultWeight: (int) $app['config']->get('nova-menu-weights.default_weight', 500),
                allowReparenting: (bool) $app['config']->get('nova-menu-weights.menu.allow_reparenting', true),
            );
        });
    }

    public function boot(): void
    {
        $this->registerMainMenu();
        $this->registerTool();

        if (config('nova-menu-weights.repository') === DatabaseOverrideRepository::class) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/nova-menu-weights.php' => config_path('nova-menu-weights.php'),
            ], 'nova-menu-weights-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'nova-menu-weights-migrations');
        }
    }

    /**
     * Apply the overrides to Nova's main menu.
     *
     * Nova keeps a single mainMenu callback, so an application that registers
     * its own silently replaces this one. Such an application should set
     * `menu.register` to false and call MenuWeights::sort() itself.
     */
    private function registerMainMenu(): void
    {
        $config = $this->app['config'];

        if (! $config->get('nova-menu-weights.menu.register', true)) {
            return;
        }

        $hidden = (array) $config->get('nova-menu-weights.menu.hide_paths', []);

        if ($hidden !== []) {
            $this->app->make(MenuWeights::class)->hidePaths($hidden);
        }

        Nova::mainMenu(static fn (Request $request, Menu $menu): Menu => MenuWeightsFacade::sort($menu, $request));
    }

    /**
     * The drag and drop screen, plus the endpoints it talks to.
     */
    private function registerRoutes(): void
    {
        Nova::router()->group(function ($router) {
            $path = (string) config('nova-menu-weights.tool.path', 'nova-menu-weights');

            $router
                ->get($path, fn () => inertia('NovaMenuWeights', ['path' => $path]))
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
        if (! $this->app['config']->get('nova-menu-weights.tool.register', true)) {
            return;
        }

        Nova::serving(static function (): void {
            Nova::tools([new MenuWeightsTool]);
        });
    }
}
