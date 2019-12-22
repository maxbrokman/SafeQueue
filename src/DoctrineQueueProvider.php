<?php


namespace MaxBrokman\SafeQueue;

use Illuminate\Support\ServiceProvider;
use MaxBrokman\SafeQueue\Console\WorkCommand;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Debug\ExceptionHandler;

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
            __DIR__ . '/../config/safequeue.php',
            'safequeue'
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
            $isDownForMaintenance = function () {
                return $this->app->isDownForMaintenance();
            };
            
            return new Worker(
                $app['queue'],
                $app['events'],
                $app['em'],
                $app[ExceptionHandler::class],
                $isDownForMaintenance
            );
        });
    }

    /**
     * @return void
     */
    protected function registerWorkCommand()
    {
        $this->app->singleton('command.safeQueue.work', function ($app) {
            return new WorkCommand(
                $app['safeQueue.worker'],
                $app[Cache::class],
                $app['config']->get('safequeue')
            );
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
