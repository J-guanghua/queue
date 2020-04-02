namespace guanghua\queue;

use Exception;
use guanghua\queue\file\Queue as FileQueue;
use guanghua\queue\redis\Queue as RedisQueue;
use guanghua\queue\beanstalk\Queue as BeanstalkQueue;

//文件队列数据存储目录
define('FILE_PATH',dirname(dirname(dirname(dirname(__FILE__)))));

class guanghua
{	
    
    public static $app;
    
    //队列配置项
    public static $queueArray = [
        'file'=>[
            'path' => FILE_PATH . '/queue'
        ],
        'redis' => [
            'hostname' => '121.37.3.163', //连接主机
            'port'     => 6379, //连接端口
            'database' => 0 //连接数据库
        ],
        'beanstalk' => [
            'hostname' => '121.37.3.163', //连接主机
        ],
    ];

    //数组共享组件实例的id索引
    private static $_components = [];


    //当前redis实例
    public static function file()
    {
    	return self::ensure('file',FileQueue::class);
    }
    
    //当前redis缓存实例
    public static function redis()
    {
    	return self::ensure('redis',RedisQueue::class);
    }

    //当前session缓存实例
    public static function beanstalk()
    {
        return self::ensure('beanstalk',BeanstalkQueue::class);
    }

    //得到一个实例化的类对象 并注册到共享组件
    public static function ensure($id ,$class){
       
        if (isset(self::$_components[$id])) {
            
            return self::$_components[$id];
        }
        if (isset(static::$queueArray[$id])) {

            return self::$_components[$id] = new $class(static::$queueArray[$id]);
        }
        throw new Exception($id .' NOT : ' . get_class($this) . '::' . $class);
    }
}
?>
