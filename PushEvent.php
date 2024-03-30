<?php

namespace queue;

/**
 * Class PushEvent
 */
class PushEvent extends JobEvent
{
    /**
     * @var int
     */
    public $delay;
    /**
     * @var mixed
     */
    public $priority;
}