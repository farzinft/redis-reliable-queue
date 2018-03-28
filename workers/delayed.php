<?php

require_once  __DIR__ . '/../vendor/autoload.php';


$config = include __DIR__ . '/../config/queue.php';

$processCount = $config['PROCESS_COUNT'];

use \Reliable\ReliableQueue;

ReliableQueue::getInstance()->handleDelayedJobs();
