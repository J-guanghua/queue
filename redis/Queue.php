<?php

namespace queue\redis;


use queue\base\Signal;
use queue\Queue as BaseQueue;

class Queue extends BaseQueue
{
	// redis 实例
    protected $redis;
    //连接主机
    public $hostname = 'localhost';

    public $database = 0;
    
    // 连接端口
    public $port = 6379;

    // 前缀名
    public $channel = 'queue';

	// inheritdoc
    public function init()
    {
        parent::init();
        $this->redis = new Redis([
            'hostname' => $this->hostname, // 连接主机
            'port'     => $this->port,     // 连接端口
            'database' => $this->database  // 连接数据库
        ]);

    }

	// 从redis-queue运行所有作业。
    public function run()
    {
        $this->openWorker();
        while (($payload = $this->reserve(0)) !== null) {
            list($id, $message, $ttr, $attempt) = $payload;
            if ($this->handleMessage($id, $message, $ttr, $attempt)) {
                $this->delete($id);
            }
        }
        $this->closeWorker();
    }

	// 侦听redis-queue并运行新作业。
    public function listen($wait)
    {
        $this->openWorker();
        while (!Signal::isExit()) {
            if (($payload = $this->reserve($wait)) !== null) {
                list($id, $message, $ttr, $attempt) = $payload;
                if ($this->handleMessage($id, $message, $ttr, $attempt)) {
                    $this->delete($id);
                }
            }
        }
        $this->closeWorker();
    }

    // 等待超时
    protected function reserve($wait)
    {
        // 将延迟的消息移动到等待状态
        if ($this->now < time()) {
            $this->now = time();
            $this->moveExpired("$this->channel.delayed", $this->now);
            $this->moveExpired("$this->channel.reserved", $this->now);
        }

        // 查找新的等待消息
        $id = null;
        if (!$wait) {
            $id = $this->redis->rpop("$this->channel.waiting");
        } elseif ($result = $this->redis->brpop("$this->channel.waiting", $wait)) {
            $id = $result[1];
        }
        if (!$id) {
            return null;
        }

        $payload = $this->redis->hget("$this->channel.messages", $id);
        list($ttr, $message) = explode(';', $payload, 2);
        $this->redis->zadd("$this->channel.reserved", time() + $ttr, $id);
        $attempt = $this->redis->hincrby("$this->channel.attempts", $id, 1);

        return [$id, $message, $ttr, $attempt];
    }

    private $now = 0;

    protected function moveExpired($from, $time)
    {
        if ($expired = $this->redis->zrevrangebyscore($from, $time, '-inf')) {
            $this->redis->zremrangebyscore($from, '-inf', $time);
            foreach ($expired as $id) {
                $this->redis->rpush("$this->channel.waiting", $id);
            }
        }
    }

	// 根据ID删除消息
    protected function delete($id)
    {
        $this->redis->zrem("$this->channel.reserved", $id);
        $this->redis->hdel("$this->channel.attempts", $id);
        $this->redis->hdel("$this->channel.messages", $id);
    }

    protected function pushMessage($message, $ttr, $delay, $priority)
    {
        if ($priority !== null) {
            throw new Exception('Job priority is not supported in the driver.');
        }

        $id = $this->redis->incr("$this->channel.message_id");
        $this->redis->hset("$this->channel.messages", $id, "$ttr;$message");
        if (!$delay) {
            $this->redis->lpush("$this->channel.waiting", $id);
        } else {
            $this->redis->zadd("$this->channel.delayed", time() + $delay, $id);
        }

        return $id;
    }

    protected function openWorker()
    {
        $id = $this->redis->incr("$this->channel.worker_id");
        $this->redis->clientSetname("$this->channel.worker.$id");
    }

    protected function closeWorker()
    {
        $this->redis->clientSetname('');
    }

    // 获取任务状态
    protected function status($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            throw new Exception("Unknown messages ID: $id.");
        }

        if ($this->redis->hexists("$this->channel.attempts", $id)) {
            return self::STATUS_RESERVED;
        } elseif ($this->redis->hexists("$this->channel.messages", $id)) {
            return self::STATUS_WAITING;
        } else {
            return self::STATUS_DONE;
        }
    }
}
