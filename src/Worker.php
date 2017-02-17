<?php


namespace MaxBrokman\SafeQueue;

use Doctrine\ORM\EntityManager;
use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
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
     * Worker constructor.
     *
     * @param QueueManager               $manager
     * @param FailedJobProviderInterface $failer
     * @param Dispatcher                 $events
     * @param EntityManager              $entityManager
     * @param ExceptionHandler           $exceptions
     */
    public function __construct(
        QueueManager $manager,
        FailedJobProviderInterface $failer,
        Dispatcher $events,
        EntityManager $entityManager,
        ExceptionHandler $exceptions
    )
    {
        parent::__construct($manager, $events, $exceptions);

        $this->entityManager = $entityManager;
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

            parent::runJob($job, $connectionName, $options);
        } catch (EntityManagerClosedException $e) {
            $this->exceptions->report($e);

            $this->stop(1);
        } catch (Exception $e) {
            $this->exceptions->report($e);

            $this->stop(1);
        } catch (Throwable $e) {
            $this->exceptions->report(new FatalThrowableError($e));

            $this->stop(1);
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
}
