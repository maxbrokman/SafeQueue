<?php


namespace MaxBrokman\SafeQueue;

class Stopper
{
    public function stop()
    {
        exit; //@codeCoverageIgnore
    }
}
