<?php

namespace queue;

/**
 * Interface Job
 */
interface Job
{
    /**
     * @param 它推动并处理作业
     */
    public function execute($queue);
}