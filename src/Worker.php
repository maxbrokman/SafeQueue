<?php


namespace MaxBrokman\SafeQueue;

use Doctrine\ORM\EntityManager;
use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Worker as IlluminateWorker;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Throwable;

final class Worker extends IlluminateWorker
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
     */
    public function __construct(
        QueueManager $manager,
        FailedJobProviderInterface $failer,
        Dispatcher $events,
        EntityManager $entityManager,
        Stopper $stopper
    ) {
        parent::__construct($manager, $failer, $events);

        $this->entityManager = $entityManager;
        $this->stopper       = $stopper;
    }

    /**
     * Listen to the given queue and work jobs from it without re-booting the framework.
     *
     * This is a slight re-working of the parent implementation to aid testing.
     *
     * @param  string $connectionName
     * @param  null   $queue
     * @param  int    $delay
     * @param  int    $memory
     * @param  int    $sleep
     * @param  int    $maxTries
     * @return void
     */
    public function daemon($connectionName, $queue = null, $delay = 0, $memory = 128, $sleep = 3, $maxTries = 0)
    {
        $lastRestart = $this->getTimestampOfLastQueueRestart();

        while (true) {
            if ($this->daemonShouldRun()) {
                $canContinue = $this->runNextJobForDaemon(
                    $connectionName, $queue, $delay, $sleep, $maxTries
                );

                if ($canContinue === false) {
                    break;
                }
            } else {
                $this->sleep($sleep);
            }

            if ($this->memoryExceeded($memory) || $this->queueShouldRestart($lastRestart)) {
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
        $this->stopper->stop();
    }

    /**
     * The parent class implementation of this method just carries on in case of error, but this
     * could potentially leave the entity manager in a bad state.
     *
     * We also clear the entity manager before working a job for good measure, this will help us better
     * simulate a single request model.
     *
     * @param  string $connectionName
     * @param  string $queue
     * @param  int    $delay
     * @param  int    $sleep
     * @param  int    $maxTries
     * @return bool
     */
    protected function runNextJobForDaemon($connectionName, $queue, $delay, $sleep, $maxTries)
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
            $this->daemonPop($connectionName, $queue, $delay, $sleep, $maxTries);
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

        return true;
    }

    /**
     * We also have to override this method, because Laravel swallows exceptions down here as well (presumably
     * because this is shared between work and daemon)
     *
     * @param string $connectionName
     * @param string $queue
     * @param int $delay
     * @param int $sleep
     * @param int $maxTries
     * @return array
     */
    private function daemonPop($connectionName, $queue = null, $delay = 0, $sleep = 3, $maxTries = 0)
    {
        $connection = $this->manager->connection($connectionName);

        $job = $this->getNextJob($connection, $queue);

        // If we're able to pull a job off of the stack, we will process it and
        // then immediately return back out. If there is no job on the queue
        // we will "sleep" the worker for the specified number of seconds.
        if (!is_null($job)) {
            return $this->process(
                $this->manager->getName($connectionName), $job, $maxTries, $delay
            );
        }

        $this->sleep($sleep);

        return ['job' => null, 'failed' => false];
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
