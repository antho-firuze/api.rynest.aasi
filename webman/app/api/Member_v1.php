<?php

namespace app\api;

use support\Request;
use support\Db;
use Firuze\Jwt\JwtToken;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;
use support\MyFunc;

class Member_v1
{
    protected $noNeedLogin = ['index'];

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
        return json(['message' => "Rynest => Admin API v1"]);
    }

    public function profile(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $user_id = JwtToken::getCurrentId();

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            $user = Db::table('tbl_users')->where('id', $user_id)->first();
            $member = Db::table('members')->where('user_id', $user_id)->first();
            $company = Db::table('companies')->where('id', $member->company_id ?? null)->first();

            Db::commit();

            // LAST STAGE (Output Process)
            // ===========================
            $result = (object) [];
            $result = $member;
            $result->email = $user->email;
            $result->company = $company;
            return json($result);
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }
    }

    public function certificate(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $user_id = JwtToken::getCurrentId();

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            $member = Db::table('members')->where('user_id', $user_id)->first();

            $certificate = Db::table('exam_results_sertifikat')
                ->selectRaw('no_sertifikat.id, no_sertifikat.no_sertifikat, exam_results_sertifikat.id_member, exam_results_sertifikat.realese_date, exam_results_sertifikat.expired_date')
                ->join('no_sertifikat', 'exam_results_sertifikat.id_no_sertfikat', '=', 'no_sertifikat.id')
                ->where('exam_results_sertifikat.id_member', $member->id)
                ->first();

            Db::commit();

            // LAST STAGE (Output Process)
            // ===========================
            $result = $certificate;
            return json($result);
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }
    }

    public function upload_photo(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('type', v::notEmpty());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $id_member = JwtToken::getExtendVal('id_member');

        $uploadType = ['idcard', 'selfie'];
        if (!in_array($data->type, $uploadType)) {
            $uploadTypeStr = implode('|', $uploadType);
            return jsonr(['message' => "[type] not allowed, except: [{$uploadTypeStr}]"]);
        }
        $type = $data->type;

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            $config['userfile'] = "userfile";
            $config['file_name'] = "{$type}-{$id_member}";
            $config['folder'] = "images/profile/";
            $config['allowed_types'] = ['jpg', 'png', 'bmp', 'gif'];
            $config['max_size'] = 1000; // in KB, default 1000KB = 1MB
            $url = MyFunc::upload_s3($request, $config);

            if ($type == 'idcard') {
                $count = Db::table('members')
                    ->where('id', $id_member)
                    ->update([
                        'photo_idcard' => $url,
                    ]);
            } else {
                $count = Db::table('members')
                    ->where('id', $id_member)
                    ->update([
                        'photo' => $url,
                    ]);
            }

            $result = ['url' => $url];

            Db::commit();

            // LAST STAGE (Output Process)
            // ===========================
            return json($result);
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }
    }
}
