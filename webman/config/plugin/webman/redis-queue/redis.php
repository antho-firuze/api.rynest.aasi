<?php
return [
    'default' => [
        'host' => getenv('REDIS_HOST') . ":" . getenv('REDIS_PORT'),
        // 'host' => 'redis://127.0.0.1:6379',
        'options' => [
            'auth' => null,
            'db' => 0,
            'prefix' => '',
            'max_attempts'  => 5,
            'retry_seconds' => 5,
        ],
        // Connection pool, supports only Swoole or Swow drivers.
        'pool' => [
            'max_connections' => 5,
            'min_connections' => 1,
            'wait_timeout' => 3,
            'idle_timeout' => 60,
            'heartbeat_interval' => 50,
        ]
    ],
    'sync_question' => [
        'host' => getenv('REDIS_HOST') . ":" . getenv('REDIS_PORT'),
        // 'host' => 'redis://127.0.0.1:6379',
        'options' => [
            'auth' => null,
            'db' => 0,
            'prefix' => '',
            'max_attempts'  => 3,
            'retry_seconds' => 5,
        ],
        // Connection pool, supports only Swoole or Swow drivers.
        'pool' => [
            'max_connections' => 5,
            'min_connections' => 1,
            'wait_timeout' => 3,
            'idle_timeout' => 60,
            'heartbeat_interval' => 50,
        ]
    ],
];
