<?php

namespace queue\beanstalk;

use Pheanstalk\Exception\ServerException;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
use queue\Queue as BaseQueue;
use queue\base\Signal;


class Queue extends BaseQueue
{
    //连接主机
    public $host = 'localhost';
    
    //连接端口
    public $port = PheanstalkInterface::DEFAULT_PORT;
    
    //字符串beanstalk管
    public $tube = 'queue';

    //从队列运行所有作业。
    public function run()
    {
        while ($payload = $this->getPheanstalk()->reserveFromTube($this->tube, 0)) {
            $info = $this->getPheanstalk()->statsJob($payload);
            if ($this->handleMessage(
                $payload->getId(),
                $payload->getData(),
                $info->ttr,
                $info->reserves
            )) {
                $this->getPheanstalk()->delete($payload);
            }
        }
    }

    //监听队列并运行新作业。
    public function listen()
    {
        while (!Signal::isExit()) {
            if ($payload = $this->getPheanstalk()->reserveFromTube($this->tube, 3)) {
                $info = $this->getPheanstalk()->statsJob($payload);
                if ($this->handleMessage(
                    $payload->getId(),
                    $payload->getData(),
                    $info->ttr,
                    $info->reserves
                )) {
                    $this->getPheanstalk()->delete($payload);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function pushMessage($message, $ttr, $delay, $priority)
    {
        return $this->getPheanstalk()->putInTube(
            $this->tube,
            $message,
            $priority ?: PheanstalkInterface::DEFAULT_PRIORITY,
            $delay,
            $ttr
        );
    }

    /**
     * @inheritdoc
     */
    protected function status($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            throw new InvalidParamException("Unknown messages ID: $id.");
        }

        try {
            $stats = $this->getPheanstalk()->statsJob($id);
            if ($stats['state'] === 'reserved') {
                return self::STATUS_RESERVED;
            } else {
                return self::STATUS_WAITING;
            }
        } catch (ServerException $e) {
            if ($e->getMessage() === 'Server reported NOT_FOUND') {
                return self::STATUS_DONE;
            } else {
                throw $e;
            }
        }
    }

    //阵列管统计
    public function getStatsTube()
    {
        return $this->getPheanstalk()->statsTube($this->tube);
    }

    //Pheanstalk
    protected function getPheanstalk()
    {
        if (!$this->_pheanstalk) {
            $this->_pheanstalk = new Pheanstalk($this->host, $this->port);
        }
        return $this->_pheanstalk;
    }

    private $_pheanstalk;
}
