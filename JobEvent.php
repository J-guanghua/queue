<?php
namespace queue;

use queue\base\Basics;

/**
 * Class JobEvent
 */
class JobEvent extends Basics
{
    //作业的唯一id为空
    public $id;

    // Job
    public $job;
    
    //预留的时间以秒为单位
    public $ttr;
}