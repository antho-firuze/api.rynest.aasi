<?php

namespace app\controller;

use support\Db;
use support\MyFunc;
use support\Redis;
use support\Request;
use Webman\Push\Api;

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

    public function json(Request $request)
    {
        $user = Db::table('tbl_users')->first();
        return json($user);
        return json(['code' => 0, 'msg' => 'ok']);
    }

    public function redis(Request $request)
    {
        $data = (object) $request->get();
        $redisKey = 'key1';
        try {
            if ($data->state == 'set') {
                Redis::set($redisKey, 'cba');
                Redis::expire($redisKey, 10);
                return json('done');
            } else {
                $result = Redis::get($redisKey);
                return json($result);
            }
        } catch (\Throwable $th) {
            return json(['message' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }

    public function crontest(Request $request)
    {
        // Cronjob for update an exam status where timed has been execeded, executed every minutes.
        $exam_result = Db::table('exam_results')
            ->select('*')
            ->where('status', '=', '')
            ->where('start_at', '<>', null)
            ->where('finish_at', '=', null)
            ->whereRaw('(start_at + INTERVAL duration MINUTE) > NOW()')
            ->get();

        return json($exam_result);
    }

    function send_notif(Request $request)
    {
        // $pusher = new Api(
        //     str_replace('0.0.0.0', '127.0.0.1', config('plugin.webman.push.app.api')),
        //     config('plugin.webman.push.app.app_key'),
        //     config('plugin.webman.push.app.app_secret')
        // );
        // // Push a message event to all clients subscribed to user-1
        // $channel_name = 'public-channel';
        // $event = 'message';
        // $data['from_uid'] = 0;
        // $data['title'] = 'Hanya title';
        // $data['message'] = 'Hanya message biasa !';
        // $pusher->trigger($channel_name, $event, $data, $socket_id);

        // $channel_name = 'public-channel';
        // $event = 'message';
        // $data['from_uid'] = 0;
        // $data['title'] = 'Hanya title';
        // $data['message'] = 'Hanya message biasa !';

        $channel_name = "private-user-400";
        $event = 'intrusion';
        $payload['code'] = 409;
        $payload['device_id'] = "emu64a:UE1A.230829.036.A23";
        $payload['title'] = 'Deteksi Gangguan';
        $payload['message'] = 'Perangkat lain telah login menggunakan akun Anda !';
        MyFunc::send_notif($channel_name, $event, $payload);
    }
}
