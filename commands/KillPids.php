<?php

require_once __DIR__ . '/../vendor/autoload.php';

use \Reliable\ReliableQueue;

ReliableQueue::getInstance()->killWorkerPids();

ReliableQueue::log('ok killed all Pids');

