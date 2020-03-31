<?php

namespace guanghua\queue;

/**
 * Class PushEvent
 *
 * @author Roman Zhuravlev <zhuravljov@gmail.com>
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