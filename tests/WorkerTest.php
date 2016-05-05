<?php


namespace tests\MaxBrokman\SafeQueue;

use Doctrine\ORM\EntityManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Worker as IlluminateWorker;
use MaxBrokman\SafeQueue\Worker;
use Mockery as m;

class WorkerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var QueueManager|m\MockInterface
     */
    private $queueManager;

    /**
     * @var FailedJobProviderInterface|m\MockInterface
     */
    private $failedJobs;

    /**
     * @var Dispatcher|m\MockInterface
     */
    private $dispatcher;

    /**
     * @var EntityManager|m\MockInterface
     */
    private $entityManager;

    /**
     * @var Repository|m\MockInterface
     */
    private $cache;

    /**
     * @var Worker
     */
    private $worker;

    protected function setUp()
    {
        $this->queueManager  = m::mock(QueueManager::class);
        $this->failedJobs    = m::mock(FailedJobProviderInterface::class);
        $this->dispatcher    = m::mock(Dispatcher::class);
        $this->entityManager = m::mock(EntityManager::class);
        $this->cache         = m::mock(Repository::class);

        $this->worker = new Worker($this->queueManager, $this->failedJobs, $this->dispatcher, $this->entityManager);
        $this->worker->setCache($this->cache);
    }

    protected function tearDown()
    {
        m::close();
    }

    public function testExtendsLaravelWorker()
    {
        $this->assertInstanceOf(IlluminateWorker::class, $this->worker);
    }
}
