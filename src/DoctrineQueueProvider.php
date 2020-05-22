<?php

namespace MaxBrokman\SafeQueue;

use Illuminate\Cache\CacheManager;
use Illuminate\Support\ServiceProvider;
use MaxBrokman\SafeQueue\Console\WorkCommand;

/**
 * @codeCoverageIgnore
 */
class DoctrineQueueProvider extends ServiceProvider
{
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        if (!$this->isLumen()) {
            $this->publishes([
                __DIR__ . '/../config/safequeue.php' => config_path('safequeue.php'),
            ], 'safequeue');
        }

        $this->mergeConfigFrom(
            __DIR__ . '/../config/safequeue.php', 'safequeue'
        );

        $this->registerWorker();
    }

    public function boot()
    {
        $this->commands('command.safeQueue.work');
    }

    /**
     * @return void
     */
    protected function registerWorker()
    {
        $this->registerWorkCommand();

        $this->app->singleton('safeQueue.worker', function ($app) {
            return new Worker(
                $app['queue'],
                $app['events'],
                $app['Illuminate\Contracts\Debug\ExceptionHandler'],
                fn() => app()->isDownForMaintenance(),
                $app['em'],
            );
        });
    }

    /**
     * @return void
     */
    protected function registerWorkCommand()
    {
        $this->app->singleton('command.safeQueue.work', function ($app) {
            $config = $app['config']->get('safequeue');
            /** @var CacheManager $cacheManager */
            $cacheManager = app()->get(CacheManager::class);
            $cache = $cacheManager->store($config['cache_store'] ?? null);

            return new WorkCommand($app['safeQueue.worker'], $cache, $config);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'safeQueue.worker',
            'command.safeQueue.work'
        ];
    }

    /**
     * @return bool
     */
    protected function isLumen()
    {
        return strpos($this->app->version(), 'Lumen') !== false;
    }
}
