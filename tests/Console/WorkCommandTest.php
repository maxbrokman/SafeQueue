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
     * @var string
     */
    private $defaultCommandName;

    /**
     * @var string
     */
    private $testNewCommandName;

    /**
     * @var array
     */
    private $configStub;

    protected function setUp()
    {
        $this->worker = m::mock(Worker::class);
        $this->configStub = ['command_name' => 'doctrine:queue:work'];
        $this->command = new WorkCommand($this->worker, $this->configStub);
        $this->defaultCommandName = 'doctrine:queue:work';
        $this->testNewCommandName = 'queue:work';
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
        $reflectedCommand = new ReflectionClass(get_class($this->command));
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
        $this->command = new WorkCommand($this->worker, [
            'command_name' => $this->testNewCommandName
        ]);

        $signature = $this->getPropertyViaReflection('signature');

        $this->assertEquals($this->testNewCommandName, $this->getCommandNameFromSignature($signature));
    }

    public function testCommandNameCanConfiguredToLaravelDefaultBySettingConfigValueToFalse()
    {
        $this->command = new WorkCommand($this->worker, [
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
