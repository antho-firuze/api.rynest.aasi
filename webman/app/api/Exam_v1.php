<?php

namespace app\api;

use ArrayIterator;
use support\Request;
use support\DB;
use Firuze\Jwt\JwtToken;
use support\MyFunc;

class Exam_v1
{
    protected $noNeedLogin = ['index'];

    public function index(Request $request)
    {
        return json(['message' => "Rynest => Admin API v1"]);
    }

    public function schedule(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $user_id = JwtToken::getCurrentId();

        // MIDDLE STAGE (Main Process)
        // ===========================
        $member = Db::table('members')
            ->where('user_id', $user_id)
            ->first();

        $schedule = Db::table('schedule_participants')
            ->selectRaw('schedules.id, schedule_request_id, schedule_participants.id_member, schedule_participants.member_id, company_id, location_id, category_id, name, duration, notes, open_registration, close_registration')
            ->join('schedule_requests', 'schedule_participants.schedule_request_id', '=', 'schedule_requests.id')
            ->join('schedules', 'schedules.id', '=', 'schedule_requests.schedule_id')
            ->where('schedule_participants.id_member', $member->id ?? null)
            ->where('schedule_requests.company_id', $member->company_id ?? null)
            ->orderBy('schedule_requests.id', 'desc')
            ->first();

        // LAST STAGE (Output Process)
        // ===========================
        $result = $schedule;
        return json($result);
    }

    public function result(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        $id_member = JwtToken::getExtendVal('id_member');

        // MIDDLE STAGE (Main Process)
        // ===========================
        $exam_result = Db::table('exam_results')
            ->selectRaw('id, score, status, click_score, questions, passed_grade, duration, start_at, finish_at, answer_keys, the_keys')
            ->where('schedule_request_id', $data->schedule_request_id ?? null)
            ->where('id_member', $id_member)
            ->first();

        if ($exam_result == null) {
            return json(null);
        }

        // Answered question count
        $arrAnswerKeys = explode(',', $exam_result->answer_keys);
        $arrCount = array_count_values($arrAnswerKeys);
        $exam_result->answered_count = $exam_result->questions - $arrCount['X'];

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

        // LAST STAGE (Output Process)
        // ===========================
        // Clearing the output
        $exam_result = (array) $exam_result;
        unset($exam_result['answer_keys']);
        unset($exam_result['the_keys']);

        $result = $exam_result;
        return json($result);
    }

