<?php

namespace app\api;

use support\Request;
use Firuze\Jwt\JwtToken;
use support\Db;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Rules\Lowercase;

class Admin_v1
{
    protected $noNeedLogin = ['index', 'user', 'member', 'update_schedule', 'clear_exam'];

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

    public function user(Request $request)
    {
        $data = (object) $request->post();

        try {
            $user = Db::table('tbl_users')
                ->where('username', $data->identifier)
                ->orWhereRaw("LOWER(email) = ?", [strtolower($data->identifier)])
                ->first();
            if ($user == null) return jsonr(["message" => "User not found"]);

            $member = Db::table('members')->where('user_id', $user->id ?? null)->first();
            $certificate = Db::table('exam_results_sertifikat')
                ->selectRaw('no_sertifikat.id, no_sertifikat.no_sertifikat, exam_results_sertifikat.id_member, exam_results_sertifikat.realese_date, exam_results_sertifikat.expired_date')
                ->join('no_sertifikat', 'exam_results_sertifikat.id_no_sertfikat', '=', 'no_sertifikat.id')
                ->where('exam_results_sertifikat.id_member', $member->id)
                ->first();
            $company = Db::table('mst_anggota')->where('id', $member->company_id ?? null)->first();
            $schedule = Db::table('schedule_participants')
                ->selectRaw('schedules.id, schedule_request_id, schedule_participants.id_member, schedule_participants.member_id, company_id, location_id, category_id, name, duration, notes, open_registration, close_registration')
                ->join('schedule_requests', 'schedule_participants.schedule_request_id', '=', 'schedule_requests.id')
                ->join('schedules', 'schedules.id', '=', 'schedule_requests.schedule_id')
                ->where('schedule_participants.id_member', $member->id ?? null)
                ->where('schedule_requests.company_id', $member->company_id ?? null)
                ->orderBy('schedule_requests.id', 'desc')
                ->first();
            $photos = Db::table('participant_photo')
                ->where('id_member', $member->id ?? null)
                ->where('schedule_request_id', $schedule->schedule_request_id ?? null)
                ->get();
            $category = Db::table('categories')
                ->selectRaw('categories.id, categories.name, categories.description, categories.duration, categories.passed_grade, module_id, modules.name as module_name, questions, easy, medium, hard, category_modules.status')
                ->join('category_modules', 'categories.id', '=', 'category_modules.category_id')
                ->join('modules', 'category_modules.module_id', '=', 'modules.id')
                ->where('categories.id', $schedule->category_id ?? null)->first();
            $location = Db::table('locations')->where('id', $schedule->location_id ?? null)->first();
            $exam_result = Db::table('exam_results')
                ->selectRaw('id, id_member, member_id, category_id, schedule_request_id, question_ids, answer_keys, score, status, `note`, click_score, questions, passed_grade, duration, start_at, finish_at, answer_keys, the_keys, restart, device, ip_address, location')
                ->where('schedule_request_id', $schedule->schedule_request_id ?? null)
                ->where('id_member', $member->id ?? null)
                ->first();
            $exam_session = Db::table('exam_session')
                ->selectRaw('restart, device_id, device_name, ip_address, location, restart_at')
                ->where('schedule_request_id', $schedule->schedule_request_id ?? null)
                ->where('id_member', $member->id ?? null)
                ->get();

            $result = (object) [];
            $result->tbl_users = $user;
            $result->members = $member;
            $result->exam_results_sertifikat = $certificate;
            $result->mst_anggota = $company;
            $result->schedule_participants = $schedule;
            $result->participant_photo = $photos;
            $result->categories = $category;
            $result->locations = $location;
            $result->exam_results = $exam_result;
            $result->exam_session = $exam_session;
            return json($result);
        } catch (\Throwable $th) {
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }
    }

