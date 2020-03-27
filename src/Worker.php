<?php


namespace MaxBrokman\SafeQueue;

use Doctrine\ORM\EntityManager;
use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Worker as IlluminateWorker;
use Illuminate\Queue\WorkerOptions;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Throwable;

/*final*/ class Worker extends IlluminateWorker
{
    /**
     * @var EntityManager
     */
    private $entityManager;
    /**
     * @var Stopper
     */
    private $stopper;

    /**
     * Worker constructor.
     * @param QueueManager               $manager
     * @param FailedJobProviderInterface $failer
     * @param Dispatcher                 $events
     * @param EntityManager              $entityManager
     * @param Stopper                    $stopper
     * @param ExceptionHandler           $exceptions
     */
    public function __construct(
        QueueManager $manager,
        FailedJobProviderInterface $failer,
        Dispatcher $events,
        EntityManager $entityManager,
        Stopper $stopper,
        ExceptionHandler $exceptions
    ) {
        parent::__construct($manager, $events, $exceptions);

        $this->entityManager = $entityManager;
        $this->stopper       = $stopper;
    }

    /**
     * Listen to the given queue and work jobs from it without re-booting the framework.
     *
     * This is a slight re-working of the parent implementation to aid testing.
     *
     * @param  string                          $connectionName
     * @param  null                            $queue
     * @param  \Illuminate\Queue\WorkerOptions $options
     * @return void
     */
    public function daemon($connectionName, $queue, WorkerOptions $options)
    {
        $lastRestart = $this->getTimestampOfLastQueueRestart();

        while (true) {
            $this->registerTimeoutHandler($options);

            if ($this->daemonShouldRun($options)) {
                $canContinue = $this->runNextJob(
                    $connectionName, $queue, $options
                );

                if ($canContinue === false) {
                    break;
                }
            } else {
                $this->sleep($options->sleep);
            }

            if ($this->memoryExceeded($options->memory) || $this->queueShouldRestart($lastRestart)) {
                break;
            }
        }

        $this->stop();
    }

    /**
     * Overridden to allow testing.
     */
    public function stop()
    {
        $this->events->fire(new WorkerStopping);

        $this->stopper->stop();
    }

    /**
     * We clear the entity manager, assert that it's open and also assert that
     * the database has an open connection before running each job.
     *
     * @param  string                          $connectionName
     * @param  string                          $queue
     * @param  \Illuminate\Queue\WorkerOptions $options
     * @return bool
     */
    public function runNextJob($connectionName, $queue, WorkerOptions $options)
    {
        $this->entityManager->clear();

        try {
            $this->assertEntityManagerOpen();
        } catch (EntityManagerClosedException $e) {
            if ($this->exceptions) {
                $this->exceptions->report(new EntityManagerClosedException);
            }

            return false;
        }

        $this->assertGoodDatabaseConnection();

        try {
            $job = $this->getNextJob(
                $this->manager->connection($connectionName), $queue
            );

            // If we're able to pull a job off of the stack, we will process it and then return
            // from this method. If there is no job on the queue, we will "sleep" the worker
            // for the specified number of seconds, then keep processing jobs after sleep.
            if ($job) {
                $this->process(
                    $connectionName, $job, $options
                );

                return true;
            }

        } catch (Exception $e) {
            if ($this->exceptions) {
                $this->exceptions->report($e);
            }

            if ($e instanceof QueueMustStop) {
                return false;
            }
        } catch (Throwable $e) {
            if ($this->exceptions) {
                $this->exceptions->report(new FatalThrowableError($e));
            }

            if ($e instanceof QueueMustStop) {
                return false;
            }
        }

        $this->sleep($options->sleep);

        return true;
    }

    /**
     * @throws EntityManagerClosedException
     */
    private function assertEntityManagerOpen()
    {
        if ($this->entityManager->isOpen()) {
            return;
        }

        throw new EntityManagerClosedException;
    }

    /**
     * Some database systems close the connection after a period of time, in MySQL this is system variable
     * `wait_timeout`. Given the daemon is meant to run indefinitely we need to make sure we have an open
     * connection before working any job. Otherwise we would see `MySQL has gone away` type errors.
     */
    private function assertGoodDatabaseConnection()
    {
        $connection = $this->entityManager->getConnection();

        if ($connection->ping() === false) {
            $connection->close();
            $connection->connect();
        }
    }
}
