<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Reliable\QueueConsumer;

QueueConsumer::getInstance()->killWorkerPids();

QueueConsumer::log('ok killed all Pids');

