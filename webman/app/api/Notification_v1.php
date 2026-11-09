<?php

namespace app\api;

use support\Request;
use support\Db;
use support\MyFunc;
use Firuze\Jwt\JwtToken;
use support\Redis;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

class Notification_v1
{
    protected $noNeedLogin = ['index', 'device_sync', 'notification_status_sync', 'notifications_sync'];

    protected $validatorDesc = [
        'attribute' => 'Params [{{name}}] is required',
        'uuid' => '[{{name}}] must be a valid UUID',
        'stringType' => '[{{name}}] must be a string type',
        'dateTime' => '[{{name}}] must be a valid date/time',
        'arrayType' => '[{{name}}] must be a array type of string',
        'objectType' => '[{{name}}] must be type of object',
        'json' => '[{{name}}] must be a valid JSON string',
        'intType' => '[{{name}}] must be integer',
        'email' => '[{{name}}] must be a valid email',
        'boolType' => '[{{name}}] must be a boolean type',
        'length' => '[{{name}}] length must be between {{minValue}} and {{maxValue}}',
        'number' => '[{{name}}] must be a number',
        'notEmpty' => '[{{name}}] must not empty',
        'noWhitespace' => '[{{name}}] cannot contain spaces',
    ];

    public function index(Request $request)
    {
        // $channel_name = 'D-emu64a:UE1A.230829.036.A1';
        // $event = 'notification';
        // $data['from_uid'] = 0;
        // $data['title'] = 'Hanya title';
        // $data['message'] = 'Hanya message biasa !';
        // $socket_id = '';
        // MyFunc::send_notif($channel_name, $event, $data, $socket_id);
        return jsonr(['message' => "This is Notification API v1"]);
    }

