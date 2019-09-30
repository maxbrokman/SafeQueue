<?php


namespace MaxBrokman\SafeQueue;

use Doctrine\ORM\EntityManager;
use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Container\Container;
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
     * @param QueueManager     $manager
     * @param Dispatcher       $events
     * @param EntityManager    $entityManager
     * @param ExceptionHandler $exceptions
     * @param  \callable $isDownForMaintenance
     */
    public function __construct(
        QueueManager $manager,
        Dispatcher $events,
        EntityManager $entityManager,
        ExceptionHandler $exceptions,
        callable $isDownForMaintenance
    ) {
        parent::__construct($manager, $events, $exceptions, $isDownForMaintenance);

        $this->entityManager = $entityManager;
    }

    /**
     * Wrap parent::runJob to make sure we have a good EM.
     *
     * Most exception handling is done in the parent method, so we consider any new
     * exceptions to be a result of our setup.
     *
     * @param \Illuminate\Contracts\Queue\Job $job
     * @param string                          $connectionName
     * @param WorkerOptions                   $options
     */
    protected function runJob($job, $connectionName, WorkerOptions $options)
    {
        try {
            $this->assertEntityManagerOpen();
            $this->assertEntityManagerClear();
            $this->assertGoodDatabaseConnection();

            parent::runJob($job, $connectionName, $options);
        } catch (EntityManagerClosedException $e) {
            $this->exceptions->report($e);
            $this->stop(1);
        } catch (Exception $e) {
            $this->exceptions->report(new QueueSetupException("Error in queue setup while running a job", 0, $e));
            $this->stop(1);
        } catch (Throwable $e) {
            $this->exceptions->report(new QueueSetupException("Error in queue setup while running a job", 0, new FatalThrowableError($e)));
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
     * To clear the em before doing any work.
     */
    private function assertEntityManagerClear()
    {
        $this->entityManager->clear();
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