    public function member(Request $request)
    {
        $data = (object) $request->post();

        try {
            $member = Db::table('members')
                ->orWhereLike('member_id', $data->identifier)
                ->orWhereLike('identity_card', $data->identifier)
                ->first();
            if ($member == null) return jsonr(["message" => "Member not found"]);

            $user = Db::table('tbl_users')->where('id', $member->user_id ?? null)->first();
            $certificate = Db::table('exam_results_sertifikat')
                ->selectRaw('no_sertifikat.id, no_sertifikat.no_sertifikat, exam_results_sertifikat.id_member, exam_results_sertifikat.realese_date, exam_results_sertifikat.expired_date')
                ->join('no_sertifikat', 'exam_results_sertifikat.id_no_sertfikat', '=', 'no_sertifikat.id')
                ->where('exam_results_sertifikat.id_member', $member->id)
                ->first();
            $company = Db::table('mst_anggota')->where('id', $member->company_id ?? null)->first();
            $schedule = Db::table('schedule_participants')
                ->selectRaw('schedules.id, schedule_request_id, schedule_participants.id_member, schedule_participants.member_id, company_id, location_id, category_id, name, duration, notes, open_registration, close_registration')
                ->join('schedule_requests', 'schedule_participants.schedule_request_id', '=', 'schedule_requests.id')
                ->join('schedules', 'schedules.id', '=', 'schedule_requests.schedule_id')
                ->where('schedule_participants.id_member', $member->id ?? null)
                ->where('schedule_requests.company_id', $member->company_id ?? null)
                ->orderBy('schedule_requests.id', 'desc')
                ->first();
            $photos = Db::table('participant_photo')
                ->where('id_member', $member->id ?? null)
                ->where('schedule_request_id', $schedule->schedule_request_id ?? null)
                ->get();
            $category = Db::table('categories')
                ->selectRaw('categories.id, categories.name, categories.description, categories.duration, categories.passed_grade, module_id, modules.name as module_name, questions, easy, medium, hard, status')
                ->join('category_modules', 'categories.id', '=', 'category_modules.category_id')
                ->join('modules', 'category_modules.module_id', '=', 'modules.id')
                ->where('categories.id', $schedule->category_id ?? null)->first();
            $location = Db::table('locations')->where('id', $schedule->location_id ?? null)->first();
            $exam_result = Db::table('exam_results')
                ->selectRaw('id, id_member, member_id, category_id, schedule_request_id, question_ids, answer_keys, score, status, `note`, click_score, questions, passed_grade, duration, start_at, finish_at, answer_keys, the_keys, restart, device, ip_address, location')
                ->where('schedule_request_id', $schedule->schedule_request_id ?? null)
                ->where('id_member', $member->id ?? null)
                ->first();
            $exam_session = Db::table('exam_session')
                ->selectRaw('restart, device_id, device_name, ip_address, location, restart_at')
                ->where('schedule_request_id', $schedule->schedule_request_id ?? null)
                ->where('id_member', $member->id ?? null)
                ->get();

            $result = (object) [];
            $result->members = $member;
            $result->tbl_users = $user;
            $result->exam_results_sertifikat = $certificate;
            $result->mst_anggota = $company;
            $result->schedule_participants = $schedule;
            $result->participant_photo = $photos;
            $result->categories = $category;
            $result->locations = $location;
            $result->exam_results = $exam_result;
            $result->exam_session = $exam_session;
            return json($result);
        } catch (\Throwable $th) {
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }
    }

    public function update_schedule(Request $request)
    {
        $data = (object) $request->post();

        Db::beginTransaction();

        $count = Db::table('schedules')
            ->where('id', $data->schedule_id)
            ->update([
                'open_registration' => $data->open_registration,
                'close_registration' => $data->close_registration,
            ]);

        // $count = Db::table('app_version')
        //     ->where('name', 'APP_AASI')
        //     ->update([
        //         'version' => '2.0.6'
        //     ]);

        if ($data->commit) {
            Db::commit();
        } else {
            Db::rollBack();
        }

        return json(['rows_affected' => $count, 'save_changes' => $data->commit]);
    }

    public function clear_exam(Request $request)
    {
        $data = (object) $request->post();

        Db::beginTransaction();

        $count = Db::table('participant_photo')
            ->where('id_member', $data->id_member)
            ->where('schedule_request_id', $data->schedule_request_id)
            ->delete();

        $count2 = Db::table('exam_results')
            ->where('id_member', $data->id_member)
            ->where('schedule_request_id', $data->schedule_request_id)
            ->delete();

        $count3 = Db::table('exam_logs')
            ->where('id_member', $data->id_member)
            ->where('schedule_request_id', $data->schedule_request_id)
            ->delete();

        if ($data->commit) {
            Db::commit();
        } else {
            Db::rollBack();
        }

        return json([
            'rows_affected(participant_photo)' => $count,
            'rows_affected(exam_results)' => $count2,
            'rows_affected(exam_logs)' => $count3,
            'save_changes' => $data->commit,
        ]);
    }

