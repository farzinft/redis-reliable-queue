<?php

namespace Reliable;


class Item
{
    protected $key;
    protected $job;

    public function __construct($key, $job)
    {
        $this->key = $key;
        $this->job = $job;
    }

    /**
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }

    public function getJob()
    {
        return $this->job;
    }

    public function setJob(array $job)
    {
        $this->job = $job;
        return $this;
    }

    public function get($index)
    {
        switch ($index) {
            case 'method':
                return $this->jobHas('method') ? $this->getValue('method') : 'perform';
            case 'jobClass':
                return $this->jobHas('jobClass') ? $this->getValue('jobClass') : null;
            case 'payload':
                return $this->jobHas('payload') ? $this->getValue('payload') : null;
            case 'delay':
                return $this->jobHas('delay') ? $this->getValue('delay') : null;
            case 'try':
                return $this->jobHas('try') ? $this->getValue('try') : null;
            case 'try_count':
                return $this->jobHas('try_count') ? $this->getValue('try_count') : null;
            default:
                throw new \Exception($index . ' not found in job data');
        }
    }

    private function jobHas($key)
    {
        return isset($this->job[$key]) && !empty($this->job[$key]);
    }

    private function getValue($data)
    {
        return $this->job[$data];
    }
}