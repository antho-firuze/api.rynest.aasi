<?php

namespace support;

use Exception;
use support\Request;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Webman\Push\Api;

class MyFunc
{
    /**
     * Generate a "Random" Code with definition length
     *
     * @param	int	$len        number of characters
     * @param	string $type	Type of random string.  uppercase|lowercase|numeric
     * @return	string
     */
    static function generate_code(int $len = 6, string $type = 'numeric|uppercase')
    {
        $data = [
            'numeric'   => '123456789',
            'lowercase' => 'abcdefghijklmnopqrstuvwxyz',
            'uppercase' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        ];

        $pool = '';
        $types = explode('|', $type);
        foreach ($types as $v)
            $pool .= isset($data[$v]) ? $data[$v] : '';

        return substr(str_shuffle(str_repeat($pool, ceil($len / strlen($pool)))), 0, $len);
    }

    /**
     * Return a formatted string
     *
     * @param string $str
     * @param array $vars     Paired value array
     * @param string $prefix  Default '{'
     * @param string $suffix  Default '}'
     * @return void
     */
    static function sprintfx(string $str, array $vars, string $prefix = '{', string $suffix = '}')
    {
        if (array() === $vars)
            return $str;

        foreach ($vars as $key => $val)
            $arr[$prefix . $key . $suffix] = $val;

        return str_replace(array_keys($arr), array_values($arr), $str);
    }

    static function upload_file(Request $request, $config = [])
    {
        $folder = $config['folder'] ?? '';
        $userfile = $config['userfile'] ?? 'userfile';
        $allowed_types = $config['allowed_types'] ?? ['jpg', 'png', 'bmp', 'gif'];
        $max_size = $config['max_size'] ?? 1000;     // in KB, default 1000KB = 1MB

        $file = $request->file($userfile);
        if ($file == null) {
            throw new Exception(message: "Param [{$userfile}] is required");
        }

        $file_size = $file->getSize() / 1000;  // convert to KB, actual in Bytes
        if ($file_size < 1) {
            throw new Exception(message: "[{$userfile}] must not empty");
        } else if ($file_size > $max_size) {
            throw new Exception(message: "[{$userfile}] file size not allowed, max size: {$max_size} KB");
        }

        $file_ext = $file->getUploadExtension();
        if (!in_array($file_ext, $allowed_types)) {
            $allowedtypes = implode("|", $allowed_types);
            throw new Exception(message: "[{$userfile}] file extension not allowed, except: [{$allowedtypes}]");
        }

        $file_name = $config['file_name'] ?? $file->getUploadName();
        $file_name = "{$file_name}.{$file_ext}";

        try {
            $relative_path = "{$folder}{$file_name}";

            // Move file to destination folder
            $upload_path = public_path(path: "{$folder}{$file_name}");
            $file->move($upload_path);

            $protocol = $config['protocol'] ?? $request->header('x-forwarded-proto');
            $protocol = $protocol ?? 'http';
            $host = $request->host();
            $result = "{$protocol}://{$host}/{$relative_path}";
            return $result;
        } catch (\Throwable $e) {
            throw new Exception(message: $e->getMessage());
        }
    }

    static function upload_s3(Request $request, $config = [])
    {
        $folder = $config['folder'] ?? '';
        $userfile = $config['userfile'] ?? 'userfile';
        $allowed_types = $config['allowed_types'] ?? ['jpg', 'png', 'bmp', 'gif'];
        $max_size = $config['max_size'] ?? 1000;     // in KB, default 1000KB = 1MB

        $file = $request->file($userfile);
        if ($file == null) {
            throw new Exception(message: "Param [{$userfile}] is required");
        }

        $file_size = $file->getSize() / 1000;  // convert to KB, actual in Bytes
        if ($file_size < 1) {
            throw new Exception(message: "[{$userfile}] must not empty");
        } else if ($file_size > $max_size) {
            throw new Exception(message: "[{$userfile}] file size not allowed, max size: {$max_size} KB");
        }

        $file_ext = $file->getUploadExtension();
        if (!in_array($file_ext, $allowed_types)) {
            $allowedtypes = implode("|", $allowed_types);
            throw new Exception(message: "[{$userfile}] file extension not allowed, except: [{$allowedtypes}]");
        }

        $file_name = $config['file_name'] ?? $file->getUploadName();
        $file_name = "{$file_name}.{$file_ext}";

        try {
            $aws_key = getenv('AWS_ACCESS_KEY_ID');
            $aws_secret = getenv('AWS_SECRET_ACCESS_KEY');
            $region = getenv('AWS_DEFAULT_REGION');
            $bucket = getenv('AWS_BUCKET');

            $s3 = new S3Client([
                'region' => $region,
                'credentials' => ['key' => $aws_key, 'secret' => $aws_secret],
                'http' => [
                    'connect_timeout' => 5,
                    'timeout' => 10,
                ],
            ]);

            if ($file && $file->isValid()) {
                // Move file to temporary folder
                // for getting mime/type (information file)
                $tmp_path = runtime_path(path: $file_name);
                $file->move($tmp_path);

                $result = $s3->putObject([
                    'Bucket' => $bucket,
                    'SourceFile' => $tmp_path,
                    'Key' => $folder . $file_name,
                ]);

                // Remove temporary file after uploaded to AWS S3
                @unlink($tmp_path);

                $result = $result->toArray();
                return $result['ObjectURL'];
            }
        } catch (S3Exception $e) {
            throw new Exception(message: $e->getMessage());
        }
    }

