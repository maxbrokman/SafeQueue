<?php


namespace tests\MaxBrokman\SafeQueue;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler as Handler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Worker as IlluminateWorker;
use MaxBrokman\SafeQueue\EntityManagerClosedException;
use MaxBrokman\SafeQueue\QueueMustStop;
use MaxBrokman\SafeQueue\Stopper;
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
     * @var Stopper|m\MockInterface
     */
    private $stopper;
    /**
     * @var Worker
     */
    private $worker;

    protected function setUp()
    {
        $this->queueManager  = m::mock(QueueManager::class);
        $this->queue         = m::mock(Queue::class);
        $this->failedJobs    = m::mock(FailedJobProviderInterface::class);
        $this->dispatcher    = m::mock(Dispatcher::class);
        $this->entityManager = m::mock(EntityManager::class);
        $this->dbConnection  = m::mock(Connection::class);
        $this->cache         = m::mock(Repository::class);
        $this->exceptions    = m::mock(Handler::class);
        $this->stopper       = m::mock(Stopper::class);

        $this->worker = new Worker($this->queueManager, $this->failedJobs, $this->dispatcher, $this->entityManager,
            $this->stopper);

        $this->worker->setDaemonExceptionHandler($this->exceptions);

        // Not interested in events
        $this->dispatcher->shouldIgnoreMissing();

        // EM will get cleared every run
        $this->entityManager->shouldReceive('clear');

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

        $popExpectation = $this->queue->shouldReceive('pop');
        call_user_func_array([$popExpectation, 'andReturn'], $jobs);
    }

    public function testExtendsLaravelWorker()
    {
        $this->assertInstanceOf(IlluminateWorker::class, $this->worker);
    }

    public function testStopWhenEntityManagerClosed()
    {
        // Entity manager will report closed but will have a connection
        $this->entityManager->shouldReceive('isOpen')->andReturn(false);
        $this->dbConnection->shouldReceive('ping')->andReturn(true);

        // We must log this fact
        $this->exceptions->shouldReceive('report')->with(m::type(EntityManagerClosedException::class))->once();

        // We must stop
        $this->stopper->shouldReceive('stop')->once();

        // Make a job
        $job = m::mock(Job::class);
        $job->shouldReceive('fire')->never();
        $this->prepareToRunJob($job);

        $this->worker->daemon('test', null, 0, 128, 3, 0);
    }

    public function testReconnected()
    {
        // Entity manager will report open but has no connection, must reconnect
        $this->entityManager->shouldReceive('isOpen')->andReturn(true);

        $this->dbConnection->shouldReceive('ping')->andReturn(false);
        $this->dbConnection->shouldReceive('close')->once();
        $this->dbConnection->shouldReceive('connect')->once();

        // We must stop
        $this->stopper->shouldReceive('stop')->once();
        // We must log this fact
        $this->exceptions->shouldReceive('report')->with(m::type(BadThingHappened::class))->once();

        // Make a job
        $job = m::mock(Job::class);

        // Needed just to make it stop
        $job->shouldReceive('fire')->once()->andThrow(new BadThingHappened());
        $job->shouldIgnoreMissing();
        $this->prepareToRunJob($job);

        $this->worker->daemon('test', null, 0, 128, 3, 0);
    }

    public function testLoops()
    {
        // Entity manager will report open and good connection
        $this->entityManager->shouldReceive('isOpen')->andReturn(true)->times(2);
        $this->dbConnection->shouldReceive('ping')->andReturn(true)->times(2);

        // We must stop
        $this->stopper->shouldReceive('stop')->once();

        // Make a job
        $jobOne = m::mock(Job::class);
        $jobTwo = m::mock(Job::class);

        $jobOne->shouldReceive('fire')->once();
        $jobOne->shouldIgnoreMissing();

        $jobTwo->shouldReceive('fire')->once()->andThrow(new BadThingHappened());
        $jobTwo->shouldIgnoreMissing();

        $this->exceptions->shouldReceive('report')->with(m::type(BadThingHappened::class))->once();

        $this->prepareToRunJob([$jobOne, $jobTwo]);

        $this->worker->daemon('test', null, 0, 128, 3, 0);
    }

    public function testRestartsNicely()
    {
        $this->worker->setCache($this->cache);

        // Different times during a job is the restart condition
        $this->cache->shouldReceive('get')->with('illuminate:queue:restart')->andReturn(1, 2);

        // Force it to not run
        $this->queueManager->shouldReceive('isDownForMaintenance')->andReturn(true);

        // We must stop
        $this->stopper->shouldReceive('stop')->once();

        $this->worker->daemon('test', null, 0, 128, 0, 0);
    }
}

class BadThingHappened extends \Exception implements QueueMustStop
{
}
