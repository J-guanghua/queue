<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace guanghua\queue\conn;


use guanghua\queue\Queue as BaseQueue;

/**
 * Queue with CLI
 */
abstract class Queue extends BaseQueue
{

    public $messageHandler;

    /**
     * @inheritdoc
     */
    protected function handleMessage($id, $message, $ttr, $attempt)
    {
        if ($this->messageHandler) {
            return call_user_func($this->messageHandler, $id, $message, $ttr, $attempt);
        } else {
            return parent::handleMessage($id, $message, $ttr, $attempt);
        }
    }

    public function execute($id, $message, $ttr, $attempt)
    {
        return parent::handleMessage($id, $message, $ttr, $attempt);
    }
}