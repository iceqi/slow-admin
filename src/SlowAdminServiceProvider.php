<?php

namespace Slowlyo\SlowAdmin;

use Illuminate\Support\Arr;
use Slowlyo\SlowAdmin\Console;
use Slowlyo\SlowAdmin\Libs\Context;
use Slowlyo\SlowAdmin\Extend\Manager;
use Illuminate\Support\ServiceProvider;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;

class SlowAdminServiceProvider extends ServiceProvider
{
    protected array $commands = [
        Console\InstallCommand::class,
        Console\PublishCommand::class,
        Console\CreateUserCommand::class,
        Console\ResetPasswordCommand::class,
    ];

    protected array $routeMiddleware = [
        'admin.auth'       => Middleware\Authenticate::class,
        'admin.bootstrap'  => Middleware\Bootstrap::class,
        'admin.session'    => Middleware\Session::class,
        'admin.permission' => Middleware\Permission::class,
        'sanctum'          => Middleware\EnsureFrontendRequestsAreStateful::class,
        'substitute'       => \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ];

    protected array $middlewareGroups = [
        'admin' => [
            'admin.auth',
            'admin.bootstrap',
            'admin.session',
            'admin.permission',
            'sanctum',
            'substitute',
        ],
    ];

    /**
     * Register any application services.
     *
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function register(): void
    {
        $this->loadAdminAuthConfig();
        $this->mergeConfigFrom(__DIR__ . '/../config/admin.php', 'admin');
        $this->registerServices();
        $this->registerExtensions();
        $this->registerRouteMiddleware();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \ReflectionException
     */
    public function boot(): void
    {
        $this->ensureHttps();
        $this->registerPublishing();
        $this->bootExtensions();

        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        if (file_exists($routes = admin_path('routes.php'))) {
            $this->loadRoutesFrom($routes);
        }
        $this->initExtensionRoutes();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);

            $this->publishes([__DIR__ . '/../admin-views/dist' => public_path('admin')], 'admin-assets');
            $this->publishes([__DIR__ . '/../lang' => lang_path()], 'admin-lang');
            $this->publishes([__DIR__ . '/../config/admin.php' => config_path('admin.php')], 'admin-config');
            $this->publishes([__DIR__ . '/../admin-views' => resource_path('admin-views')], 'admin-views');
        }
    }

    protected function ensureHttps()
    {
        if (config('admin.https')) {
            \URL::forceScheme('https');
            $this->app['request']->server->set('HTTPS', true);
        }
    }

    protected function loadAdminAuthConfig()
    {
        config(Arr::dot(config('admin.auth', []), 'auth.'));
    }

    public function registerServices()
    {
        $this->app->singleton('admin.extend', Manager::class);
        $this->app->singleton('admin.context', Context::class);
        $this->app->singleton('admin.setting', fn() => settings());
    }

    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Exception
     */
    public function registerExtensions()
    {
        Admin::extension()->register();
    }

    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface|\ReflectionException
     */
    public function bootExtensions()
    {
        Admin::extension()->boot();
    }

    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \ReflectionException
     */
    public function initExtensionRoutes()
    {
        Admin::extension()->initRoutes();
    }

    protected function registerRouteMiddleware(): void
    {
        $router = $this->app->make('router');

        foreach ($this->routeMiddleware as $key => $middleware) {
            $router->aliasMiddleware($key, $middleware);
        }
        foreach ($this->middlewareGroups as $key => $middleware) {
            $router->middlewareGroup($key, $middleware);
        }
    }
}
