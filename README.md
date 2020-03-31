
Installation
------------

安装此扩展的首选方法是通过 [composer](http://getcomposer.org/download/).

php composer.phar require --prefer-dist guanghua/queue

or add

“guanghua / queue”：“dev-master”到composer.json文件的require部分。

 
Usage
-----

Once the extension is installed, simply use it in your code by  :

```php

支持 文件 , redis , beanstalk 存储队列方式

发送到队列的每个任务应该被定义为一个单独的类。例如，如果您需要下载并保存文件，则该类可能如下所示：

class DownloadJob extends \guanghua\queue\base\Basics implements\guanghua\queue\Job {
 
 public $url; 
 public $file;

 public function execute($queue)
 {
     file_put_contents($this->file, file_get_contents($this->url));
 }

}
以下是如何将任务发送到队列中：

//文件队列
Guanghua::file()->push（new DownloadJob（['url'=>' http://example.com/image.jpg'，'file'= >'/tmp/image.jpg']））;

//redis队列
Guanghua::redis()->push（new DownloadJob（['url'=>' http://example.com/image.jpg'，'file'= >'/tmp/image.jpg']））;

//beanstalk队列
Guanghua::beanstalk()->push（new DownloadJob（['url'=>' http://example.com/image.jpg'，'file'= >'/tmp/image.jpg']））;

将作业推送到5分钟后运行的队列中：

Guanghua::file()->delay（5 * 60)->push（new DownloadJob（['url'=>' http://example.com/image.jpg'，'file'= >'/tmp/image.jpg']））;

任务执行的确切方式取决于所使用的驱动程序。驱动程序的大部分可以使用控制台命令运行，组件在应用程序中注册。

在循环中获取并执行任务的命令，直到队列为空：

Guanghua::file()->run() 消费队列

Guanghua::file()->listen 命令启动一个无限查询队列的守护程序：


该组件具有跟踪被推入队列的作业的状态的能力。

//将作业推入队列并获取作业ID。
$ID = Guanghua::file()->push（new DownloadJob) ;

//工作正在等待执行。
Guanghua::file()->isWaiting（$ID）;

// Worker从队列中获取作业，并执行它。
Guanghua::file()->isReserved（$ID）;

//工作执行了这个工作。
Guanghua::file()->isDone（$ID）; 
