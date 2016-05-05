<?php


namespace MaxBrokman\SafeQueue\Console;

use Illuminate\Queue\Console\WorkCommand as IlluminateWorkCommand;
use MaxBrokman\SafeQueue\Worker;

class WorkCommand extends IlluminateWorkCommand
{
    /**
     * The console command name
     *
     * @var string
     */
    protected $name = 'doctrine:queue:work';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process the next job on a queue in a way that\'s safe for Doctrine';

    /**
     * WorkCommand constructor.
     * @param Worker $worker
     */
    public function __construct(Worker $worker)
    {
        parent::__construct($worker);
    }
}
