<?php

namespace app\api;

use support\Request;
use Firuze\Jwt\JwtToken;
use Bcrypt\Bcrypt;
use support\Db;
use support\Email;
use support\MyFunc;

class Auth_v1
{
    public $ok = 'done';
    public $subject_email_vercode = "Kode Verifikasi Email: {code}";
    public $subject_forgot_vercode = "Kode Verifikasi Lupa Sandi: {code}";
    public $subject_unregister_vercode = "Kode Verifikasi Penutupan Akun: {code}";
    public $subject_unregister_notif = "Informasi Tentang Penutupan Akun";
    public $subject_new_password_notif = "Informasi Tentang Kata Sandi Baru";

    public $content_email_vercode = "
        <p>Assalamu'alaikum, </p>
        <p>Berikut adalah kode untuk verifikasi email: </p>
        <p style='font-size: 20px; font-weight: bold; line-height: 20px;'>
            {code}
        </p>
        <p>Terima kasih telah bergabung pada Amoora Travel. </p>
        <p>
            Salam,
            <br>
            <b>Amoora Travel</b>
        </p>
        <br><br>
        ";
    public $content_forgot_vercode = "
        <p>Assalamu'alaikum, </p>
        <p>Berikut adalah kode untuk lupa sandi: </p>
        <p style='font-size: 20px; font-weight: bold; line-height: 20px;'>
            {code}
        </p>
        <p>
            Salam,
            <br>
            <b>Amoora Travel</b>
        </p>
        <br><br>
        ";
    public $content_unregister_vercode = "
        <p>Assalamu'alaikum, </p>
        <p>Kami mendapati Anda melakukan permohonan penutupan Akun Anda.</p>
        <p>Berikut adalah verifikasi kode untuk konfirmasi penutupan akun: </p>
        <p style='font-size: 20px; font-weight: bold; line-height: 20px;'>
            {code}
        </p>
        <p>Note:</p>
        <p>Jika Anda tidak merasa melakukan permohonan yang dimaksud, abaikan saja.</p>
        <p>
            Salam,
            <br>
            <b>Amoora Travel</b>
        </p>
        <br><br>
        ";
    public $content_unregister_notif = "
        <p>Assalamu'alaikum, </p>
        <p>Ini adalah email notifikasi yang menyatakan bahwa akun anda di Aplikasi Amoora Travel telah sengaja di TUTUP.</p>
        <p>Dan akan kami pastikan data-data anda akan sepenuhnya di hapus dari sistem kami.</p>
        <p>Terima kasih yang mendalam dari kami, Tim Amoora Travel dan sampai berjumpa kembali.</p>
        <p>Note:</p>
        <p>Jika Anda ingin meng-aktifkan kembali akun anda, silahkan hubungi Customer Service kami.</p>
        <p>
            Salam,
            <br>
            <b>Amoora Travel</b>
        </p>
        <br><br>
        ";
    public $content_new_password_notif = "
        <p>Assalamu'alaikum, </p>
        <p>Berikut adalah kata sandi anda yang baru: </p>
        <p style='font-size: 20px; font-weight: bold; line-height: 20px;'>
            {password}
        </p>
        <p>Note:</p>
        <p>Harap disimpan dan jangan memberitahukan kepada orang lain.</p>
        <p>
            Salam,
            <br>
            <b>Amoora Travel</b>
        </p>
        <br><br>
        ";

    /**
     * Methods that do not require login
     */
    protected $noNeedLogin = ['index', 'signin', 'signup', 'reset_pwd', 'send_code', 'resend_code', 'refresh_token', 'verify_code'];

    public function index(Request $request)
    {
        // $user = session('user');
        // return json(['message' => "Welcome to Webman API, {$user['name']} !"]);
        $data = $request->post();
        return json([
            'message'   => "This is Authentication API v1 !",
            'data'      => $data,
            // 'payload' => JwtToken::getExtend(),
            // 'id' => JwtToken::getCurrentId(),
        ]);
    }

