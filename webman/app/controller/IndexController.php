<?php

namespace app\controller;

use support\Db;
use support\Redis;
use support\Request;

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
        Redis::set('abc', 'cba');
        return json(Redis::get('abc'));
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
}
