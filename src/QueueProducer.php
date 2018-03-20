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
            $task = $this->formatTask($task);

            static::$client->lpush(static::$PENDING_QUEUES, $uuid);

            static::$client->hset(static::$PENDING_QUEUE_VALUES, $uuid, $task);

            $this->log('Added Task: ' . $task);

        }
    }

    protected static function formatTask($task)
    {
        if(is_array($task)) {
            return json_encode($task);
        }
        return $task;
    }

}