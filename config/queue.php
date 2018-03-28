<?php

return [
    'PENDING_QUEUES' => 'reliable:pending_list',
    'PENDING_QUEUE_VALUES' => 'reliable:pending_values',
    'WORKING_TIMESTAMP_QUEUE' => 'reliable:timestamp_working',
    'DELAYED_QUEUE' => 'reliable:delayed_queue',
    'QUEUE_TIMEOUT' => 20,
    'JOB_TRY_COUNT' => 5,
    'PROCESS_COUNT' => 2,
    'DEFAULT_JOB_DELAY' => 5
];