<?php

namespace app\controller;

use support\Db;
use support\Email;
use support\MyFunc;
use support\Redis;
use support\Request;
use Webman\Push\Api;
use Webman\RedisQueue\Redis as RedisQueue;

class IndexController
{
    public function index(Request $request)
    {
        static $readme;
        if (!$readme) {
            $readme = file_get_contents(base_path('README.md'));
        }
        return $readme;
    }

    public function view(Request $request)
    {
        return view('index/view', ['name' => 'webman']);
    }

    function test_php(Request $request)
    {
        ob_start();
        phpinfo();
        return nl2br(ob_get_clean());
    }

    function test_env(Request $request)
    {
        try {
            return json(getenv());
        } catch (\Throwable $th) {
            return json(['message' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }

    function test_db(Request $request)
    {
        try {
            $user = Db::table('users')->first();
            return json($user);
        } catch (\Throwable $th) {
            return json(['message' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }

    function test_redis(Request $request)
    {
        $data = (object) $request->get();
        $redisKey = 'key-a';
        $state = $data->state ?? 'set';
        try {
            if ($state == 'set') {
                Redis::set($redisKey, 'value-a');
                Redis::expire($redisKey, 10);
                return json(['message' => "done"]);
            } else {
                $value = Redis::get($redisKey);
                $result[$redisKey] = $value;
                return json($result);
            }
        } catch (\Throwable $th) {
            return json(['message' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }

    function test_redis_queue(Request $request)
    {
        try {
            $payload['to'] = 'antho.firuze@gmail.com';
            $payload['subject'] = 'Testing send mail';
            $payload['content'] = 'This is only content !';
            RedisQueue::send('send-mail', $payload);

            return json(['message' => "Sending mail with Redis-Queue success !"]);
        } catch (\Throwable $th) {
            return json(['message' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }

    function test_cron(Request $request)
    {
        try {
            // Cronjob for update an exam status where timed has been execeded, executed every minutes.
            // $exam_result = Db::table('exam_results')
            //     ->select('*')
            //     ->where('status', '=', '')
            //     ->where('start_at', '<>', null)
            //     ->where('finish_at', '=', null)
            //     ->whereRaw('(start_at + INTERVAL duration MINUTE) > NOW()')
            //     ->get();

            // return json($exam_result);
        } catch (\Throwable $th) {
            return json(['message' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }

    function test_send_mail(Request $request)
    {
        try {
            $payload['to'] = 'antho.firuze@gmail.com';
            $payload['subject'] = 'Testing send mail';
            $payload['content'] = 'This is only content !';
            Email::send(null, $payload['to'], $payload['subject'], $payload['content']);

            return json(['message' => "Sending mail success !"]);
        } catch (\Throwable $th) {
            return json(['message' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }

    function test_pusher_public(Request $request)
    {
        try {
            $channel_name = "public-channel";
            $event = 'message';
            $payload['title'] = 'Ini title';
            $payload['message'] = 'Dan ini contoh message !';
            MyFunc::send_notif($channel_name, $event, $payload);

            return json(['message' => "Sending push-message success !"]);
        } catch (\Throwable $th) {
            return json(['message' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }

    function test_pusher_private(Request $request)
    {
        try {
            $channel_name = "private-user-400";
            $event = 'intrusion';
            $payload['code'] = 409;
            $payload['device_id'] = "emu64a:UE1A.230829.036.A23";
            $payload['title'] = 'Deteksi Gangguan';
            $payload['message'] = 'Perangkat lain telah login menggunakan akun Anda !';
            MyFunc::send_notif($channel_name, $event, $payload);

            return json(['message' => "Sending push-message success !"]);
        } catch (\Throwable $th) {
            return json(['message' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }

    function test_upload(Request $request)
    {
        try {
            $config['userfile'] = "userfile";
            $config['file_name'] = "test-upload";
            $config['folder'] = "images/";
            $config['allowed_types'] = ['jpg', 'png', 'bmp', 'gif'];
            $config['max_size'] = 1000; // in KB, default 1000KB = 1MB

            $url = MyFunc::upload_file($request, $config);

            return json(['message' => 'Upload success !', 'url' => $url]);
        } catch (\Throwable $th) {
            return json(['message' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }

    function test_upload_s3(Request $request)
    {
        try {
            $config['userfile'] = "userfile";
            $config['file_name'] = "test-upload";
            $config['folder'] = "images/";
            $config['allowed_types'] = ['jpg', 'png', 'bmp', 'gif'];
            $config['max_size'] = 1000; // in KB, default 1000KB = 1MB

            $url = MyFunc::upload_s3($request, $config);

            return json(['message' => 'Upload to S3 success !', 'url' => $url]);
        } catch (\Throwable $th) {
            return json(['message' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }
}
