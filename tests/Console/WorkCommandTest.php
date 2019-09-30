<?php


namespace tests\MaxBrokman\SafeQueue\Console;

use Illuminate\Contracts\Cache\Repository as Cache;
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
     * @var Cache|m\MockInterface
     */
    private $cache;

    /**
     * @var WorkCommand
     */
    private $command;

    /**
     * @var string
     */
    private $defaultCommandName;

    /**
     * @var array
     */
    private $testNewCommandNames;

    /**
     * @var array
     */
    private $configStub;

    protected function setUp()
    {
        $this->worker              = m::mock(Worker::class);
        $this->cache               = m::mock(Cache::class);
        $this->defaultCommandName  = 'doctrine:queue:work';
        $this->configStub          = ['command_name' => $this->defaultCommandName];
        $this->command             = new WorkCommand(
            $this->worker,
            $this->cache,
            $this->configStub
        );
        $this->testNewCommandNames = [
            'queue:work',
            'custom-queue:work',
            'doctrine-queue-work',
            'doctrine123-queue-work',
        ];
    }

    public function testHasCorrectWorker()
    {
        $worker = $this->getPropertyViaReflection('worker');

        $this->assertSame($this->worker, $worker);
    }

    /**
     * @param $propertyName
     * @return mixed
     */
    private function getPropertyViaReflection($propertyName)
    {
        // Use reflection to peek at the worker
        $reflectedCommand   = new ReflectionClass(get_class($this->command));
        $reflectionProperty = $reflectedCommand->getProperty($propertyName);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($this->command);
    }

    public function testCommandNameIsCorrectAsDefault()
    {
        $signature = $this->getPropertyViaReflection('signature');

        $this->assertEquals($this->defaultCommandName, $this->getCommandNameFromSignature($signature));
    }

    public function testCommandNameCanBeConfigured()
    {
        foreach ($this->testNewCommandNames as $newCommandName) {
            $this->command = new WorkCommand($this->worker, $this->cache, [
                'command_name' => $newCommandName
            ]);

            $signature = $this->getPropertyViaReflection('signature');

            $this->assertEquals($newCommandName, $this->getCommandNameFromSignature($signature));
        }
    }

    public function testCommandNameCanConfiguredToLaravelDefaultBySettingConfigValueToFalse()
    {
        $this->command = new WorkCommand($this->worker, $this->cache, [
            'command_name' => false
        ]);

        $signature = $this->getPropertyViaReflection('signature');

        $this->assertEquals('queue:work', $this->getCommandNameFromSignature($signature));
    }

    private function getCommandNameFromSignature($signature)
    {
        preg_match(WorkCommand::SIGNATURE_REGEX_PATTERN, $signature, $matches);

        return $matches[1];
    }
}
