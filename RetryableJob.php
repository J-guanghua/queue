<?php

namespace queue;

/**
 * Interface RetryableJob
 */

interface RetryableJob extends Job
{
    //时间以秒为单位保留
    public function getTtr();

    //来自最后一次执行作业的错误
    public function canRetry($attempt, $error);
}