    /**
     * Create a new notification
     */
    public function create(Request $request)
    {
        $user_id = JwtToken::getCurrentId();
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('topic', v::stringType()->noWhitespace()->notEmpty())
                ->attribute('title', v::stringType()->notEmpty())
                ->attribute('body', v::stringType()->notEmpty())
                ->attribute('image', v::nullable(v::stringType()->notEmpty()), false)
                ->attribute('payload', v::nullable(v::json()->notEmpty()), false)
                ->attribute('priority', v::stringType()->notEmpty())
                ->attribute('action_type', v::stringType()->notEmpty())
                ->attribute('action_value', v::nullable(v::stringType()), false)
                ->attribute('publish_at', v::nullable(v::dateTime()->notEmpty()), false)
                ->attribute('expired_at', v::nullable(v::dateTime()->notEmpty()), false)
                // ->attribute('publish_at', v::dateTime()->notEmpty(), false)
                // ->attribute('is_active', v::boolType()->notEmpty(), false)
                ->attribute('target_type', v::stringType()->notEmpty())
                ->attribute('device_id', v::nullable(v::stringType()->notEmpty()), false)
                ->attribute('user_id', v::nullable(v::intType()->notEmpty()), false)
                ->attribute('group_id', v::nullable(v::uuid()->notEmpty()), false);
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage], 400);
        }

        try {
            $data->user_id = $user_id;
            $result =  MyFunc::create_notif($data);
            return jsonr($result);
        } catch (\Throwable $e) {
            return jsonr(['message' => $e->getMessage()]);
        }
    }

    public function update(Request $request)
    {
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('id', v::intType())
                ->attribute('topic', v::stringType()->noWhitespace()->notEmpty(), false)
                ->attribute('title', v::stringType()->notEmpty(), false)
                ->attribute('body', v::stringType()->notEmpty(), false)
                ->attribute('image', v::nullable(v::stringType()->notEmpty()), false)
                ->attribute('payload', v::nullable(v::json()->notEmpty()), false)
                ->attribute('priority', v::stringType()->notEmpty(), false)
                ->attribute('action_type', v::stringType()->notEmpty(), false)
                ->attribute('action_value', v::nullable(v::stringType()), false)
                ->attribute('publish_at', v::dateTime()->notEmpty(), false)
                ->attribute('expired_at', v::nullable(v::dateTime()->notEmpty()), false)
                ->attribute('is_active', v::boolType()->notEmpty(), false)
                ->attribute('target_type', v::stringType()->notEmpty(), false)
                ->attribute('device_id', v::nullable(v::stringType()->notEmpty()), false)
                ->attribute('user_id', v::nullable(v::intType()->notEmpty()), false)
                ->attribute('group_id', v::nullable(v::uuid()->notEmpty()), false);
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage], 400);
        }

        Db::beginTransaction();
        try {
            $fields = [];
            $table_fields = ['topic', 'title', 'body', 'image', 'payload', 'priority', 'action_type', 'action_value', 'publish_at', 'expired_at', 'is_active', 'target_type', 'device_id', 'user_id', 'group_id'];
            foreach ($data as $key => $value) {
                if (in_array($key, $table_fields)) {
                    $fields[$key] = $value;
                }
            }

            Db::table('notifications')->where(['id' => $data->id])->update($fields);

            $fields['id'] = $data->id;
            $result = $fields;

            Db::commit();

            // send push notification
            // $target_value = $data->target_type == 'SPECIFIC_GUEST' ? "D-{$data->device_id}" : '';
            // $target_value = $data->target_type == 'SPECIFIC_USER' ? "U-{$data->user_id}" : $target_value;
            // $target_value = $data->target_type == 'SPECIFIC_GROUP' ? "G-{$data->group_id}" : $target_value;
            // self::_send_push_notification($data->target_type, $target_value);
        } catch (\Throwable $e) {
            Db::rollBack();
            return jsonr(['message' => $e->getMessage()]);
        }

        return jsonr($result);
    }

    public function delete(Request $request)
    {
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('id', v::arrayType()->notEmpty());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage], 400);
        }

        Db::beginTransaction();
        try {

            Db::table('notifications')->whereIn('id', $data->id)->delete();

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            return jsonr(['message' => $e->getMessage()]);
        }

        return jsonr(['message' => 'done']);
    }

    // User state read
    public function user_read(Request $request)
    {
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('notification_id', v::stringType()->noWhitespace()->notEmpty())
                ->attribute('device_id', v::stringType()->noWhitespace()->notEmpty())
                ->attribute('user_id', v::nullable(v::intType()->noWhitespace()->notEmpty()));
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage], 400);
        }

        Db::beginTransaction();
        try {

            Db::table('notification_status')->updateOrInsert(
                ['notification_id' => $data->notification_id, 'user_id' => $data->user_id, 'device_id' => $data->device_id],
                ['is_read' => true, 'updated_at' => date('Y-m-d H:i:s')]
            );

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            return jsonr(['message' => $e->getMessage()]);
        }

        return jsonr(['message' => 'done']);
    }

    // User state archive
    public function user_archive(Request $request)
    {
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('notification_id', v::stringType()->noWhitespace()->notEmpty())
                ->attribute('device_id', v::stringType()->noWhitespace()->notEmpty())
                ->attribute('user_id', v::nullable(v::intType()->noWhitespace()->notEmpty()));
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage], 400);
        }

        Db::beginTransaction();
        try {

            Db::table('notification_status')->updateOrInsert(
                ['notification_id' => $data->notification_id, 'user_id' => $data->user_id, 'device_id' => $data->device_id],
                ['is_archived' => true, 'updated_at' => date('Y-m-d H:i:s')]
            );

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            return jsonr(['message' => $e->getMessage()]);
        }

        return jsonr(['message' => 'done']);
    }

    // User state delete
    public function user_delete(Request $request)
    {
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('notification_id', v::stringType()->noWhitespace()->notEmpty())
                ->attribute('device_id', v::stringType()->noWhitespace()->notEmpty())
                ->attribute('user_id', v::nullable(v::intType()->noWhitespace()->notEmpty()));
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage], 400);
        }

        Db::beginTransaction();
        try {

            Db::table('notification_status')->updateOrInsert(
                ['notification_id' => $data->notification_id, 'user_id' => $data->user_id, 'device_id' => $data->device_id],
                ['is_deleted' => true, 'updated_at' => date('Y-m-d H:i:s')]
            );

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            return jsonr(['message' => $e->getMessage()]);
        }

        return jsonr(['message' => 'done']);
    }

    /**
     *  Device Sync from Mobile Apps triggerd by Apps Start
     *  
     *  {
     *      "id": "emu64a:AE3A.240806.036",
     *      "name": "GOOGLE sdk_gphone64_arm64",
     *      "platform": "android",
     *      "user_id": null
     *  }
     */
    public function device_sync(Request $request)
    {
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('id', v::stringType()->noWhitespace()->notEmpty())
                ->attribute('name', v::stringType()->notEmpty())
                ->attribute('platform', v::stringType()->notEmpty())
                ->attribute('user_id', v::nullable(v::stringType()->noWhitespace()->notEmpty()), false);
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage], 400);
        }

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            Db::table('devices')->updateOrInsert(
                ['id' => $data->id],
                ['name' => $data->name, 'platform' => $data->platform, 'updated_at' => date('Y-m-d H:i:s')]
            );

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            return jsonr(['message' => $e->getMessage()]);
        }

        return jsonr(['message' => 'done']);
    }

    /**
     *  Get latest notification triggered by Push-Notification/Pusher/WebSocket/First App Start/Background Sync
     *  
     *  {
     *       "last_sync": "2026-07-23 12:00:00",
     *       "device_id": "emu64a:AE3A.240806.036",
     *       "user_id": null,
     *       "topic": ["maintenance","promo","general","registration","newcommers"]
     *  }
     */
    public function notifications_sync(Request $request)
    {
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('last_sync', v::nullable(v::dateTime()))
                ->attribute('device_id', v::stringType()->noWhitespace()->notEmpty(), false)
                ->attribute('user_id', v::nullable(v::intType()->noWhitespace()->notEmpty()), false)
                ->attribute('topic', v::arrayType()->notEmpty());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage], 400);
        }

        // REDIS CHECK STAGE
        // ===================
        // try {
        //     $dateTime = new \DateTime($data->last_sync);
        //     $redisKey = "{$dateTime->format('Y-m-d_H:i')}_{$data->device_id}_{$data->user_id}_" . md5(json_encode($data->topic));
        //     $redisVal = Redis::get($redisKey);
        //     if ($redisVal != null) {
        //         $result = json_decode($redisVal);
        //         $result->redis = true;
        //         return jsonr($result);
        //     }
        // } catch (\Throwable $th) {
        //     return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()], $th->getCode());
        // }

        Db::beginTransaction();
        try {
            $data->last_sync = $data->last_sync ?? date('Y-m-d H:i:s');

            $notifications = Db::table('notifications')
                ->selectRaw('id, topic, title, body, image, payload, priority, action_type, action_value, publish_at, expired_at, user_id')
                ->where('publish_at', '>', $data->last_sync)
                ->where(function ($query) use ($data) {
                    $device_id = isset($data->device_id) ? $data->device_id : null;
                    $user_id = isset($data->user_id) ? $data->user_id : null;
                    if ($user_id == null) {
                        $query->where('device_id', '=', $device_id);
                    } else {
                        $query->where('user_id', '=', $user_id);
                    }
                })
                ->where(function ($query) {
                    $query->where('expired_at', '>', date('Y-m-d H:i:s'))
                        ->orWhereNull('expired_at');
                })
                ->whereIn('topic', $data->topic)
                ->where('is_active', true)
                ->orderBy('publish_at', 'desc')
                ->get()->toArray();

            $result['last_sync'] = date('Y-m-d H:i:s');
            $result['notifications'] = [];
            $result['states'] = [];
            if (count($notifications) > 0) {
                $result['last_sync'] = $notifications[0]->publish_at;
                $result['notifications'] = $notifications;

                // and also get notification status state 
                $notification_status = Db::table('notification_status')
                    ->selectRaw('notification_id, device_id, user_id, is_read, is_deleted, is_archived, updated_at')
                    ->whereIn('notification_id', array_column($notifications, 'id'))
                    ->get();

                if (count($notification_status) > 0) {
                    $result['states'] = $notification_status;
                }
            }

            // Get also notification status state where not in above scope and update_at > Last Sync
            $notification_status = Db::table('notification_status')
                ->selectRaw('notification_id, device_id, user_id, is_read, is_deleted, is_archived, updated_at')
                ->whereNotIn('notification_id', array_column($notifications, 'id'))
                ->where(function ($query) use ($data) {
                    $device_id = isset($data->device_id) ? $data->device_id : null;
                    $user_id = isset($data->user_id) ? $data->user_id : null;
                    if ($user_id == null) {
                        $query->where('device_id', '=', $device_id);
                    } else {
                        $query->where('user_id', '=', $user_id);
                    }
                })
                ->where('updated_at', '>', $data->last_sync)
                ->get();

            if (count($notification_status) > 0) {
                $result['states'] = [...$result['states'], ...$notification_status];
                // $result['states'] = array_merge($result['states'], $notification_status);
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            return jsonr(['message' => $e->getMessage(), "trace" => $e->getTrace()]);
        }

        // Save to Redis
        // Redis::set($redisKey, json_encode($result));
        // Redis::expire($redisKey, 30);

        return jsonr($result);
    }

    /**
     *  Syncronized user notification states From Mobile Apps triggerd by onChanged or with Background Sync
     *  
     *  {
     *      "notification_id": "fd46407b-ec54-4888-ab8d-03948102171f",
     *      "device_id": "emu64a:AE3A.240806.036",
     *      "user_id": null,
     *      "is_read": true,
     *      "is_delete": true
     *  }
     *  
     */
    public function notification_status_sync(Request $request)
    {
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('notification_id', v::intType()->noWhitespace()->notEmpty())
                ->attribute('device_id', v::stringType()->noWhitespace()->notEmpty())
                ->attribute('user_id', v::nullable(v::intType()->noWhitespace()->notEmpty()))
                ->attribute('is_read', v::boolType())
                ->attribute('is_deleted', v::boolType())
                ->attribute('is_archived', v::boolType());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage], 400);
        }

        Db::beginTransaction();
        try {

            Db::table('notification_status')->updateOrInsert(
                ['notification_id' => $data->notification_id, 'user_id' => $data->user_id, 'device_id' => $data->device_id],
                ['is_read' => $data->is_read, 'is_deleted' => $data->is_deleted, 'is_archived' => $data->is_archived, 'updated_at' => date('Y-m-d H:i:s')]
            );

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            return jsonr(['message' => $e->getMessage()]);
        }

        return jsonr(['message' => 'done']);
    }

    public function garbage_collection(Request $request)
    {
        // Umur notifikasi < 2 minggu
        $notification_old = 60 * 60 * 24 * 14; // 2 minggu
        $notification_status_old = 60 * 60 * 24 * 14; // 2 minggu

        Db::beginTransaction();
        try {
            Db::table('notifications')->where('publish_at', '<', date('Y-m-d H:i:s', time() - $notification_old))->delete();
            Db::table('notification_status')->where('updated_at', '<', date('Y-m-d H:i:s', time() - $notification_status_old))->delete();
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            return jsonr(['message' => $e->getMessage()]);
        }

        return jsonr(['message' => 'done']);
    }
}
