<?php


namespace tests\MaxBrokman\SafeQueue\Console;

use MaxBrokman\SafeQueue\Console\WorkCommand;
use MaxBrokman\SafeQueue\Worker;
use Mockery as m;

class WorkCommandTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Worker|m\MockInterface
     */
    private $worker;

    /**
     * @var WorkCommand
     */
    private $command;

    protected function setUp()
    {
        $this->worker  = m::mock(Worker::class);
        $this->command = new WorkCommand($this->worker);
    }

    public function testHasCorrectWorker()
    {
        // Use reflection to peek at the worker
        $reflectionClass    = new \ReflectionClass(get_class($this->command));
        $reflectionProperty = $reflectionClass->getProperty('worker');
        $reflectionProperty->setAccessible(true);

        $worker = $reflectionProperty->getValue($this->command);

        $this->assertSame($this->worker, $worker);
    }
}
