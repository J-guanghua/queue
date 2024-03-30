
Installation
------------

安装此扩展的首选方法是通过 [composer](http://getcomposer.org/download/).

php composer.phar require --prefer-dist guanghua/queue "dev-master"

or add

“guanghua / queue”：“dev-master”到composer.json文件的require部分。

 
Usage
-----

适用于 php
```php

支持 文件 , redis , beanstalk 存储队列方式

use queue\file\Queue;
use queue\redis\Queue as RedisQueue;
use queue\beanstalk\Queue as BeanstalkQueue;

// 如果是手动载入，没有使用 composer
spl_autoload_register(function ($className) {
    // queue 目录所在路径
    $dirPath = __DIR__.'\\pieend';
    // 将命名空间中的反斜杠转换为目录分隔符
    $classFile =  str_replace('\\', '/', $dirPath. '\\'. $className) . '.php';
    // 如果文件存在，则包含文件
    if (is_file($classFile)) {
        include($classFile);
    }
});


// 定义一个文件下载类 并实现 queue\Job 接口方法
class DownloadJob extends \queue\base\Basics implements \queue\Job {

     public $url;
     public $file;
    
     public function execute($queue)
     {
         file_put_contents($this->file, file_get_contents($this->url));
     }

}

// 实例化一个本地文件队列 
$queue = new Queue(["path"=>"./queue"]);

// 实例化一个redis队列
$queue = new RedisQueue([
   'hostname' => '127.0.0.1', //连接主机
   'port'     => 6379, //连接端口
   'database' => 0 //连接数据库
]);

// 实例化一个beanstalk队列
$queue = new BeanstalkQueue([
    'hostname' => '127.0.0.1', //连接主机
]);

// 文件队列
$queue->push(new DownloadJob(['url'=>'https://www.topgoer.cn/uploads/202008/cover_162950b2ef51313f_small.png','file'=>'./queue/image.jpg']));

// 将作业推送到5分钟后运行的队列中：
$queue->delay(5 * 60)->push(new DownloadJob(['url'=>' http://example.com/image.jpg','file'=>'./queue/image.jpg']));

// 任务执行的确切方式取决于所使用的驱动程序。驱动程序的大部分可以使用控制台命令运行。
$queue->run(); // 在循环中获取并执行任务的命令，直到队列为空：

// 生产作业方式
// 命令启动一个队列的守护程序：
$queue->listen();


// 该组件具有跟踪被推入队列的作业的状态的能力。

// 将作业推入队列并获取作业ID。
$ID = $queue->push（new DownloadJob) ;

// 工作正在等待执行。
$queue->isWaiting($ID);

// Worker从队列中获取作业，并执行它。
$queue->isReserved($ID);

// 这个工作是否被执行。
$queue->isDone($ID); 
```