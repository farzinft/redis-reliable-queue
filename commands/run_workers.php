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

$workersPath = __DIR__ . '/../workers';

exec('/usr/bin/php ' . $workersPath . '/sweep.php > /dev/null 2>&1 &');
exec('/usr/bin/php ' . $workersPath . '/delayed.php > /dev/null 2>&1 &');
exec('/usr/bin/php ' . $workersPath . '/worker.php > /dev/null 2>&1 &');

fwrite(STDOUT, '*** All workers started');
