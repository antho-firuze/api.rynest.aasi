<?php

namespace app\queue\redis;

use support\Email;
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
        try {
            $to = $data['to'];
            $subject = $data['subject'];
            $content = $data['content'];
            Email::send(null, $to, $subject, $content);

            var_export("Sending mail success !\n");
        } catch (\Throwable $th) {
            var_export($th->getMessage());
        }

        // No need for deserialization
        // var_export($data); // Outputs ['to' => 'tom@gmail.com', 'content' => 'hello']
    }
}
