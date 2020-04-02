<?php
/**
 *作者光华
 *文件队列
 */

namespace guanghua\queue;

use guanghua\queue\base\Monitor;
use guanghua\queue\base\Serializer;
use guanghua\queue\base\PhpSerializer;
use guanghua\queue\base\BaseVarDumper;

/**
 * Base Queue
 *
 * @author Roman Zhuravlev <zhuravljov@gmail.com>
 */
abstract class Queue extends Monitor
{
    /**
     * @event PushEvent
     */
    const EVENT_BEFORE_PUSH = 'beforePush';
    /**
     * @event PushEvent
     */
    const EVENT_AFTER_PUSH = 'afterPush';
    /**
     * @event ExecEvent
     */
    const EVENT_BEFORE_EXEC = 'beforeExec';
    /**
     * @event ExecEvent
     */
    const EVENT_AFTER_EXEC = 'afterExec';
    /**
     * @event ExecEvent
     */
    const EVENT_AFTER_ERROR = 'afterError';
    /**
     * @see Queue::isWaiting()
     */
    const STATUS_WAITING = 1;
    /**
     * @see Queue::isReserved()
     */
    const STATUS_RESERVED = 2;
    /**
     * @see Queue::isDone()
     */
    const STATUS_DONE = 3;

    /**
     * @var 序列化类
     */
    public $serializer = PhpSerializer::class;
    /**
     * @var 保留作业的默认时间
     */
    public $ttr = 300;
    /**
     * @var 默认尝试计数
     */
    public $attempts = 1;

    private $pushTtr;
    private $pushDelay;
    private $pushPriority;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->serializer = new $this->serializer;
    }

    /**
     * 为作业执行设置延迟时间
     */
    public function ttr($value)
    {
        $this->pushTtr = $value;
        return $this;
    }

    /**
     * 为以后的执行设置延迟
     */
    public function delay($value)
    {
        $this->pushDelay = $value;
        return $this;
    }

    /**
     * 集工作优先级
     */
    public function priority($value)
    {
        $this->pushPriority = $value;
        return $this;
    }

    //将作业推入队列 并返回作业ID
    public function push($job)
    {    
        $event = new PushEvent([
            'job' => $job,
            'ttr' => $job instanceof RetryableJob
                ? $job->getTtr()
                : ($this->pushTtr ?: $this->ttr),
            'delay' => $this->pushDelay ?: 0,
            'priority' => $this->pushPriority,
        ]);

        $this->pushTtr = null;
        $this->pushDelay = null;
        $this->pushPriority = null;

        $this->trigger(self::EVENT_BEFORE_PUSH, $event);
 
        $message = $this->serializer->serialize($event->job);
        $event->id = $this->pushMessage($message, $event->ttr, $event->delay, $event->priority);
        $this->trigger(self::EVENT_AFTER_PUSH, $event);
        return $event->id;
    }

    //添加作业接口 返回消息的id
    abstract protected function pushMessage($message, $ttr, $delay, $priority);

    //执行 队列作业方法
    protected function handleMessage($id, $message, $ttr, $attempt)
    {
        $job = $this->serializer->unserialize($message);

        if (!($job instanceof Job)) {
            throw new \Exception(strtr('Job must be {class} object instead of {dump}.', [
                '{class}' => Job::class,
                '{dump}' => get_class($job),
            ]));
        }
        $event = new ExecEvent([
            'id' => $id,
            'job' => $job,
            'ttr' => $ttr,
            'attempt' => $attempt,
        ]);

        try {
            $event->job->execute($this);
        } catch (\Exception $error) {
            return $this->handleError($event->id, $event->job, $event->ttr, $event->attempt, $error);
        }
        $this->trigger(self::EVENT_AFTER_EXEC, $event);

        return true;
    }

    //队列作业 执行异常处理
    public function handleError($id, $job, $ttr, $attempt, $error)
    {
        $event = new ErrorEvent([
            'id' => $id,
            'job' => $job,
            'ttr' => $ttr,
            'attempt' => $attempt,
            'error' => $error,
            'retry' => $job instanceof RetryableJob
                ? $job->canRetry($attempt, $error)
                : $attempt < $this->attempts,
        ]);
        $this->trigger(self::EVENT_AFTER_ERROR, $event);
        return !$event->retry;
    }

    /**
     * @param 检查作业是否正在等待执行。
     * @return bool
     */
    public function isWaiting($id)
    {
        return $this->status($id) === Queue::STATUS_WAITING;
    }

    /**
     * @param 检查工作人员是否从队列中获取了该作业并执行该作业。
     * @return bool
     */
    public function isReserved($id)
    {
        return $this->status($id) === Queue::STATUS_RESERVED;
    }

    /**
     * @param 检查工人是否执行了作业。
     * @return bool
     */
    public function isDone($id)
    {
        return $this->status($id) === Queue::STATUS_DONE;
    }

    /**
     * @return int status code
     */
    abstract protected function status($id);
}
