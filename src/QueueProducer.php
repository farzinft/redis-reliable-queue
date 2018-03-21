<?php

namespace Reliable;


class QueueProducer extends AbstractQueue
{

    protected $tasks = [];


    public function setTask($task)
    {
        array_push($this->tasks, (array)$task);
        return $this;
    }

    public function enqueue()
    {
        foreach ($this->tasks as $task) {

            $uuid = $this->generateUUID();

            $try = $this->taskHasTry($task);

            $delay = $this->getTaskDelay($task);

            $task = $this->formatTask($task);

            if ($try) {
                static::log('Task Added To delayed queue');
                static::$client->zadd(static::$DELAYED_QUEUE, $delay, $uuid);
            } else {
                static::$client->lpush(static::$PENDING_QUEUES, $uuid);
            }

            static::$client->hset(static::$PENDING_QUEUE_VALUES, $uuid, $task);

            $this->log('Added Task: ' . $task);

        }
    }

    protected function taskHasTry(array $task)
    {
        return isset($task['try']) && $task['try'] == true;
    }

    protected function getTaskDelay(array $task)
    {
        return isset($task['delay']) && !empty($task['delay']) ? $task['delay'] : static::getdefaultTaskDelay();
    }

    protected static function formatTask($task)
    {
        if(is_array($task)) {
            return json_encode($task);
        }
        return $task;
    }

}