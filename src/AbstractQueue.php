<?php


namespace Reliable;
use Predis\Client;

class AbstractQueue
{
    protected $redis;
    protected $uuid;
    protected $job;
    
    protected static $instance;
    protected static $PENDING_QUEUES;
    protected static $PENDING_QUEUE_VALUES;
    protected static $WORKING_QUEUE;
    protected static $TIME_OUT;
    protected static $PROCESS_COUNT;
    protected static $DELAYED_QUEUE;
    protected static $JOBTRY;
    protected static $DEFAULT_JOB_DELAY;
    /**
     * @return mixed
     */
    public function getRedis()
    {
        return $this->redis;
    }


    private function __construct($options = [])
    {
        $config = $this->getConfigs();
        static::$PENDING_QUEUES = $config['PENDING_QUEUES'];
        static::$PENDING_QUEUE_VALUES = $config['PENDING_QUEUE_VALUES'];
        static::$WORKING_QUEUE = $config['WORKING_TIMESTAMP_QUEUE'];
        static::$TIME_OUT = $config['QUEUE_TIMEOUT'];
        static::$PROCESS_COUNT = $config['PROCESS_COUNT'];
        static::$DELAYED_QUEUE = $config['DELAYED_QUEUE'];
        static::$JOBTRY = $config['JOB_TRY_COUNT'];
        static::$DEFAULT_JOB_DELAY = $config['DEFAULT_JOB_DELAY'];
        $this->redis = $this->connector($options);
    }

    /**
     * @return mixed
     */
    public static function getDefaultJobDelay()
    {
        return self::$DEFAULT_JOB_DELAY;
    }

    /**
     * @return mixed
     */
    public static function getJOBTRY()
    {
        return self::$JOBTRY;
    }

    private function getConfigs()
    {
        return require __DIR__ . '/../config/queue.php';
    }

    public function connector($options = [])
    {
        return new Client([
            'scheme' => isset($options['scheme']) ? $options['scheme'] : 'tcp',
            'host' => isset($options['host']) ? $options['host'] : '127.0.0.1',
            'port' => isset($options['port']) ? $options['port'] : '6379',
        ], (isset($options['options']) && is_array($options['options'])) ? $options['options'] : []);
    }

    public static function getInstance($options = [])
    {
        if(!isset(static::$instance)) {
            static::$instance = new ReliableQueue($options);
        }
        return static::$instance;
    }

    protected function generateUUID()
    {
        return uniqid('reliable_');
    }

    public function log($message, $data = null)
    {
        return fwrite(STDOUT, sprintf('[' . date('Y/m/d H:i'). '] ' . $message . "%s", $data) . "\n");
    }

    public static function __callStatic($name, $arguments)
    {
        if (method_exists(new static(), $name)) {
            return call_user_func_array([new static(), $name], $arguments);
        }
    }

    public function getProcessCount()
    {
        return static::$PROCESS_COUNT;
    }

    protected function setWorkerPids($pid)
    {
        $this->redis->lpush('worker_pids', $pid);
    }

    protected static function formatJob($job)
    {
        if(is_array($job)) {
            return json_encode($job);
        }
        return $job;
    }

    protected function popJob()
    {
        $this->uuid = $this->redis->rpop(static::$PENDING_QUEUES);
        if ($this->uuid) {
            $this->redis->zadd(static::$WORKING_QUEUE, [
                $this->uuid => time()
            ]);
            static::log('job ' . $this->uuid . ' added to ' . static::$WORKING_QUEUE);
            $this->job = json_decode($this->getJobFromQueue($this->uuid), true);
            return $this->job;
        } else {
            return false;
        }
    }


    protected  function queueHasJob($uuid)
    {
        $items = $this->redis->lrange(static::$PENDING_QUEUES, 0, -1);
        foreach ($items as $item) {
            if ($item == $uuid) return true;
        }
        return false;
    }

    protected function getJobFromQueue($uuid)
    {
        return  $this->redis->hget(static::$PENDING_QUEUE_VALUES, $uuid);
    }

    public static function fork()
    {
        if (!function_exists('pcntl_fork')) {
            return false;
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new \RuntimeException('Unable to fork child worker.');
        }
        return $pid;
    }

    protected function getWorkerPids()
    {
        return $this->redis->lrange('worker_pids', 0, -1);
    }

    public function killWorkerPids()
    {
        foreach ($this->getWorkerPids() as $pid) {
            posix_kill($pid, SIGKILL);
        }
        return $this->redis->del('worker_pids');
    }

    public static function publishConfig()
    {
        $configPath = __DIR__ . '/../config/queue.php';
        $publishPath = __DIR__ . '/../../../../reliable-queue';
        if (file_exists($configPath)) {
            mkdir($publishPath, 0755);
            symlink($configPath, $publishPath . '/config.php');
        } else {
            exit('error on creating symbolic config file');
        }
    }


}