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
                    'ACL' => 'public-read',
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
}
