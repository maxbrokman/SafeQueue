<?php


namespace tests\MaxBrokman\SafeQueue;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler as Handler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Worker as IlluminateWorker;
use Illuminate\Queue\WorkerOptions;
use MaxBrokman\SafeQueue\Worker;
use Mockery as m;

class WorkerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var QueueManager|m\MockInterface
     */
    private $queueManager;

    /**
     * @var Queue|m\MockInterface
     */
    private $queue;

    /**
     * @var Dispatcher|m\MockInterface
     */
    private $dispatcher;

    /**
     * @var EntityManager|m\MockInterface
     */
    private $entityManager;

    /**
     * @var Connection|m\MockInterface
     */
    private $dbConnection;

    /**
     * @var Repository|m\MockInterface
     */
    private $cache;

    /**
     * @var Handler|m\MockInterface
     */
    private $exceptions;

    /**
     * @var ManagerRegistry|m\MockInterface
     */
    private $managerRegistry;

    /**
     * @var Worker
     */
    private $worker;

    /**
     * @var WorkerOptions
     */
    private $options;

    protected function setUp()
    {
        $this->queueManager  = m::mock(QueueManager::class);
        $this->queue         = m::mock(Queue::class);
        $this->dispatcher    = m::mock(Dispatcher::class);
        $this->entityManager = m::mock(EntityManager::class);
        $this->dbConnection  = m::mock(Connection::class);
        $this->cache         = m::mock(Repository::class);
        $this->exceptions    = m::mock(Handler::class);
        $this->managerRegistry = m::mock(ManagerRegistry::class);

        $this->worker = new Worker($this->queueManager, $this->dispatcher, $this->managerRegistry, $this->exceptions);

        $this->options = new WorkerOptions(0, 128, 0, 0, 0);

        // Not interested in events
        $this->dispatcher->shouldIgnoreMissing();

        // EM always has connection available
        $this->entityManager->shouldReceive('getConnection')->andReturn($this->dbConnection);
    }

    protected function tearDown()
    {
        m::close();
    }

    protected function prepareToRunJob($job)
    {
        if ($job instanceof Job) {
            $jobs = [$job];
        } else {
            $jobs = $job;
        }

        $this->queueManager->shouldReceive('isDownForMaintenance')->andReturn(false);
        $this->queueManager->shouldReceive('connection')->andReturn($this->queue);
        $this->queueManager->shouldReceive('getName')->andReturn('test');

        $this->queue->shouldReceive('pop')->andReturn(...$jobs);
    }

    public function testExtendsLaravelWorker()
    {
        $this->assertInstanceOf(IlluminateWorker::class, $this->worker);
    }

    public function testChecksEmState()
    {
        $job = m::mock(Job::class);
        $job->shouldReceive('fire')->once();
        $job->shouldIgnoreMissing();

        $this->prepareToRunJob($job);

        // Must make sure em is open
        $this->entityManager->shouldReceive('isOpen')->once()->andReturn(true);

        // Em must be cleared
        $this->entityManager->shouldReceive('clear')->once();

        // Must re-open db connection
        $this->dbConnection->shouldReceive('ping')->once()->andReturn(false);
        $this->dbConnection->shouldReceive('close')->once();
        $this->dbConnection->shouldReceive('connect')->once();

        $this->managerRegistry->shouldReceive('getManagers')->andReturn(array($this->entityManager));

        $this->worker->runNextJob('connection', 'queue', $this->options);
    }

    public function testMultipleEntityManagers() {
        $job = m::mock(Job::class);
        $job->shouldReceive('fire')->once();
        $job->shouldIgnoreMissing();

        $this->prepareToRunJob($job);

        $this->entityManager->shouldReceive('isOpen')->once()->andReturn(true);
        $this->entityManager->shouldReceive('clear')->once();

        $this->dbConnection->shouldReceive('ping')->once()->andReturn(false);
        $this->dbConnection->shouldReceive('close')->once();
        $this->dbConnection->shouldReceive('connect')->once();

        $secondConnection = m::mock(Connection::class);
        $secondConnection->shouldReceive('ping')->once()->andReturn(false);
        $secondConnection->shouldReceive('close')->once();
        $secondConnection->shouldReceive('connect')->once();

        $secondManager = m::mock(EntityManagerInterface::class);
        $secondManager->shouldReceive('getConnection')->andReturn($secondConnection);
        $secondManager->shouldReceive('isOpen')->once()->andReturn(true);
        $secondManager->shouldReceive('clear')->once();

        $this->managerRegistry->shouldReceive('getManagers')->andReturn(array($this->entityManager, $secondManager));

        $this->worker->runNextJob('connection', 'queue', $this->options);
    }
}
