<?php

namespace app\queue\redis;

use Webman\RedisQueue\Consumer;

class MySendMail implements Consumer
{
    // Queue name to consume
    public $queue = 'send-mail';

    // Connection name, corresponding to the connection in `plugin/webman/redis-queue/redis.php`
    public $connection = 'default';

    // Consumption
    public function consume($data)
    {
        // No need for deserialization
        var_export($data); // Outputs ['to' => 'tom@gmail.com', 'content' => 'hello']
    }
}
