<?php


namespace tests\MaxBrokman\SafeQueue\Console;

use MaxBrokman\SafeQueue\Console\WorkCommand;
use MaxBrokman\SafeQueue\Worker;
use Mockery as m;
use ReflectionClass;

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

    /**
     * @var ReflectionClass
     */
    private $reflectedCommand;

    /**
     * @var string
     */
    private $intendedCommandName;

    protected function setUp()
    {
        $this->worker = m::mock(Worker::class);
        $this->command = new WorkCommand($this->worker);
        $this->intendedCommandName = 'doctrine:queue:work';

        // Use reflection to peek at the worker
        $this->reflectedCommand = new ReflectionClass(get_class($this->command));
    }

    public function testHasCorrectWorker()
    {
        $reflectionProperty = $this->reflectedCommand->getProperty('worker');
        $reflectionProperty->setAccessible(true);

        $worker = $reflectionProperty->getValue($this->command);

        $this->assertSame($this->worker, $worker);
    }

    public function testCommandNameIsCorrect()
    {
        $reflectionProperty = $this->reflectedCommand->getProperty('signature');
        $reflectionProperty->setAccessible(true);

        $signature = $reflectionProperty->getValue($this->command);

        $this->assertContains($this->intendedCommandName, $signature);
    }
}