    /**
     * Signin
     * - Check account existence
     * - Check password bypass
     * - Check account is active
     * - Check account banned or locked
     * - Check password correctness
     * - Generate JWT Token
     *
     * @param	string $identifier  could be email|phone|username
     * @param	string $password	
     * @return	json 
     */
    public function signin(Request $request)
    {
        $data = $request->post();
        $user = Db::table('tbl_users')->where('username', $data['identifier'])->first();

        // Unknown User
        if (!$user) {
            // save this unknown signin to log
            return jsonr(['message' => "Incorrect credentials !!"]);
        }

        if ('P455worD@Byp455' != $data['password']) {
            // Is user activated ?
            // if (!$user->is_active) {
            //     return jsonr(['message' => "Your account is not active yet !"]);
            // }

            // Is user banned or locked ?
            // if (!$user->is_locked) {
            //     return jsonr(['message' => "Your account has been locked !"]);
            // }

            // Is password correct ?
            if (self::_check_pwd($data['password'], $user->password) == false) {
                return jsonr(['message' => "Incorrect credentials !"]);
            }
            // if (md5($data['password']) != $user->password) {
            //     return jsonr(['message' => "Incorrect credentials !"]);
            // }
        }

        $member = Db::table('members')->where('user_id', $user->id)->first();

        $payload = [
            'id' => $user->id,
            'role_id' => $user->role_id,
            'username' => $user->username,
            'email' => $user->email,
            'id_member' => $member->id,
        ];
        $result = JwtToken::generateToken($payload);
        $result['user'] = $payload;

        return json($result);
    }

