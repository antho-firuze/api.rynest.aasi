<?php

namespace support;

use Exception;
use support\Request;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;

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
        $protocol = $config['protocol'] ?? $request->header('x-forwarded-proto');
        $protocol = $protocol ?? 'http';
        $folder = $config['folder'] ?? '';
        $userfile = $config['userfile'] ?? 'userfile';
        $allowed_types = $config['allowed_types'] ?? ['jpg', 'png', 'bmp', 'gif'];
        $max_size = $config['max_size'] ?? 1000;     // in KB

        $file = $request->file($userfile);
        $file_ext = $file->getUploadExtension();
        $file_size = $file->getSize() / 1000;  // convert to KB, actual in Bytes
        if ($config['file_name'] == null) {
            $file_name = $file->getUploadName();
        } else {
            $file_name = $config['file_name'];
            $file_name = "{$file_name}.{$file_ext}";
        }

        if (!in_array($file_ext, $allowed_types)) {
            $allowedtypes = implode(",", $allowed_types);
            throw new Exception(message: "File extension not allowed, except: {$allowedtypes}.");
        }

        if ($file_size > $max_size) {
            throw new Exception(message: "File size not allowed, max size: {$max_size}KB");
        }

        if ($file && $file->isValid()) {
            $relative_path = "{$folder}{$file_name}.{$file_ext}";

            // Move file to destination folder
            $upload_path = public_path(path: "{$folder}{$file_name}.{$file_ext}");
            $file->move($upload_path);

            $host = $request->host();

            $result = "{$protocol}://{$host}/{$relative_path}";
            return $result;
        }

        throw new Exception(message: 'File not found !');
    }

    static function upload_s3(Request $request, $config = [])
    {
        $folder = $config['folder'] ?? '';
        $userfile = $config['userfile'] ?? 'userfile';
        $allowed_types = $config['allowed_types'] ?? ['jpg', 'png', 'bmp', 'gif'];
        $max_size = $config['max_size'] ?? 1000;     // in KB

        $file = $request->file($userfile);
        $file_ext = $file->getUploadExtension();
        $file_size = $file->getSize() / 1000;  // convert to KB, actual in Bytes
        if ($config['file_name'] == null) {
            $file_name = $file->getUploadName();
        } else {
            $file_name = $config['file_name'];
            $file_name = "{$file_name}.{$file_ext}";
        }

        if (!in_array($file_ext, $allowed_types)) {
            $allowedtypes = implode(",", $allowed_types);
            throw new Exception(message: "File extension not allowed, except: {$allowedtypes}.");
        }

        if ($file_size > $max_size) {
            throw new Exception(message: "File size not allowed, max size: {$max_size}KB");
        }

        $aws_key = getenv('AWS_ACCESS_KEY_ID');
        $aws_secret = getenv('AWS_SECRET_ACCESS_KEY');
        $region = getenv('AWS_DEFAULT_REGION');
        $bucket = getenv('AWS_BUCKET');

        $s3 = new S3Client([
            'region' => $region,
            'credentials' => ['key' => $aws_key, 'secret' => $aws_secret]
        ]);

        try {
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
}
