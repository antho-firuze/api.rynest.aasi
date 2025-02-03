<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['socket_type'] = 'tcp'; //`tcp` or `unix`
$config['socket'] = '/var/run/redis.sock'; // in case of `unix` socket type
$config['host'] = $_ENV["REDIS_HOST"];
$config['password'] = $_ENV["REDIS_PASS"];
$config['port'] = $_ENV["REDIS_PORT"];
$config['timeout'] = 0;