    public function start(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        $user_id = JwtToken::getCurrentId();
        $id_member = JwtToken::getExtendVal('id_member');

        // MIDDLE STAGE (Main Process)
        // ===========================
        $exam_result = Db::table('exam_results')
            ->selectRaw('id, id_member, schedule_request_id, category_id, question_ids, answer_keys, score, status, click_score, questions, passed_grade, duration, start_at, finish_at, ip_address, device')
            ->where('schedule_request_id', $data->schedule_request_id)
            ->where('id_member', $id_member)
            ->first();

        // $exam_result = null;
        if ($exam_result == null) {
            $category = Db::table('categories')
                ->selectRaw('categories.id, categories.name, categories.description, categories.duration, categories.passed_grade, module_id, modules.name as module_name, questions, easy, medium, hard, status')
                ->join('category_modules', 'categories.id', '=', 'category_modules.category_id')
                ->join('modules', 'category_modules.module_id', '=', 'modules.id')
                ->where('categories.id', $data->category_id)->first();

            // Questions
            $question = Db::table('questions')->selectRaw('id, answer_key')->where('module_id', $category->module_id)->get()->toArray();
            shuffle($question);

            $question_ids = [];
            $answer_key = [];
            $answered_default = [];
            foreach ($question as $key => $value) {
                $value = (object) $value;
                if ($key < $category->questions) {
                    $question_ids[$key] = $value->id . str_shuffle('ABCD');
                    $answer_key[$key] = $value->answer_key;
                    $answered_default[$key] = 'X';
                }
            }

            $exam_result = [
                'id_member'           => $id_member,
                'schedule_request_id' => $data->schedule_request_id,
                'category_id'         => $data->category_id,
                'score'               => '0/0',
                'status'              => '',
                'click_score'         => 2,
                'question_ids'        => implode(",", $question_ids),
                'answer_keys'         => implode(",", $answered_default),
                'the_keys'            => implode(",", $answer_key),
                'questions'           => $category->questions,
                'passed_grade'        => $category->passed_grade,
                'duration'            => $category->duration,
                'start_at'            => $data->start_at ?? date('Y-m-d H:i:s'),
                'finish_at'           => null,
                'device'              => $data->device,
                'ip_address'          => $data->ip_address,
                'lat'                 => $data->lat,
                'lng'                 => $data->lng,
            ];
            // $id = Db::table('exam_results')->insertGetId($exam_result);
            $exam_result['id'] = $id ?? null;
        } else {
            // SECURITY CHECK: If difference IP is detected
            if ($exam_result->ip_address != $data->ip_address) {
                // TODO: Send notification to creator/initiator
            }

            // SECURITY CHECK: If difference Device is detected
            if ($exam_result->device != $data->device) {
                // TODO: Send notification to creator/initiator
            }
        }

        // LAST STAGE (Output Process)
        // ===========================
        // Clearing the output
        $exam_result = (array) $exam_result;
        unset($exam_result['id_member']);
        unset($exam_result['schedule_request_id']);
        unset($exam_result['category_id']);
        unset($exam_result['device']);
        unset($exam_result['ip_address']);
        unset($exam_result['lat']);
        unset($exam_result['lng']);

        $result = $exam_result;
        return json($result);
    }

    public function answer(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        $user_id = JwtToken::getCurrentId();
        $id_member = JwtToken::getExtendVal('id_member');

        // MIDDLE STAGE (Main Process)
        // ===========================
        $exam_result = Db::table('exam_results')
            ->selectRaw('id, id_member, schedule_request_id, category_id, question_ids, answer_keys, score, status, click_score, questions, passed_grade, duration, start_at, finish_at, the_keys')
            ->where('schedule_request_id', $data->schedule_request_id)
            ->where('id_member', $id_member)
            ->first();

        if ($exam_result == null) {
            return jsonr(['message' => "Incorrect examination !!"]);
        }

        if ($exam_result->status == 'completed') {
            return jsonr(['message' => "Examination has been finished !!"]);
        }

        // REPLACE ANSWER_KEYS
        $arrQuestions = explode(',', $exam_result->question_ids);
        $arrAnswerKeys = explode(',', $exam_result->answer_keys);
        $index = array_keys($arrQuestions, $data->question_id);
        $arrAnswerKeys[$index[0]] = $data->answered_key ?? 'X';

        // Db::beginTransaction();
        $count = Db::table('exam_results')
            ->where('id', $exam_result->id)
            ->update([
                'answer_keys' => implode(',', $arrAnswerKeys),
            ]);

        // Db::rollBack();

        // LAST STAGE (Output Process)
        // ===========================
        $result = ['message' => 'done'];
        return json($result);
    }

    public function check_score(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        $id_member = JwtToken::getExtendVal('id_member');

        // MIDDLE STAGE (Main Process)
        // ===========================
        $exam_result = Db::table('exam_results')
            ->selectRaw('id, score, status, click_score, questions, passed_grade, duration, start_at, finish_at, answer_keys, the_keys')
            ->where('schedule_request_id', $data->schedule_request_id ?? null)
            ->where('id_member', $id_member)
            ->first();

        if ($exam_result == null) {
            return json(null);
        }

        // Answered question count
        $arrAnswerKeys = explode(',', $exam_result->answer_keys);
        $arrCount = array_count_values($arrAnswerKeys);
        $exam_result->answered_count = $exam_result->questions - $arrCount['X'];

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

        // Update field check Score
        $exam_result->click_score = $exam_result->click_score - 1;
        $count = Db::table('exam_results')
            ->where('id', $exam_result->id)
            ->update([
                'click_score' => $exam_result->click_score,
            ]);

        // LAST STAGE (Output Process)
        // ===========================
        // Clearing the output
        $exam_result = (array) $exam_result;
        unset($exam_result['answer_keys']);
        unset($exam_result['the_keys']);

        $result = $exam_result;
        return json($result);
    }

