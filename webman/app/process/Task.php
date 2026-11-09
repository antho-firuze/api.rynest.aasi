<?php

namespace app\process;

use Workerman\Crontab\Crontab;
use support\Db;
use Workerman\Worker;
use support\MyFunc;

class Task
{
    public function onWorkerStart()
    {
        // // Run every second
        new Crontab('*/1 * * * * *', function () {
            // echo date('Y-m-d H:i:s') . "\n";
        });

        // Run every 30 seconds
        new Crontab('*/30 * * * * *', function () {
            // $this->check_publish_notification();
        });

        // // Run every minute
        new Crontab('0 */1 * * * *', function () {
            // echo date('Y-m-d H:i:s') . "\n";
            self::_update_exam_status();
            self::check_publish_notification();
            self::clean_exam_session_apps();
        });

        // Run every 5 minutes
        new Crontab('0 */5 * * * *', function () {
            // $this->clean_broadcast_garbage();
        });

        // Run every hour
        new Crontab('0 * * * * *', function () {
            // echo date('Y-m-d H:i:s') . "\n";
        });

        // Run at 23:00pm every day. Note that the second field is omitted here
        new Crontab('0 23 * * *', function () {
            // $this->clean_location_garbage();
        });
    }

    // Cronjob for update an exam status where timed has been execeded, executed every minutes.
    // Duration add 120 minutes (for safety timezone WITA+1/WIT+2)
    function _update_exam_status()
    {
        try {
            // Db::table('exam_results')
            //     ->where('status', '=', '')
            //     ->where('start_at', '<>', null)
            //     ->where('finish_at', '=', null)
            //     ->whereRaw('(start_at + INTERVAL (duration + 120) MINUTE) < NOW()')
            //     ->update([
            //         'status' => 'completed',
            //         'finish_at' => date('Y-m-d H:i:s'),
            //         'note' => 'update status [completed] by system',
            //     ]);
            Db::table('exam_results')
                ->where(function ($query) {
                    $query->where('status', '=', '')
                        ->orWhereNull('status');
                })
                // ->where('start_at', '<>', null)
                // ->where('finish_at', '=', null)
                ->whereNotNull('start_at')
                ->whereRaw("start_at > '1970-01-01 00:00:00'")
                ->where('start_at', '>', '2025-08-01 00:00:00')
                ->whereNull('finish_at')
                ->whereRaw('(start_at + INTERVAL (duration + 120) MINUTE) < NOW()')
                ->update([
                    'status' => 'completed',
                    'finish_at' => date('Y-m-d H:i:s'),
                    'note' => 'update status [completed] by system',
                ]);
        } catch (\Throwable $e) {
            Worker::log('CRON _update_exam_status ERROR: ' . $e->getMessage());
        }
    }

    private function _send_push_notification(String $target_type, String $target_value = '')
    {
        switch (strtoupper($target_type)) {
            case 'GUEST':
                $channel_name = "guest-channel";
                $event = "notification";
                $data = ["type" => "notification"];
                $socket_id = '';
                MyFunc::send_notif($channel_name, $event, $data, $socket_id);
                break;
            case 'USER':
                $channel_name = "presence-channel";
                $event = "notification";
                $data = ["type" => "notification"];
                $socket_id = '';
                MyFunc::send_notif($channel_name, $event, $data, $socket_id);
                break;
            case 'SPECIFIC_USER':
            case 'SPECIFIC_GUEST':
                $channel_name = $target_value;
                $event = "notification";
                $data = ["type" => "notification"];
                $socket_id = '';
                MyFunc::send_notif($channel_name, $event, $data, $socket_id);
                break;
            default:
                self::_send_push_notification('GUEST');
                self::_send_push_notification('USER');
                break;
        }
    }

    private function check_publish_notification()
    {
        // echo date('Y-m-d H:i:s') . " => Checking Publish Notification every 30 seconds\n";
        Db::beginTransaction();
        try {
            $notifications = Db::table('notifications')
                ->where('is_active', false)
                ->where('publish_at', '<=', date('Y-m-d H:i:s'))
                ->get();
            
            echo date('Y-m-d H:i:s') . " => Found " . count($notifications) . " notifications to publish\n";

            foreach ($notifications as $notif) {
                // update is_active to true
                Db::table('notifications')
                    ->where('id', $notif->id)
                    ->update(['is_active' => true]);

                // send push notification
                $target_value = $notif->target_type == 'SPECIFIC_GUEST' ? "D-{$notif->device_id}" : '';
                $target_value = $notif->target_type == 'SPECIFIC_USER' ? "U-{$notif->user_id}" : $target_value;
                $target_value = $notif->target_type == 'SPECIFIC_GROUP' ? "G-{$notif->group_id}" : $target_value;
                self::_send_push_notification($notif->target_type, $target_value);
            }

            Db::commit();
        } catch (\Throwable $th) {
            Db::rollBack();
        }
    }

    private function update_broadcast_state()
    {
        // echo date('Y-m-d H:i:s') . " => Cleaning Broadcast every 15 second\n";
        $obsolete = date('Y-m-d H:i:s', strtotime('-15 second'));
        Db::table('presenter')->where('heartbeat', '<', $obsolete)->update(['state' => 'inactive']);
        Db::table('audience')->where('heartbeat', '<', $obsolete)->update(['state' => 'inactive']);
    }

    private function clean_broadcast_garbage()
    {
        // echo date('Y-m-d H:i:s') . " => Cleaning Broadcast every 5 minutes\n";
        $obsolete = date('Y-m-d H:i:s', strtotime('-5 minute'));
        Db::table('presenter')->where('heartbeat', '<', $obsolete)->delete();
        Db::table('audience')->where('heartbeat', '<', $obsolete)->delete();
    }

    private function clean_location_garbage()
    {
        // echo date('Y-m-d H:i:s') . " => Cleaning Location every 1 day at 23:00pm\n";
        $obsolete = date('Y-m-d H:i:s', strtotime('-1 day'));
        Db::table('log_location')->where('time', '<', $obsolete)->delete();
        Db::table('live_location')->where('heartbeat', '<', $obsolete)->delete();
    }

    private function clean_exam_session_apps()
    {
        // echo date('Y-m-d H:i:s') . " => Cleaning Exam Session Apps every 1 hour\n";
        Db::table('exam_session_apps')
            ->whereRaw('(created_at + INTERVAL 100 MINUTE) < NOW()')
            ->delete();
    }
}
