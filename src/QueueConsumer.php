<?php

namespace Reliable;

use Exception;
use Reliable\Exceptions\ClassNotFoundException;
use Reliable\Exceptions\MethodNotFoundException;

class QueueConsumer extends AbstractQueue
{

    protected static $uuid;
    protected static $task; // Object


    public function consume()
    {
        while (true) {

            while (($task = static::dequeue()) !== false) {
                try {

                    $taskClass = $task['taskClass'];

                    if (class_exists($taskClass)) {

                        $taskObj = new $taskClass;

                        $args = isset($task['args']) ? $task['args'] : [];

                        $method = isset($task['method']) ? isset($task['method']) : 'perform';

                        if (method_exists($taskObj, $method)) {
                            static::log('calling task' . json_encode($task));
                            call_user_func_array([$taskObj, $method], $args);
                        } else {
                            throw new MethodNotFoundException('method ' . $method . ' can not be found :' . json_encode($task));
                        }
                    } else {
                        throw new ClassNotFoundException('class ' . $taskClass . ' can not be found :' . json_encode($task));
                    }
                    static::log('task done ' . json_encode($task));
                    static::safelyRemoveTaskFromQueue();

                } catch (Exception $e) {

                    static::log($e->getMessage());

                    if ($e instanceof ClassNotFoundException || $e instanceof MethodNotFoundException) {
                        static::safelyRemoveTaskFromQueue();
                    } else {
                        static::reenqueue();
                    }
                }
            }
            sleep(1);
        }

    }


    public static function sweepUp()
    {
         $tasks = static::$client->zrangebyscore(static::$WORKING_QUEUE, '-inf', time(), [
             'withscores' => true
        ]);
         if(!empty($tasks)) {
             static::log('found timeout tasks: ' . json_encode($tasks));

             foreach ($tasks as $uuid => $time) {
                 if(time() - $time > static::$TIME_OUT) {
                     static::$uuid = $uuid;
                     static::reenqueue();
                 }
             }
         }
         return false;
    }

    public static function safelyRemoveTaskFromQueue()
    {
        static::$client->zrem(static::$WORKING_QUEUE, static::$uuid);
        static::$client->hdel(static::$PENDING_QUEUE_VALUES, static::$uuid);
        static::log('safely removed task: ' . json_encode(static::$task) . "\n");
    }


    public static function dequeue()
    {
        static::$uuid = static::$client->rpop(static::$PENDING_QUEUES);
        if(static::$uuid) {
            static::$client->zadd(static::$WORKING_QUEUE, [
                static::$uuid => time()
            ]);
            $task = static::$client->hget(static::$PENDING_QUEUE_VALUES, static::$uuid);
            static::$task = json_decode($task, true);
            return static::$task;
        }else {
            return false;
        }

    }

    public static function reenqueue()
    {
        static::$client->lpush(static::$PENDING_QUEUES, static::$uuid);
        static::$client->zrem(static::$WORKING_QUEUE, static::$uuid);
    }

    public static function fork()
    {
        if(!function_exists('pcntl_fork')) {
            return false;
        }

        // Close the connection to Redis before forking.
        // This is a workaround for issues phpredis has.
        self::$client = null;
        $pid = pcntl_fork();
        if($pid === -1) {
            throw new \RuntimeException('Unable to fork child worker.');
        }
        return $pid;
    }


    
}