    public function finish(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        $user_id = JwtToken::getCurrentId();
        $id_member = JwtToken::getExtendVal('id_member');

        // MIDDLE STAGE (Main Process)
        // ===========================
        $exam_result = Db::table('exam_results')
            ->selectRaw('id, id_member, schedule_request_id, category_id, question_ids, answer_keys, score, status, click_score, questions, passed_grade, duration, start_at, finish_at, the_keys')
            ->where('schedule_request_id', $data->schedule_request_id)
            ->where('id_member', $id_member)
            ->first();

        if ($exam_result == null) {
            return jsonr(['message' => "Incorrect examination !!"]);
        }

        if ($exam_result->status == 'completed') {
            return jsonr(['message' => "Examination has been finished !!"]);
        }

        // Db::beginTransaction();
        $count = Db::table('exam_results')
            ->where('id', $exam_result->id)
            ->update([
                'status' => 'completed',
            ]);
        // Db::rollBack();

        // LAST STAGE (Output Process)
        // ===========================
        $result = ['message' => 'done'];
        return json($result);
    }

    public function question(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        $user_id = JwtToken::getCurrentId();
        $id_member = JwtToken::getExtendVal('id_member');

        // MIDDLE STAGE (Main Process)
        // ===========================
        $question = Db::table('questions')
            ->where('id', $data->question_id ?? null)
            ->first();

        // LAST STAGE (Output Process)
        // ===========================
        $result = $question;
        return json($result);
    }

    public function photos(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        $user_id = JwtToken::getCurrentId();
        $id_member = JwtToken::getExtendVal('id_member');

        // MIDDLE STAGE (Main Process)
        // ===========================
        $photos = Db::table('participant_photo')
            ->selectRaw('id, type, filename, image_url')
            ->where('schedule_request_id', $data->schedule_request_id)
            ->where('id_member', $id_member)
            ->get();

        // LAST STAGE (Output Process)
        // ===========================
        $result = $photos;
        return json($result);
    }

    public function upload_photo(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        $id_member = JwtToken::getExtendVal('id_member');

        $uploadType = ['exam_start', 'exam_finish', 'exam_rnd_1', 'exam_rnd_2'];
        if (!in_array($data->type, $uploadType)) {
            $uploadTypeStr = implode(',', $uploadType);
            return jsonr(['message' => "Upload type is not defined, etc: {$uploadTypeStr}."]);
        }
        $type = $data->type;

        // MIDDLE STAGE (Main Process)
        // ===========================
        try {
            $url = MyFunc::upload_s3($request, [
                'file_name' => "{$type}-{$id_member}",
                'folder'    => "images/examination/{$data->schedule_request_id}/",
            ]);

            $photo = Db::table('participant_photo')
                ->where('schedule_request_id', $data->schedule_request_id)
                ->where('id_member', $id_member)
                ->where('type', $type)
                ->first();

            if ($photo == null) {
                $count = Db::table('participant_photo')
                    ->insert([
                        'schedule_request_id' => $data->schedule_request_id,
                        'id_member' => $id_member,
                        'type'      => $type,
                        'image_url' => $url,
                    ]);
            } else {
                $count = Db::table('participant_photo')
                    ->where('schedule_request_id', $data->schedule_request_id)
                    ->where('id_member', $id_member)
                    ->where('type', $type)
                    ->update([
                        'image_url' => $url,
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
