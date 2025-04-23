<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use support\Request;
use Webman\Route;
use Webman\Push\Api;

/**
 * Push js client files
 */
Route::get('/plugin/webman/push/push.js', function (Request $request) {
    return response()->file(base_path().'/vendor/webman/push/src/push.js');
});

/**
 * Private channel authentication, here you should use session to identify the current user's identity, and then determine whether the user has permission to listen to channel_name
 */
Route::post(config('plugin.webman.push.app.auth'), function (Request $request) {
    $pusher = new Api(str_replace('0.0.0.0', '127.0.0.1', config('plugin.webman.push.app.api')), config('plugin.webman.push.app.app_key'), config('plugin.webman.push.app.app_secret'));
    $channel_name = $request->post('channel_name');
    $session = $request->session();
    // Here, we should use session and channel_name to determine whether the current user has permission to listen to channel_name.
    $has_authority = true;
    if ($has_authority) {
        return response($pusher->socketAuth($channel_name, $request->post('socket_id')));
    } else {
        return response('Forbidden', 403);
    }
});

/**
 * Callback triggered when the channel is online and offline
 * Channel online: refers to an event in which a channel has not been connected to online.
 * Channel offline: refers to an event triggered by all connections of a channel being disconnected
 */
Route::post(parse_url(config('plugin.webman.push.app.channel_hook'), PHP_URL_PATH), function (Request $request) {

    // No x-pusher-signature header is considered a forgery request
    if (!$webhook_signature = $request->header('x-pusher-signature')) {
        return response('401 Not authenticated', 401);
    }

    $body = $request->rawBody();

    // Calculate the signature. $app_secret is the key used by both parties and is confidential and has no way to know it outside.
    $expected_signature = hash_hmac('sha256', $body, config('plugin.webman.push.app.app_secret'), false);

    // Security verification, if the signature is inconsistent, it may be a forged request, return 401 status code
    if ($webhook_signature !== $expected_signature) {
        return response('401 Not authenticated', 401);
    }

    // Here is the channel data that is online and offline
    $payload = json_decode($body, true);

    $channels_online = $channels_offline = [];

    foreach ($payload['events'] as $event) {
        if ($event['name'] === 'channel_added') {
            $channels_online[] = $event['channel'];
        } else if ($event['name'] === 'channel_removed') {
            $channels_offline[] = $event['channel'];
        }
    }

    // The service handles the up and down channel as needed, such as writing the online status to the database, notifying other channels, etc.
    // All channels online
    echo 'online channels: ' . implode(',', $channels_online) . "\n";
    // All channels offline
    echo 'offline channels: ' . implode(',', $channels_offline) . "\n";

    return 'OK';
});
