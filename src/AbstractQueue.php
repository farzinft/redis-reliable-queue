<?php


namespace Reliable;
use Predis\Client;

class AbstractQueue
{
    protected static $client;
    protected static $instance;
    protected static $PENDING_QUEUES;
    protected static $PENDING_QUEUE_VALUES;
    protected static $WORKING_QUEUE;
    protected static $TIME_OUT;

    private function __construct($options = [])
    {
        $config = $this->getConfigs();
        static::$PENDING_QUEUES = $config['PENDING_QUEUES'];
        static::$PENDING_QUEUE_VALUES = $config['PENDING_QUEUE_VALUES'];
        static::$WORKING_QUEUE = $config['WORKING_TIMESTAMP_QUEUE'];
        static::$TIME_OUT = $config['QUEUE_TIMEOUT'];
        static::$client = $this->connector($options);
    }

    private function getConfigs()
    {
        return  require __DIR__ . '/../config/queue.php';
    }

    public function connector($options = [])
    {
        return new Client([
            'scheme' => isset($options['scheme']) ? $options['scheme'] : 'tcp',
            'host' => isset($options['host']) ? $options['host'] : '127.0.0.1',
            'port' => isset($options['port']) ? $options['port'] : '6379',
        ], (isset($options['options']) && is_array($options['options'])) ? $options['options'] : []);
    }

    public static function connect($options = [])
    {
        if(!isset(static::$instance)) {
            if(get_called_class() == QueueProducer::class) {
                static::$instance = new QueueProducer($options);
            }else {
                static::$instance = new QueueConsumer($options);
            }
        }
        return static::$instance;
    }

    protected function generateUUID()
    {
        return uniqid('reliable_');
    }

    protected function log($message, $data = null)
    {
        return fwrite(STDOUT, sprintf('[' . date('Y/m/d H:i'). '] ' . $message . "%s", $data) . "\n");
    }

    public static function __callStatic($name, $arguments)
    {
        if (method_exists(new static(), $name)) {
            return call_user_func_array([new static(), $name], $arguments);
        }
    }
}