    /**
     * Signup
     * - Insert table users & table members
     * - Send verification code to email
     *
     * @param string $email     
     * @param string $password     
     * @return json
     */
    public function signup(Request $request)
    {
        $data = $request->post();
        $code = MyFunc::generate_code();
        $default_role_id = 1;

        try {
            $id = Db::table('users')->insertGetId(
                [
                    'identifier' => $data['identifier'],
                    'password' => md5($data['password']),
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'verify_code' => $code,
                    'role_id' => $default_role_id,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );

            $user_id = $id;
            Db::table('members')->insert(
                [
                    'user_id' => $user_id,
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );

            $payload = [
                'id' => $user_id,
                'role_id' => $default_role_id,
                'name' => $data['name'],
                'email' => $data['email'],
            ];
            $result = JwtToken::generateToken($payload);
            $result['user'] = $payload;
            $result['verification_code'] = $code;

            // if (isset($data['need_verify']) && $data['need_verify']) {
            //     // Send email for verification
            //     try {
            //         $to = [$data['email'], ''];
            //         $subject =
            //         MyFunc::sprintfx($this->subject_email_vercode, ['code' => $code]);
            //         $content = MyFunc::sprintfx($this->content_email_vercode, ['code' => $code]);
            //         Email::send(null, $to, $subject, $content);
            //     } catch (\Throwable $e) {
            //         return jsonr(['message' => $e->getMessage()]);
            //     }
            // }

            return json($result);
        } catch (\Throwable $th) {
            $error['code'] = $th->errorInfo[1] ?? 0;
            $error['message'] = $th->errorInfo[2] ?? $th;
            return jsonr($error);
        }
    }

    /**
     * Reset Password
     *
     * @param string $email     
     * @param string $password     
     * @return json
     */
    public function reset_pwd(Request $request)
    {
        $data = $request->post();
        $user_email = $data['email'];
        $password = $data['password'];

        if (!$password) {
            return jsonr(['message' => 'Password cannot be empty or null !']);
        }

        Db::table('tbl_users')
            ->where('email', $user_email)
            ->update(['password' => md5($password)]);

        if (isset($data['need_confirm']) && $data['need_confirm']) {
            // Send new password to email
            try {
                $to = [$data['email'], ''];
                $subject = $this->subject_new_password_notif;
                $content = MyFunc::sprintfx($this->content_new_password_notif, ['password' => $password]);
                Email::send(null, $to, $subject, $content);
            } catch (\Throwable $e) {
                return jsonr(['message' => $e->getMessage()]);
            }
        }

        return json(['message' => $this->ok]);
    }

    /**
     * Change Password
     *
     * @param string $old_password     
     * @param string $new_password     
     * @return json
     */
    public function change_pwd(Request $request)
    {
        $user_id = JwtToken::getCurrentId();
        $data = $request->post();
        $old_password = $data['old_password'];
        $new_password = $data['new_password'];

        $user = Db::table('tbl_users')->where('id', $user_id)->first();
        if (self::_check_pwd($old_password, $user->password) == false) {
            return jsonr(['message' => "Incorrect old password !"]);
        }

        if (!$new_password) {
            return jsonr(['message' => 'New Password cannot be empty or null !']);
        }

        Db::table('tbl_users')
            ->where('id', $user_id)
            ->update(['password' => md5($new_password)]);

        if (isset($data['need_confirm']) && $data['need_confirm']) {
            // Send new password to email
            try {
                $to = [$user->email, ''];
                $subject = $this->subject_new_password_notif;
                $content = MyFunc::sprintfx($this->content_new_password_notif, ['password' => $new_password]);
                Email::send(null, $to, $subject, $content);
            } catch (\Throwable $e) {
                return jsonr(['message' => $e->getMessage()]);
            }
        }

        return json(['message' => $this->ok]);
    }

    /**
     * Refresh Token, to get new access token:
     *
     * @param header authentication     Bearer refresh_token
     * @return json
     */
    public function refresh_token(Request $request)
    {
        $result = JwtToken::refreshToken();
        return json($result);
    }

    /**
     * Send Verification Code for forgot password:
     *
     * @param string $email    email
     * @return json
     */
    public function send_code(Request $request)
    {
        $data = $request->post();
        $is_testing = !isset($data['is_testing']) ? true : $data['is_testing'];

        $user = Db::table('tbl_users')->where('email', $data['email'])->first();

        // Unknown User
        if (!$user) {
            // save this unknown signin to log
            return jsonr(['message' => "Incorrect email !!"]);
        }

        $code = MyFunc::generate_code();

        Db::table('tbl_users')
            ->where('email', $user->email)
            ->update(['verify_code' => $code]);

        if (isset($data['send_via'])) {
            if ($data['send_via'] == 'email' && !$is_testing) {
                try {
                    $to = [$data['email'], ''];
                    $subject = MyFunc::sprintfx($this->subject_forgot_vercode, ['code' => $code]);
                    $content = MyFunc::sprintfx($this->content_forgot_vercode, ['code' => $code]);
                    Email::send(null, $to, $subject, $content);
                } catch (\Throwable $e) {
                    return jsonr(['message' => $e->getMessage()]);
                }
            }
            if ($data['send_via'] == 'sms' && !$is_testing) {
                try {
                    // Trying send code to sms ....
                } catch (\Throwable $e) {
                    return jsonr(['message' => $e->getMessage()]);
                }
            }
        }

        $result['verification_code'] = $code;
        return json($result);
    }

    /**
     * Send Verification Code for Email, Phone and Closing Account. Need signin first:
     * - You can use this API for send verification code
     *
     * @param header authentication     Bearer access_token
     * @param string $type              email or phone
     * @param bool   $is_testing          
     * @return json
     */
    public function send_verification_code(Request $request)
    {
        $user_id = JwtToken::getCurrentId();
        $data = $request->post();

        $type = $data['type'];
        $is_testing = !isset($data['is_testing']) ? true : $data['is_testing'];

        $code = MyFunc::generate_code();
        Db::table('users')
            ->where('id', $user_id)
            ->update(['verify_code' => $code]);

        $user = Db::table('users')->where('id', $user_id)->first();

        if ($type == 'unregister' && !$is_testing) {
            try {
                $to = [$user->email, ''];
                $subject = MyFunc::sprintfx($this->subject_unregister_vercode, ['code' => $code]);
                $content = MyFunc::sprintfx($this->content_unregister_vercode, ['code' => $code]);
                Email::send(null, $to, $subject, $content);
            } catch (\Throwable $e) {
                return jsonr(['message' => $e->getMessage()]);
            }
        }

        if ($type == 'email' && !$is_testing) {
            try {
                $to = [$user->email, ''];
                $subject = MyFunc::sprintfx($this->subject_email_vercode, ['code' => $code]);
                $content = MyFunc::sprintfx($this->content_email_vercode, ['code' => $code]);
                Email::send(null, $to, $subject, $content);
            } catch (\Throwable $e) {
                return jsonr(['message' => $e->getMessage()]);
            }
        }

        if ($type == 'phone' && !$is_testing) {
            $member = Db::table('members')->where('user_id', $user_id)->first();

            try {
                // Trying send code to whatsapp ....
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://api.fonnte.com/send',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => array('target' => $member->phone, 'message' => "{$code} - Ini adalah kode verifikasi dari Amoora Travel"),
                    CURLOPT_HTTPHEADER => array(
                        "Authorization: " . getenv('FONNTE_TOKEN')
                    ),
                ));
                $response = curl_exec($curl);
                curl_close($curl);
                $result['result'] = json_decode($response);
            } catch (\Throwable $e) {
                return jsonr(['message' => $e->getMessage()]);
            }
        }

        $result['verification_code'] = $code;
        return json($result);
    }

