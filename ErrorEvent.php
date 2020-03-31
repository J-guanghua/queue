<?php

namespace guanghua\queue;

/**
 * Class ErrorEvent
 */
class ErrorEvent extends ExecEvent
{
    /**
     * @var \Exception
     */
    public $error;
    /**
     * @var bool
     */
    public $retry;
}