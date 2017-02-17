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
     *
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
    )
    {
        parent::__construct($manager, $events, $exceptions);

        $this->entityManager = $entityManager;
        $this->stopper = $stopper;
    }

    /**
     * We clear the entity manager, assert that it's open and also assert that
     * the database has an open connection before processing the current job.
     *
     * @param  \Illuminate\Contracts\Queue\Job $job
     * @param  string                          $connectionName
     * @param  \Illuminate\Queue\WorkerOptions $options
     * @return void
     */
    protected function runJob($job, $connectionName, WorkerOptions $options)
    {
        $this->entityManager->clear();

        try {
            $this->assertEntityManagerOpen();
            $this->assertGoodDatabaseConnection();

            $this->process($connectionName, $job, $options);

        } catch (EntityManagerClosedException $e) {
            if ($this->exceptions) {
                $this->exceptions->report(new EntityManagerClosedException);
            }

            $this->stop();
        } catch (Exception $e) {
            if ($this->exceptions) {
                $this->exceptions->report($e);
            }

            if ($e instanceof QueueMustStop) {
                $this->stop();
            }
        } catch (Throwable $e) {
            if ($this->exceptions) {
                $this->exceptions->report(new FatalThrowableError($e));
            }

            if ($e instanceof QueueMustStop) {
                $this->stop();
            }
        }
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

    /**
     * Overridden to allow testing.
     *
     * @param int $status
     */
    public function stop($status = 0)
    {
        $this->events->fire(new WorkerStopping);

        $this->stopper->stop($status);
    }
}
