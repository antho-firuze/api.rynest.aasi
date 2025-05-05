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
            $member = Db::table('members')->where('user_id', $user_id)->first();
            $company = Db::table('companies')->where('id', $member->company_id ?? null)->first();

            Db::commit();
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

        // LAST STAGE (Output Process)
        // ===========================
        $result = (object) [];
        $result = $member;
        $result->company = $company;
        return json($result);
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
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

        // LAST STAGE (Output Process)
        // ===========================
        $result = $certificate;
        return json($result);
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
            $errAttr = $e->getMessages([
                'attribute' => 'Params [{{name}}] is required',
                'notEmpty' => '[{{name}}] must not empty',
            ]);
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
        try {
            $url = MyFunc::upload_s3($request, [
                'file_name' => "{$type}-{$id_member}",
                'folder'    => 'images/profile/',
            ]);

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
            return json($result);
        } catch (\Throwable $th) {
            $result = ['message' => $th->getMessage()];
            return jsonr($result);
        }
    }
}
