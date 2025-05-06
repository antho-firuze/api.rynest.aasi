<?php

namespace app\queue\redis;

use support\Db;
use support\Email;
use Webman\RedisQueue\Consumer;

class SyncQuestion implements Consumer
{
    // Queue name to consume
    public $queue = 'sync-question';

    // Connection name, corresponding to the connection in `plugin/webman/redis-queue/redis.php`
    public $connection = 'sync_question';

    // Consumption
    public function consume($data)
    {
        $schedule_request_id = $data['schedule_request_id'];
        $id_member = $data['id_member'];
        $question_id = $data['question_id'];

        Db::table('exam_results')
            ->where('schedule_request_id', $schedule_request_id)
            ->where('id_member', $id_member)
            ->update([
                'sync_question' => $question_id,
            ]);

        // No need for deserialization
        var_export($data); // Outputs ['to' => 'tom@gmail.com', 'content' => 'hello']
    }
}
