<?php


namespace MaxBrokman\SafeQueue;

class Stopper
{
    /**
     * @param int $status
     */
    public function stop($status = 0)
    {
        exit($status); //@codeCoverageIgnore
    }
}
