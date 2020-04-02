<?php
/**
 *作者光华
 *文件队列
 */

namespace guanghua\queue\file;

use guanghua\queue\base\Signal;
use guanghua\queue\Queue as ConnQueue;

/**
 * File Queue
 *
 * @author Roman Zhuravlev <zhuravljov@gmail.com>
 */
class Queue extends ConnQueue
{
    //文件队列存储队列
    public $path;

    //文件目录权限
    public $dirMode = 0755;
   
    //文件权限
    public $fileMode;


    public function init()
    {
        parent::init();
        if (!is_dir($this->path)) {
            static::createDirectory($this->path, $this->dirMode, true);
        }
    }
    
    //创建队列存储目录
    public static function createDirectory($path, $mode = 0775, $recursive = true)
    {
        if(!is_dir($path) && !mkdir($path, $mode))
            throw new \Exception("not dir！", 1);
        return true;
    }

    //从db队列运行所有作业。
    public function run()
    {
        while (!Signal::isExit() && ($payload = $this->reserve()) !== null) {
            list($id, $message, $ttr, $attempt) = $payload;

            if ($this->handleMessage($id, $message, $ttr, $attempt)) {
                $this->delete($payload);
            }
        }
    }

    //监听文件队列并运行新作业
    public function listen($delay)
    {
        do {
            $this->run();
        } while (!$delay || sleep($delay) === 0);
    }

    //保留消息等待执行
    protected function reserve()
    {
        $id = null;
        $ttr = null;
        $attempt = null;

        $this->touchIndex(function (&$data) use (&$id, &$ttr, &$attempt) {

            if (!empty($data['reserved'])) {
                foreach ($data['reserved'] as $key => $payload) {
                    if ($payload[1] + $payload[3] < time()) {
                        list($id, $ttr, $attempt, $time) = $payload;
                        $data['reserved'][$key][2] = ++$attempt;
                        $data['reserved'][$key][3] = time();
                        return;
                    }
                }
            }

            if (!empty($data['delayed']) && $data['delayed'][0][2] <= time()) {
                list($id, $ttr,) = array_shift($data['delayed']);
            } elseif (!empty($data['waiting'])) {
                list($id, $ttr) = array_shift($data['waiting']);
            }
            if ($id) {
                $attempt = 1;
                $data['reserved']["job$id"] = [$id, $ttr, $attempt, time()];
            }
        });
        if ($id) {
            return [$id, file_get_contents("$this->path/job$id.data"), $ttr, $attempt];
        } else {
            return null;
        }
    }

    //删除预留信息
    protected function delete($payload)
    {
        $id = $payload[0];
        $this->touchIndex(function (&$data) use ($id) {
            foreach ($data['reserved'] as $key => $payload) {
                if ($payload[0] === $id) {
                    unset($data['reserved'][$key]);
                    break;
                }
            }
        });
        unlink("$this->path/job$id.data");
    }

    /**
     * @inheritdoc
     */
    protected function pushMessage($message, $ttr, $delay, $priority)
    {
        if ($priority !== null) {
            throw new \Exception('Job priority is not supported in the driver.');
        }

        $this->touchIndex(function (&$data) use ($message, $ttr, $delay, &$id) {
            if (!isset($data['lastId'])) {
                $data['lastId'] = 0;
            }
            $id = ++$data['lastId'];
            $fileName = "$this->path/job$id.data";
            file_put_contents($fileName, $message);
            if ($this->fileMode !== null) {
                chmod($fileName, $this->fileMode);
            }
            if (!$delay) {
                $data['waiting'][] = [$id, $ttr, 0];
            } else {
                $data['delayed'][] = [$id, $ttr, time() + $delay];
                usort($data['delayed'], function ($a, $b) {
                    if ($a[2] < $b[2]) return -1;
                    if ($a[2] > $b[2]) return 1;
                    if ($a[0] < $b[0]) return -1;
                    if ($a[0] > $b[0]) return 1;
                    return 0;
                });
            }
        });

        return $id;
    }

    //队列执行状态 $id 队列的id
    protected function status($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            throw new \Exception("Unknown messages ID: $id.");
        }

        if (file_exists("$this->path/job$id.data")) {
            return self::STATUS_WAITING;
        } else {
            return self::STATUS_DONE;
        }
    }

    //队列取出队列数据,回调函数处理任务
    private function touchIndex($callback)
    {
        $fileName = "$this->path/index.data";
        $isNew = !file_exists($fileName);
        touch($fileName);
        if ($isNew && $this->fileMode !== null) {
            chmod($fileName, $this->fileMode);
        }
        if (($file = fopen($fileName, 'r+')) === false) {
            throw new \Exception("Unable to open index file: $fileName");
        }
        flock($file, LOCK_EX);
        $content = stream_get_contents($file);
        $data = $content === '' ? [] : unserialize($content);
        try {
            $callback($data);
            $newContent = serialize($data);
            if ($newContent !== $content) {
                ftruncate($file, 0);
                rewind($file);
                fwrite($file, $newContent);
                fflush($file);
            }
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}
