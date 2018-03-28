<?php

namespace Reliable;


use Exception;
use Reliable\Exceptions\ClassNotFoundException;
use Reliable\Exceptions\MethodNotFoundException;

class ReliableQueue extends AbstractQueue implements IQueue
{
    protected $jobs = [];

    public function setJob($job)
    {
        array_push($this->jobs, (array)$job);
        return $this;
    }

    public function enqueue()
    {
        foreach ($this->jobs as $job) {

            $uuid = $this->generateUUID();

            $item = new Item($uuid, $job);

            $try = $item->get('try');

            $delay = $item->get('delay');

            $job = $this->formatJob($job);

            if ($try) {
                static::log('job added To delayed queue');
                $this->redis->zadd(static::$DELAYED_QUEUE, time() + $delay, $uuid);
            } else {
                $this->redis->lpush(static::$PENDING_QUEUES, $uuid);
            }

            $this->redis->hset(static::$PENDING_QUEUE_VALUES, $uuid, $job);

            $this->log('added job: ' . $job);

        }
    }
    

    public function dequeue()
    {
        $this->setWorkerPids(getmypid());

        cli_set_process_title('queue_process_' . getmypid());

        while (true) {

            while ($this->popJob() !== false) {
                try {
                    
                    $item = new Item($this->uuid, $this->job);

                    $class = $item->get('jobClass');

                    if (class_exists($class)) {

                        $classInstance = new $class;

                        $payload = $item->get('payload');

                        $method = $item->get('method');

                        if (method_exists($classInstance, $method)) {

                            static::log('calling job ' . json_encode($this->job));

                            call_user_func([$classInstance, $method], $payload);

                        } else {
                            throw new MethodNotFoundException('method ' . $method . ' can not be found :' . json_encode($this->job));
                        }
                    } else {
                        throw new ClassNotFoundException('class ' . $class . ' can not be found :' . json_encode($this->job));
                    }
                    static::log('job done ' . json_encode($this->job));

                    $this->release();

                } catch (Exception $e) {

                    static::log($e->getMessage());

                    if ($e instanceof ClassNotFoundException || $e instanceof MethodNotFoundException) {
                        $this->release();
                    } else {

                        if ($item->get('try')) {

                            static::log('trying job: ' . json_encode($this->job));

                            if (!$item->get('try_count')) {
                                $this->job['try_count'] = 0;
                            }

                            if ($this->job['try_count'] < static::getjobTry()) {

                                $this->job['try_count']++;

                                $item->setJob($this->job);

                                $this->renqueueDelayed($item);
                            } else {
                                $this->release();
                            }
                        } else {
                            /**
                             * in this section we can save job in database
                             */
                          //  $this->renqueue();
                            $this->log('renqueued job ' . json_encode($this->job));
                        }
                    }
                }
            }
            sleep(1);
        }
    }



    public function sweep()
    {
        $jobs = $this->redis->zrangebyscore(static::$WORKING_QUEUE, '-inf', time(), [
            'withscores' => true
        ]);
        if (!empty($jobs)) {
            foreach ($jobs as $uuid => $time) {
                if ($this->queueHasJob($uuid) == false) {
                    if (time() - $time > static::$TIME_OUT) {
                        static::log('got timeout tasks: ' . json_encode($jobs));
                        $this->uuid = $uuid;
                        $job = json_decode($this->getJobFromQueue($uuid), true);
                        if (isset($job['true']) && $job['try'] == true) {
                            $this->renqueueDelayed($job);
                        } else {
                            $this->renqueue();
                        }
                    }
                }
            }
        }
        return false;
    }

    public function release()
    {
        $this->redis->zrem(static::$WORKING_QUEUE, $this->uuid);
        $this->redis->zrem(static::$DELAYED_QUEUE, $this->uuid);
        $this->redis->hdel(static::$PENDING_QUEUE_VALUES, $this->uuid);
        static::log('safely released job: ' . json_encode($this->job) . "\n");
    }

    public function renqueue()
    {
        $this->redis->lpush(static::$PENDING_QUEUES, $this->uuid);
        $this->redis->zrem(static::$WORKING_QUEUE, $this->uuid);
    }

    public function renqueueDelayed(Item $item)
    {
        $delay = $item->get('delay') ?: static::getDefaultJobDelay();

        $this->redis->hset(static::$PENDING_QUEUE_VALUES, $item->getKey(), json_encode($item->getjob()));
        $this->redis->zadd(static::$DELAYED_QUEUE, time() + $delay, $item->getKey());

        static::log('*** re enqueued delayed job' . "\n");

    }

    public function handleDelayedJobs()
    {
        static::log('Started Delayed Worker');
        while (true) {
            $item = $this->redis->zrangebyscore(static::$DELAYED_QUEUE, '-inf', time(), array('withscores' => TRUE, 'limit' => array(0, 1)));
            if (!empty($item)) {
                static::log('got a delayed task: ' . json_encode($item) . "\n");
                $this->uuid = key($item);
                $this->redis->lpush(static::$PENDING_QUEUES, $this->uuid);
                $this->redis->zrem(static::$DELAYED_QUEUE, $this->uuid);
            } else {
                sleep(1);
            }
        }
    }

}