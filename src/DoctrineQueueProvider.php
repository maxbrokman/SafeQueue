<?php


namespace MaxBrokman\SafeQueue;

use Illuminate\Support\ServiceProvider;
use MaxBrokman\SafeQueue\Console\WorkCommand;

/**
 * @codeCoverageIgnore
 */
class DoctrineQueueProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerWorker();
    }

    /**
     * @return void
     */
    protected function registerWorker()
    {
        $this->registerWorkCommand();

        $this->app->singleton('safeQueue.worker', function ($app) {
            return new Worker($app['queue'], $app['queue.failer'], $app['events'],
                $app['em'], new Stopper(), $app['Illuminate\Contracts\Debug\ExceptionHandler']);
        });
    }

    /**
     * @return void
     */
    protected function registerWorkCommand()
    {
        $this->app->singleton('command.safeQueue.work', function ($app) {
            return new WorkCommand($app['safeQueue.worker']);
        });

        $this->commands('command.safeQueue.work');
    }
}
