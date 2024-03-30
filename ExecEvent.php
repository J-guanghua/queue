<?php

namespace queue;

/**
 * Class ExecEvent
 */
class ExecEvent extends JobEvent
{
	//int尝试次数
    public $attempt;
}