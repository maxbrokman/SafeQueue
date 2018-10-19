<?php declare(strict_types=1);

namespace MaxBrokman\SafeQueue\Exceptions;

use MaxBrokman\SafeQueue\QueueFailure;
use Throwable;

class QueueFailureException extends \Exception implements QueueFailure
{
    public function __construct(Throwable $previous = null)
    {
        parent::__construct("Unable to fetch job from queue", 500, $previous);
    }
}