    static function view_s3_content(Request $request, $config = [])
    {
        $folder = $config['folder'] ?? '';
        $file_name = $config['file_name'] ?? '';
        if (empty($file_name)) {
            throw new Exception(message: "Param [file_name] is required");
        }

        try {
            $aws_key = getenv('AWS_ACCESS_KEY_ID');
            $aws_secret = getenv('AWS_SECRET_ACCESS_KEY');
            $region = getenv('AWS_DEFAULT_REGION');
            $bucket = getenv('AWS_BUCKET');

            $s3 = new S3Client([
                'region' => $region,
                'credentials' => ['key' => $aws_key, 'secret' => $aws_secret],
                'http' => [
                    'connect_timeout' => 5,
                    'timeout' => 10,
                ],
            ]);

            // Siapkan perintah untuk mengambil objek dari S3
            $command = $s3->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key' => "{$folder}{$file_name}",
            ]);

            // Buat URL ber-tanda tangan digital yang hanya valid selama 10 menit
            $request = $s3->createPresignedRequest($command, '+10 minutes');

            // Ambil URL string-nya
            $secureUrl = (string) $request->getUri();

            return $secureUrl;

            // Get the object from S3
            // $result = $s3->getObject([
            //     'Bucket' => $bucket,
            //     'Key' => "{$folder}{$file_name}",
            // ]);

            // return $result->toArray();

            // Set the appropriate headers for the response
            // header("Content-Type: " . $result['ContentType']);
            // header("Content-Length: " . $result['ContentLength']);
            // header("Content-Disposition: inline; filename=\"" . basename($file_name) . "\"");

            // // Output the content of the file
            // echo (string) $result['Body'];
        } catch (S3Exception $e) {
            throw new Exception(message: $e->getMessage());
        }
    }

    static function send_notif(string $channel_name, string $event, array $data, string $socket_id = '')
    {
        $pusher = new Api(
            str_replace('0.0.0.0', '127.0.0.1', config('plugin.webman.push.app.api')),
            config('plugin.webman.push.app.app_key'),
            config('plugin.webman.push.app.app_secret')
        );
        // // Push a message event to all clients subscribed to user-1
        // $channel_name = 'public-channel';
        // $event = 'message';
        // $data['from_uid'] = 0;
        // $data['title'] = 'Hanya title';
        // $data['message'] = 'Hanya message biasa !';
        // $socket_id = '';
        $pusher->trigger($channel_name, $event, $data, $socket_id);
    }

    // create new notif save to database and send_notif to user
    static function create_notif(object $data): array
    {
        Db::beginTransaction();
        try {
            $user_id = $data->user_id;

            $fields = [];
            $table_fields = ['topic', 'title', 'body', 'image', 'payload', 'priority', 'action_type', 'action_value', 'publish_at', 'expired_at', 'is_active', 'target_type', 'device_id', 'user_id', 'group_id'];
            foreach ($data as $key => $value) {
                if (in_array($key, $table_fields)) {
                    $fields[$key] = $value;
                }
            }
            // post-processed fields
            $fields['created_by'] = $user_id;
            if (!isset($data->publish_at) || $data->publish_at == null) {
                $fields['publish_at'] = date('Y-m-d H:i:s');
                $fields['is_active'] = true;
            } else if ($data->publish_at > date('Y-m-d H:i:s', strtotime('+1 minute'))) {
                $fields['is_active'] = false;
            } else {
                $fields['is_active'] = true;
            }

            $insert_id = Db::table('notifications')->insertGetId($fields);
            $fields['id'] = $insert_id;

            $result = $fields;

            // send push notification
            $target_value = $data->target_type == 'SPECIFIC_GUEST' ? "D-{$data->device_id}" : '';
            $target_value = $data->target_type == 'SPECIFIC_USER' ? "U-{$data->user_id}" : $target_value;
            $target_value = $data->target_type == 'SPECIFIC_GROUP' ? "G-{$data->group_id}" : $target_value;
            // adding payload data
            $payload['title'] = $fields['title'];
            $payload['message'] = $fields['body'];
            $payload['payload'] = ["action" => "open_notification", "value" => $fields['id']];
            $payload['image'] = $fields['image'];
            $payload['priority'] = $fields['priority'];
            $payload['action_type'] = $fields['action_type'];
            $payload['action_value'] = $fields['action_value'];
            self::_send_push_notification($data->target_type, $target_value, $payload);

            Db::commit();
            return $result;
        } catch (\Throwable $th) {
            Db::rollBack();
            throw $th;
        }
    }

    static function _send_push_notification(String $target_type, ?String $target_value, array $data)
    {
        switch (strtoupper($target_type)) {
            case 'GUEST':
                $channel_name = "guest-channel";
                $event = "notification";
                $data = ["type" => "notification", ...$data];
                $socket_id = '';
                echo date('Y-m-d H:i:s') . " => Send Notification : $channel_name->$event \n";
                MyFunc::send_notif($channel_name, $event, $data, $socket_id);
                break;
            case 'USER':
                $channel_name = "presence-channel";
                $event = "notification";
                $data = ["type" => "notification", ...$data];
                $socket_id = '';
                echo date('Y-m-d H:i:s') . " => Send Notification : $channel_name->$event \n";
                MyFunc::send_notif($channel_name, $event, $data, $socket_id);
                break;
            case 'SPECIFIC_USER':
            case 'SPECIFIC_GUEST':
                $channel_name = $target_value;
                $event = "notification";
                $data = ["type" => "notification", ...$data];
                $socket_id = '';
                echo date('Y-m-d H:i:s') . " => Send Notification : $channel_name->$event \n";
                MyFunc::send_notif($channel_name, $event, $data, $socket_id);
                break;
            default:
                self::_send_push_notification('GUEST', null, $data);
                self::_send_push_notification('USER', null, $data);
                break;
        }
    }
}