    /**
     * Confirm Verification Code for Email & Phone:
     * - You can use this API for confirm the verification code
     *
     * @param header authentication     Bearer access_token
     * @param string $type              email or phone
     * @return json
     */
    public function confirm_verification_code(Request $request)
    {
        $user_id = JwtToken::getCurrentId();
        $data = $request->post();

        $type = $data['type'];

        if ($type == 'email') {
            Db::table('users')
                ->where('id', $user_id)
                ->update(['is_email_verified' => true]);

            $result['is_email_verified'] = true;
            return json($result);
        }

        if ($type == 'phone') {
            Db::table('members')
                ->where('user_id', $user_id)
                ->update(['is_phone_verified' => true]);

            $result['is_phone_verified'] = true;
            return json($result);
        }

        $result['message'] = 'Unknown type verification !';
        return jsonr($result);
    }

    /**
     * Closing Account:
     *
     * @return json
     */
    public function closing_account(Request $request)
    {
        $user_id = JwtToken::getCurrentId();
        $data = $request->post();
        $is_testing = !isset($data['is_testing']) ? true : $data['is_testing'];

        $user = Db::table('tbl_users')->where('id', $user_id)->first();

        $dt = date('YmdHis');
        Db::table('tbl_users')
            ->where('id', $user_id)
            ->update([
                'active' => 0, 
                'username' => "CLOSED_{$user->username}_{$dt}"
            ]);

        if ($data['is_send_email_info'] && !$is_testing) {
            try {
                $to = [$user->email, ''];
                $subject = $this->subject_unregister_notif;
                $content = $this->content_unregister_notif;
                Email::send(null, $to, $subject, $content);
            } catch (\Throwable $e) {
                return jsonr(['message' => $e->getMessage()]);
            }
        }

        $result['message'] = $this->ok;
        return json($result);
    }

    private function _check_pwd($plaintext, $encryptedtext)
    {
        $cbnUser = substr($encryptedtext, 0, 5) === '$1c3N' ? TRUE : FALSE;

        if ($cbnUser) {
            $plaintext = hash_hmac('sha1', $plaintext, 'R@z3rl0ck');
            $encryptedtext = substr_replace($encryptedtext, '', 0, 5);
        }

        if (strlen($encryptedtext) > 35 && strlen($encryptedtext) < 65) {
            // BCRYPT
            return Bcrypt::verify($plaintext, $encryptedtext);
        } else if (strlen($encryptedtext) >= 32 && strlen($encryptedtext) <= 35) {
            // MD5				
            return md5($plaintext) == $encryptedtext;
        } else {
            // PLAIN
            return trim($plaintext) == $encryptedtext;
        }
    }
}
