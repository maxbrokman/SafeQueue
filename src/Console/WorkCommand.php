<?php


namespace MaxBrokman\SafeQueue\Console;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Queue\Console\WorkCommand as IlluminateWorkCommand;
use MaxBrokman\SafeQueue\Worker;

class WorkCommand extends IlluminateWorkCommand
{
    const SIGNATURE_REGEX_PATTERN = '/([\w:-]+)(?=\s|\{)/i';

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
     * @param Cache  $cache
     * @param array  $config
     */
    public function __construct(Worker $worker, Cache $cache, $config)
    {
        $this->renameCommandInSignature($config['command_name']);

        parent::__construct($worker, $cache);
    }

    public function renameCommandInSignature($commandName)
    {
        if ($commandName) {
            /**
             * RegEx matches signature from the Laravel Worker Command that we're extending
             * from. Captures 1+ word characters (a-zA-Z0-9_) and/or literal colon (:),
             * and\or literal hyphen (-), up to the first white-space character or a
             * literal opening curly brace ({). The match is then replaced with the
             * command name from config, and the result is a renamed signature.
             */
            $this->signature = preg_replace(
                self::SIGNATURE_REGEX_PATTERN,
                $commandName,
                $this->signature,
                1
            );
        }
    }
}
