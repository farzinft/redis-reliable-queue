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

use Reliable\QueueConsumer;

QueueConsumer::log('Started Consumer Worker');

//$consumer = QueueConsumer::connect();

if ($processCount > 1) {

    for ($i = 0; $i < $processCount; $i++) {
        $pid = QueueConsumer::fork();

        if($pid === false || $pid === -1) {
            QueueConsumer::log('Could not fork worker');
            die();
        }

        if ($pid == 0) {
            //child process
            $consumer = QueueConsumer::getInstance();
            $consumer->consume();
            exit;
        }
    }
} else {
    QueueConsumer::getInstance()->consume();
}



