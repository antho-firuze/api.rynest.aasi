<?php
return [
    'consumer'  => [
        'handler'     => Webman\RedisQueue\Process\Consumer::class,
        'count'       => 8, // Multiple processes can be used to consume simultaneously
        'constructor' => [
            // Consumer Catalog
            'consumer_dir' => app_path() . '/queue/redis'
        ]
    ]
];