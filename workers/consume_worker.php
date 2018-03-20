<?php

$files = array(
    //Add Your Application Autoload File
    __DIR__ . '/../../../../vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
);

foreach ($files as $file) {
    if (file_exists($file)) {
        require_once $file;
        break;
    }
}

use Reliable\QueueConsumer;

fwrite(STDOUT, 'Started Consumer Worker' . "\n");

QueueConsumer::connect()->consume();

