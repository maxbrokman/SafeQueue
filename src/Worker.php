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

class Worker extends IlluminateWorker
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * Worker constructor.
     * @param QueueManager               $manager
     * @param FailedJobProviderInterface $failer
     * @param Dispatcher                 $events
     * @param EntityManager              $entityManager
     */
    public function __construct(
        QueueManager $manager,
        FailedJobProviderInterface $failer,
        Dispatcher $events,
        EntityManager $entityManager
    ) {
        parent::__construct($manager, $failer, $events);

        $this->entityManager = $entityManager;
    }

    /**
     * The parent class implementation of this method just carries on in case of error, but this
     * could potentially leave the entity manager in a bad state.
     *
     * We also clear the entity manager before working a job for good measure, this will help us better
     * simulate a single request model.
     *
     * @param string $connectionName
     * @param string $queue
     * @param int    $delay
     * @param int    $sleep
     * @param int    $maxTries
     */
    protected function runNextJobForDaemon($connectionName, $queue, $delay, $sleep, $maxTries)
    {
        $this->entityManager->clear();

        try {
            $this->pop($connectionName, $queue, $delay, $sleep, $maxTries);
        } catch (Exception $e) {
            if ($this->exceptions) {
                $this->exceptions->report($e);
            }
        } catch (Throwable $e) {
            if ($this->exceptions) {
                $this->exceptions->report(new FatalThrowableError($e));
            }
        } finally {
            if (!$this->entityManager->isOpen()) {
                $this->stop();
            }
        }
    }
}
