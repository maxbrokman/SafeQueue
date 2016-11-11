<?php


namespace MaxBrokman\SafeQueue\Console;

use Illuminate\Queue\Console\WorkCommand as IlluminateWorkCommand;
use MaxBrokman\SafeQueue\Worker;

class WorkCommand extends IlluminateWorkCommand
{
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process the next job on a queue in a way that\'s safe for Doctrine';

    /**
     * WorkCommand constructor.
     *
     * @param Worker $worker
     * @param array  $config
     */
    public function __construct(Worker $worker, $config)
    {
        $this->renameCommandInSignature($config['command_name']);

        parent::__construct($worker);
    }

    public function renameCommandInSignature($commandName)
    {
        if ($commandName) {
            $this->signature = preg_replace(
                '/([\w\:]+)(?=\s|\{)/i', $commandName,  $this->signature, 1
            );
        }
    }
}
