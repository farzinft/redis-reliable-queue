<?php

$files = array(
    //Add Your Application Autoload File
    __DIR__ . '/../../../../vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php'
);

foreach ($files as $file) {
    if (file_exists($file)) {
        require_once $file;
        break;
    }
}

$config = include __DIR__ . '/../config/queue.php';

$processCount = $config['PROCESS_COUNT'];

use \Reliable\ReliableQueue;

ReliableQueue::log('Started Consumer Worker');

//$consumer = QueueConsumer::connect();

if ($processCount > 1) {

    for ($i = 0; $i < $processCount; $i++) {
        $pid = ReliableQueue::fork();

        if ($pid === false || $pid === -1) {
            ReliableQueue::log('Could not fork worker');
        }

        if ($pid == 0) {
            //child process
            ReliableQueue::getInstance()->dequeue();
            exit;
        }
    }
} else {
    ReliableQueue::getInstance()->dequeue();
}



