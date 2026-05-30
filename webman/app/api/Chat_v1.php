<?php

namespace app\api;

use support\Request;
use Firuze\Jwt\JwtToken;
use support\Db;

class Chat_v1
{
    protected $noNeedLogin = ['index', 'user', 'member', 'update_schedule', 'clear_exam'];

    public function index(Request $request)
    {
        return json(['message' => "Rynest => Admin API v1"]);
    }

    

}
