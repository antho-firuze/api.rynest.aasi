<?php

namespace app\api;

use support\Request;
use Firuze\Jwt\JwtToken;
use support\Db;
use Webman\Push\Api;

class Pusher_v1
{
    protected $noNeedLogin = ['index', 'auth'];

    public function index(Request $request)
    {
        return json(['message' => "Rynest => Pusher API v1"]);
    }

    public function auth(Request $request)
    {
        $pusher = new Api(str_replace('0.0.0.0', '127.0.0.1', config('plugin.webman.push.app.api')), config('plugin.webman.push.app.app_key'), config('plugin.webman.push.app.app_secret'));
        $channel_name = $request->post('channel_name');
        $socket_id = $request->post('socket_id');
        $user_id = $request->post('user_id');
        $user_info = $request->post('user_info');
        $session = $request->session();
        // Here, we should use session and channel_name to determine whether the current user has permission to listen to channel_name.
        $has_authority = true;
        if ($has_authority) {
            if (strpos($channel_name, 'private-') === 0) {
                $result = $pusher->socketAuth($channel_name, $socket_id);
                return response($result);
            } elseif (strpos($channel_name, 'presence-') === 0) {
                $result = $pusher->presenceAuth($channel_name, $socket_id, $user_id, $user_info);
                return response($result);
            }
        } else {
            return response('Forbidden', 403);
        }
    }

    // {
    //     "channel_name": "private-user1",
    //     "socket_id": 1.151,
    //     "event": "client-message",
    //     "data": {
    //         "message": "hello"
    //     }
    // }
    public function trigger(Request $request)
    {
        $pusher = new Api(
            str_replace('0.0.0.0', '127.0.0.1', config('plugin.webman.push.app.api')), 
            config('plugin.webman.push.app.app_key'), 
            config('plugin.webman.push.app.app_secret')
        );

        $channel_name = $request->post('channel_name');
        $event = $request->post('event');
        $data = $request->post('data');
        $socket_id = $request->post('socket_id');
        $result = $pusher->trigger($channel_name, $event, $data, $socket_id);
        if ($result) {
            return json(['message' => 'success']);
        }

        return jsonr(['message' => 'failed']);
    }

    // {
    //     "channel_name": "private-user1",
    //     "params": {
    //         "info": "user_count,subscription_count"
    //     }
    // }
    public function channel_info(Request $request)
    {
        $pusher = new Api(str_replace('0.0.0.0', '127.0.0.1', config('plugin.webman.push.app.api')), config('plugin.webman.push.app.app_key'), config('plugin.webman.push.app.app_secret'));
        $channel_name = $request->post('channel_name') ?? null;
        $params = $request->post('params') ?? [];
        $result = $pusher->getChannelInfo($channel_name, $params);
        return json($result);
    }
    
    public function channel_info_users(Request $request)
    {
        $pusher = new Api(str_replace('0.0.0.0', '127.0.0.1', config('plugin.webman.push.app.api')), config('plugin.webman.push.app.app_key'), config('plugin.webman.push.app.app_secret'));
        $channel_name = $request->post('channel_name') ?? null;
        $params = $request->post('params') ?? [];

        $result = $pusher->get("/channels/$channel_name/users", $params);

        return json($result);
    }
    
    public function channels(Request $request)
    {
        $pusher = new Api(str_replace('0.0.0.0', '127.0.0.1', config('plugin.webman.push.app.api')), config('plugin.webman.push.app.app_key'), config('plugin.webman.push.app.app_secret'));
        $params = $request->post('params') ?? [];
        $result = $pusher->getChannels($params);
        return json($result);
    }
}
