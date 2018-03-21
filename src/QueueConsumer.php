<?php

namespace Reliable;

use Exception;
use Reliable\Exceptions\ClassNotFoundException;
use Reliable\Exceptions\MethodNotFoundException;

class QueueConsumer extends AbstractQueue
{

    protected static $uuid;
    protected static $task; // Object
    protected static $childProcess;

    public function consume()
    {
        $this->setWorkerPids(getmypid());

        cli_set_process_title('queue process' . getmypid());

        while (true) {

            while ((($task = static::dequeue()) !== false)) {
                try {

                    $taskClass = $task['taskClass'];

                    if (class_exists($taskClass)) {

                        $taskObj = new $taskClass;

                        $data = isset($task['data']) ? $task['data'] : [];

                        $method = isset($task['method']) ? isset($task['method']) : 'perform';

                        if (method_exists($taskObj, $method)) {

                            static::log('calling task' . json_encode($task));

                            call_user_func([$taskObj, $method], $data);

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

                        if (isset($task['try']) && $task['try'] == true) {

                            static::log('trying job: ' . json_encode($task));

                            if (isset($task['try_count']) == false) {
                                $task['try_count'] = 0;
                            }

                            if ($task['try_count'] < static::getTaskTry()) {

                                $task['try_count']++;

                                static::reenqueueDelayed($task);
                            } else {
                                static::safelyRemoveTaskFromQueue();
                            }
                        } else {
                            static::reenqueue();
                        }
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
        if (!empty($tasks)) {
            foreach ($tasks as $uuid => $time) {
                if (static::listHasTask($uuid) == false) {
                    if (time() - $time > static::$TIME_OUT) {
                        static::log('got timeout tasks: ' . json_encode($tasks));
                        static::$uuid = $uuid;
                        $task = json_decode(static::getTask($uuid), true);
                        if (isset($task['true']) && $task['try'] == true) {
                            static::reenqueueDelayed($task);
                        } else {
                            static::reenqueue();
                        }
                    }
                }
            }
        }
        return false;
    }

    public static function safelyRemoveTaskFromQueue()
    {
        static::$client->zrem(static::$WORKING_QUEUE, static::$uuid);
        static::$client->zrem(static::$DELAYED_QUEUE, static::$uuid);
        static::$client->hdel(static::$PENDING_QUEUE_VALUES, static::$uuid);
        static::log('safely removed task: ' . json_encode(static::$task) . "\n");
    }


    public static function dequeue()
    {
        static::$uuid = static::$client->rpop(static::$PENDING_QUEUES);
        if (static::$uuid) {
            static::$client->zadd(static::$WORKING_QUEUE, [
                static::$uuid => time()
            ]);
            static::log('task added to ' . static::$WORKING_QUEUE);
            $task = static::$client->hget(static::$PENDING_QUEUE_VALUES, static::$uuid);
            static::$task = json_decode($task, true);
            return static::$task;
        } else {
            return false;
        }

    }

    public static function reenqueue()
    {
        static::$client->lpush(static::$PENDING_QUEUES, static::$uuid);
        static::$client->zrem(static::$WORKING_QUEUE, static::$uuid);
    }

    public static function reenqueueDelayed(array $task)
    {
        $delay = isset($task['delay']) && !empty($task['delay']) ? $task['delay'] : static::getdefaultTaskDelay();

        static::$client->hset(static::$PENDING_QUEUE_VALUES, static::$uuid, json_encode($task));
        static::$client->zadd(static::$DELAYED_QUEUE, time() + $delay, static::$uuid);

        static::log('*** re enqueued delayed job' . "\n");

    }

    public static function fork()
    {
        if (!function_exists('pcntl_fork')) {
            return false;
        }

        // Close the connection to Redis before forking.
        // This is a workaround for issues phpredis has.
        self::$client = null;
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new \RuntimeException('Unable to fork child worker.');
        }
        return $pid;
    }

    public function setWorkerPids($pid)
    {
        static::$client->lpush('worker_pids', $pid);
    }

    public function getWorkerPids()
    {
        return static::$client->lrange('worker_pids', 0, -1);
    }

    public function killWorkerPids()
    {
        foreach ($this->getWorkerPids() as $pid) {
            posix_kill($pid, SIGKILL);
        }
        return static::$client->del('worker_pids');
    }

    public function handleDelayedTasks()
    {
        static::log('Started Delayed Worker');
        while (true) {
            $item = static::$client->zrangebyscore(static::$DELAYED_QUEUE, '-inf', time(), array('withscores' => TRUE, 'limit' => array(0, 1)));
            if (!empty($item)) {
                static::log('got a delayed task: ' . json_encode($item) . "\n");
                static::$uuid = key($item);
                static::$client->lpush(static::$PENDING_QUEUES, static::$uuid);
                static::$client->zrem(static::$DELAYED_QUEUE, static::$uuid);
            } else {
                sleep(1);
            }

        }
    }

    public static function listHasTask($uuid)
    {
        $items = static::$client->lrange(static::$PENDING_QUEUES, 0, -1);

        foreach ($items as $item) {
            if ($item == $uuid) return true;
        }
        return false;
    }

    public static function getTask($uuid)
    {
        return static::$client->hget(static::$PENDING_QUEUE_VALUES, $uuid);
    }

}