    public function update_exam_result_compatibility(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('schedule_request_id', v::intType()->notEmpty())
                ->attribute('id_member', v::intType()->notEmpty());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages($this->validatorDesc);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $id_member = $data->id_member;

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            $exam_result = Db::table('exam_results')
                ->selectRaw('id, category_id, score, status, note, click_score, cek_score, questions, passed_grade, duration, start_at, finish_at, question_ids, answer_keys, the_keys, restart, device, ip_address, location')
                ->where('schedule_request_id', $data->schedule_request_id ?? null)
                ->where('id_member', $id_member)
                ->first();

            if ($exam_result == null) {
                return json(null);
            }

            // $exam_session = Db::table('exam_session')
            //     ->selectRaw('restart, device_id, device_name, ip_address, location, restart_at')
            //     ->where('schedule_request_id', $data->schedule_request_id ?? null)
            //     ->where('id_member', $id_member)
            //     ->get();

            $category = Db::table('categories')
                ->selectRaw('categories.id, categories.name, categories.description, categories.duration, categories.passed_grade, module_id, modules.name as module_name, questions, easy, medium, hard, status')
                ->join('category_modules', 'categories.id', '=', 'category_modules.category_id')
                ->join('modules', 'category_modules.module_id', '=', 'modules.id')
                ->where('categories.id', $exam_result->category_id ?? null)->first();

            // Result Status:
            // - TEMPORARY
            // - COMPLETED
            // $status = 'TEMPORARY';
            // if (empty($exam_result->status)) {
            //     $status = 'TEMPORARY';
            // } else {
            //     $status = 'COMPLETED';
            // }

            // Answered question count
            $arrAnswerKeys = explode(',', $exam_result->answer_keys);
            $arrCount = array_count_values($arrAnswerKeys);
            $countNotAnswered = $arrCount['X'] ?? 0;
            $exam_result->answered_count = $exam_result->questions - $countNotAnswered;

            // This is for compatibily with old version
            // - The old version is not record field the_keys, this cause error
            // ===================================================================
            if ($exam_result->the_keys == null) {
                // GET the question id from field question_ids
                $arrIds = [];
                $arrQuestionIds = [];
                $items = explode(',', $exam_result->question_ids);
                foreach ($items as $item) {
                    $Id = substr(trim($item), 0, 4);
                    $arrIds[] = $Id;
                    $arrQuestionIds[intval($Id)] = "X";
                }

                // UPDATE array of question with the keys
                $question = Db::table('questions')->selectRaw('id, answer_key')
                    ->where('module_id', $category->module_id)
                    ->get()->toArray();
                foreach ($question as $key => $value) {
                    if (in_array($value->id, $arrIds)) {
                        $arrQuestionIds[$value->id] = $value->answer_key;
                    }
                }
                $the_keys = implode(",", $arrQuestionIds);
                $exam_result->the_keys = $the_keys;
            }
            // ===================================================================

            // Right-answered question count
            $arrTheKeys = explode(',', $exam_result->the_keys);
            $countRightAnswer = 0;
            $countWrongAnswer = 0;
            foreach ($arrAnswerKeys as $key => $val) {
                if ($val != 'X') {
                    $countRightAnswer += ($val == $arrTheKeys[$key]) ? 1 : 0;
                    $countWrongAnswer += ($val != $arrTheKeys[$key]) ? 1 : 0;
                }
            }
            $exam_result->r_answered_count = $countRightAnswer;
            $exam_result->w_answered_count = $countWrongAnswer;

            // Calculate score
            $score = $countRightAnswer * (100 / $exam_result->questions);
            $exam_result->score = "{$score}/{$exam_result->questions}";
            $desc1 = $score >= $category->passed_grade ? 'LULUS' : 'GAGAL';

            // // Update field check Score
            // $count = Db::table('exam_results')
            //     ->where('id', $exam_result->id)
            //     ->update([
            //         'score' => $exam_result->score,
            //     ]);

            // Exam real time duration
            $real_duration = '';
            if ($exam_result->start_at && $exam_result->finish_at) {
                $startAt = date_create($exam_result->start_at);
                $finishAt = date_create($exam_result->finish_at);
                $diff = date_diff($finishAt, $startAt);
                $i = ($diff->h * 60) + ($diff->i);
                $s = $diff->s;
                $real_duration = "$i menit" . ($s == 0 ? "" : " $s detik");
            }

            // Check Score
            $check_score = $exam_result->cek_score ?? 0;

            // Exam Photos
            $photos = Db::table('participant_photo')
                ->where('schedule_request_id', $data->schedule_request_id ?? null)
                ->where('id_member', $id_member)
                ->get();

            $photo_start = false;
            $photo_finish = false;
            foreach ($photos as $key => $value) {
                if ($value->type == 'exam_start') {
                    $photo_start = true;
                } else if ($value->type == 'exam_finish') {
                    $photo_finish = true;
                }
            }

            // Db::commit();
        } catch (\Throwable $th) {
            // Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

        return json($exam_result);
    }
}
