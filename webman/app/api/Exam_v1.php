<?php

namespace app\api;

use support\Request;
use Firuze\Jwt\JwtToken;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;
use stdClass;
use support\Db;
use support\MyFunc;
use support\Redis;
use Webman\RedisQueue\Redis as RedisQueue;

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
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('datetime', v::dateTime('Y-m-d H:i:s')->notEmpty());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages([
                'attribute' => 'Params [{{name}}] is required',
                'dateTime' => '[{{name}}] format is [Y-m-d H:i:s]',
                'notEmpty' => '[{{name}}] must not empty',
            ]);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $user_id = JwtToken::getCurrentId();

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
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

            $category = Db::table('categories')
                ->selectRaw('categories.id, categories.name, categories.description, categories.duration, categories.passed_grade, module_id, modules.name as module_name, questions, easy, medium, hard, status')
                ->join('category_modules', 'categories.id', '=', 'category_modules.category_id')
                ->join('modules', 'category_modules.module_id', '=', 'modules.id')
                ->where('categories.id', $schedule->category_id ?? null)->first();

            $min_point = abs(ceil($category->questions * $category->passed_grade / 100));

            $exam_result = Db::table('exam_results')
                ->selectRaw('id, id_member, member_id, category_id, schedule_request_id, question_ids, answer_keys, score, status, click_score, questions, passed_grade, duration, start_at, finish_at, device, ip_address')
                ->where('schedule_request_id', $schedule->schedule_request_id ?? null)
                ->where('id_member', $member->id ?? null)
                ->first();

            $openReg = date_create($schedule->open_registration);
            $closeReg = date_create($schedule->close_registration);
            $inputDate = date_create($data->datetime);
            $state = self::_get_exam_state($inputDate, $openReg, $closeReg, $exam_result);

            // Exam Photos
            $photos = Db::table('participant_photo')
                ->where('schedule_request_id', $schedule->schedule_request_id ?? null)
                ->where('id_member', $member->id)
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

            Db::commit();
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

        // LAST STAGE (Output Process)
        // ===========================
        $result = $schedule;
        $result->state = $state;
        $result->category = $category;
        $result->category->min_point = $min_point;
        $result->photo_start = $photo_start;
        $result->photo_finish = $photo_finish;
        $result->photos = $photos;
        return json($result);
    }

    // Examination State :
    // - NOT-YET-OPEN
    // - IN-SCHEDULE
    // - EXPIRED
    // - ON-GOING
    // - COMPLETED
    private function _get_exam_state(\DateTime $inputDate, \DateTime $openReg, \DateTime $closeReg, $exam_result): string
    {
        $state = '';
        if ($inputDate < $openReg) {
            if ($exam_result == null) {
                $state = 'NOT-YET-OPEN';
            } else {
                if (empty($exam_result->status)) {
                    $state = 'ON-GOING';
                } else {
                    $state = 'COMPLETED';
                }
            }
        } else if ($inputDate > $closeReg) {
            if ($exam_result == null) {
                $state = 'EXPIRED';
            } else {
                if (empty($exam_result->status)) {
                    $state = 'ON-GOING';
                } else {
                    $state = 'COMPLETED';
                }
            }
        } else {
            if ($exam_result == null) {
                $state = 'IN-SCHEDULE';
            } else {
                if (empty($exam_result->status)) {
                    $state = 'ON-GOING';
                } else {
                    $state = 'COMPLETED';
                }
            }
        }
        return $state;
    }

    public function result(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('schedule_request_id', v::intType()->notEmpty())
                ->attribute('device_id', v::stringType()->notEmpty());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages([
                'attribute' => 'Params [{{name}}] is required',
                'intType' => '[{{name}}] must be integer',
                'notEmpty' => '[{{name}}] must not empty',
            ]);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $id_member = JwtToken::getExtendVal('id_member');

        // SESSION CHECK STAGE
        // ===================
        if (self::_check_session($data->schedule_request_id, $id_member, $data->device_id) == false) {
            return jsonr(['message' => 'Another device has been login'], 409);
        }

        // MIDDLE STAGE (Main Process)
        // ===========================
        // Db::beginTransaction();
        try {
            $exam_result = Db::table('exam_results')
                ->selectRaw('id, category_id, score, status, note, click_score, cek_score, questions, passed_grade, duration, start_at, finish_at, answer_keys, the_keys, restart, device, ip_address, location')
                ->where('schedule_request_id', $data->schedule_request_id ?? null)
                ->where('id_member', $id_member)
                ->first();

            if ($exam_result == null) {
                return json(null);
            }

            $exam_session = Db::table('exam_session')
                ->selectRaw('restart, device_id, device_name, ip_address, location, restart_at')
                ->where('schedule_request_id', $data->schedule_request_id ?? null)
                ->where('id_member', $id_member)
                ->get();

            $category = Db::table('categories')
                ->selectRaw('categories.id, categories.name, categories.description, categories.duration, categories.passed_grade, module_id, modules.name as module_name, questions, easy, medium, hard, status')
                ->join('category_modules', 'categories.id', '=', 'category_modules.category_id')
                ->join('modules', 'category_modules.module_id', '=', 'modules.id')
                ->where('categories.id', $exam_result->category_id ?? null)->first();

            // Result Status:
            // - TEMPORARY
            // - COMPLETED
            $status = 'TEMPORARY';
            if (empty($exam_result->status)) {
                $status = 'TEMPORARY';
            } else {
                $status = 'COMPLETED';
            }

            // Answered question count
            $arrAnswerKeys = explode(',', $exam_result->answer_keys);
            $arrCount = array_count_values($arrAnswerKeys);
            $countNotAnswered = $arrCount['X'] ?? 0;
            $exam_result->answered_count = $exam_result->questions - $countNotAnswered;

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

        Db::beginTransaction();
        try {
            // Update field check Score
            $count = Db::table('exam_results')
                ->where('id', $exam_result->id)
                ->update([
                    'score' => $exam_result->score,
                ]);

            Db::commit();
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

        // LAST STAGE (Output Process)
        // ===========================
        // Clearing the output
        unset($exam_result->answer_keys);
        unset($exam_result->the_keys);
        unset($exam_result->status);
        unset($exam_result->cek_score);

        $result = $exam_result;
        $result->duration ??= $category->duration;
        $result->real_duration = $real_duration;
        $result->passed_grade ??= $category->passed_grade;
        $result->score = $score;
        $result->check_score = $check_score;
        $result->desc1 = $desc1;
        $result->desc2 = 'Minimum Jawaban Benar adalah 42 dari total soal yang diujikan.';
        $result->photo_start = $photo_start;
        $result->photo_finish = $photo_finish;
        $result->state = $status;
        $result->session = $exam_session;
        return json($result);
    }

    // function _get_exam_photos(int $schedule_request_id, int $id_member): ?object
    // {
    //     $photos = Db::table('participant_photo')
    //         ->where('schedule_request_id', $schedule_request_id)
    //         ->where('id_member', $id_member)
    //         ->get();

    //     $photo_start = false;
    //     $photo_finish = false;
    //     foreach ($photos as $key => $value) {
    //         if ($value->type == 'exam_start') {
    //             $photo_start = true;
    //         } else if ($value->type == 'exam_finish') {
    //             $photo_finish = true;
    //         }
    //     }

    //     $result = $photos;
    //     $result['photo_start'] = $photo_start;
    //     $result['photo_finish'] = $photo_finish;
    //     return $result;
    // }

    public function info(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('schedule_request_id', v::intType()->notEmpty())
                ->attribute('category_id', v::intType()->notEmpty())
                ->attribute('device_id', v::stringType()->notEmpty());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages([
                'attribute' => 'Params [{{name}}] is required',
                'intType' => '[{{name}}] must be integer',
                'notEmpty' => '[{{name}}] must not empty',
            ]);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $user_id = JwtToken::getCurrentId();
        $id_member = JwtToken::getExtendVal('id_member');

        // MIDDLE STAGE (Main Process)
        // ===========================
        // Db::beginTransaction();
        try {
            $exam_result = Db::table('exam_results')
                ->selectRaw('id, question_ids, answer_keys, sync_question, status, click_score, cek_score, questions, duration, start_at, finish_at, ip_address, location, device')
                ->where('schedule_request_id', $data->schedule_request_id ?? null)
                ->where('id_member', $id_member)
                ->first();

            $category = Db::table('categories')
                ->selectRaw('categories.id, categories.name, categories.description, categories.duration, categories.passed_grade, module_id, modules.name as module_name, questions, easy, medium, hard, status')
                ->join('category_modules', 'categories.id', '=', 'category_modules.category_id')
                ->join('modules', 'category_modules.module_id', '=', 'modules.id')
                ->where('categories.id', $data->category_id)->first();

            if ($exam_result == null) {
                return json(null);
            }
            $exam_result->check_score = $exam_result->cek_score ?? 0;
            $exam_result->duration = $exam_result->duration ?? $category->duration;
            $exam_result->state = empty($exam_result->status) ? 'ON-GOING' : 'COMPLETED';
            if ($exam_result->state == 'COMPLETED') {
                // FINISH EXAM SESSION
                self::_finish_session($data->schedule_request_id, $id_member);
            }

            // Db::commit();
        } catch (\Throwable $th) {
            // Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

        // LAST STAGE (Output Process)
        // ===========================
        // Clearing the output
        unset($exam_result->id_member);
        unset($exam_result->schedule_request_id);
        unset($exam_result->category_id);
        unset($exam_result->ip_address);
        unset($exam_result->location);
        unset($exam_result->device);
        unset($exam_result->cek_score);
        unset($exam_result->status);
        unset($exam_result->score);
        unset($exam_result->passed_grade);
        // unset($exam_result->duration);
        // unset($exam_result->questions);

        $result = $exam_result;
        return json($result);
    }

    public function start(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('schedule_request_id', v::intType()->notEmpty())
                ->attribute('category_id', v::intType()->notEmpty())
                ->attribute('start_at', v::dateTime('Y-m-d H:i:s')->notEmpty())
                ->attribute('device_id', v::stringType()->notEmpty());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages([
                'attribute' => 'Params [{{name}}] is required',
                'intType' => '[{{name}}] must be integer',
                'dateTime' => '[{{name}}] format is [Y-m-d H:i:s]',
                'notEmpty' => '[{{name}}] must not empty',
            ]);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $user_id = JwtToken::getCurrentId();
        $id_member = JwtToken::getExtendVal('id_member');

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            $exam_result = Db::table('exam_results')
                ->selectRaw('id, question_ids, answer_keys, sync_question, status, click_score, cek_score, questions, duration, start_at, finish_at, restart, ip_address, location, device')
                ->where('schedule_request_id', $data->schedule_request_id ?? null)
                ->where('id_member', $id_member)
                ->first();

            $category = Db::table('categories')
                ->selectRaw('categories.id, categories.name, categories.description, categories.duration, categories.passed_grade, module_id, modules.name as module_name, questions, easy, medium, hard, status')
                ->join('category_modules', 'categories.id', '=', 'category_modules.category_id')
                ->join('modules', 'category_modules.module_id', '=', 'modules.id')
                ->where('categories.id', $data->category_id)->first();

            if ($exam_result == null) {
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
                    'sync_question'       => 0,
                    'click_score'         => 2,
                    'cek_score'           => 0,
                    'question_ids'        => implode(",", $question_ids),
                    'answer_keys'         => implode(",", $answered_default),
                    'the_keys'            => implode(",", $answer_key),
                    'questions'           => $category->questions,
                    'passed_grade'        => $category->passed_grade,
                    'duration'            => $category->duration,
                    'start_at'            => $data->start_at ?? date('Y-m-d H:i:s'),
                    'finish_at'           => null,
                    'restart'             => 0,
                    'device'              => $data->device_id ?? '',
                    'ip_address'          => $data->ip_address ?? '',
                    'location'            => $data->location ?? '',
                ];

                $id = Db::table('exam_results')->insertGetId($exam_result);

                $exam_result['id'] = $id ?? null;
                $exam_result['state'] = 'ON-GOING';

                // EXAM SESSION
                // ============
                $count = Db::table('exam_session')
                    ->where([
                        'schedule_request_id' => $data->schedule_request_id,
                        'id_member'     => $id_member,
                    ])
                    ->delete();

                $count = Db::table('exam_session')
                    ->insert([
                        'schedule_request_id' => $data->schedule_request_id,
                        'id_member'     => $id_member,
                        'restart'       => 0,
                        'device_id'     => $data->device_id ?? '',
                        'device_name'   => $data->device_name ?? '',
                        'ip_address'    => $data->ip_address ?? '',
                        'location'      => $data->location ?? '',
                        'restart_at'    => date('Y-m-d H:i:s'),
                    ]);

                // START EXAM SESSION
                self::_start_session($data->schedule_request_id, $id_member, $data->device_id);
            } else {
                $exam_result->check_score = $exam_result->cek_score ?? 0;
                $exam_result->duration = $exam_result->duration ?? $category->duration;
                $exam_result->state = empty($exam_result->status) ? 'ON-GOING' : 'COMPLETED';
                if ($exam_result->state == 'COMPLETED') {
                    // FINISH EXAM SESSION
                    self::_finish_session($data->schedule_request_id, $id_member);
                } else {
                    // START EXAM SESSION
                    self::_start_session($data->schedule_request_id, $id_member, $data->device_id);
                }

                // EXAM SESSION
                // ============
                $restart = $exam_result->restart + 1;
                $count = Db::table('exam_results')
                    ->where('id', $exam_result->id)
                    ->update([
                        'restart' => $restart,
                    ]);

                $count = Db::table('exam_session')
                    ->insert([
                        'schedule_request_id' => $data->schedule_request_id,
                        'id_member'     => $id_member,
                        'restart'       => $restart,
                        'device_id'     => $data->device_id ?? '',
                        'device_name'   => $data->device_name ?? '',
                        'ip_address'    => $data->ip_address ?? '',
                        'location'      => $data->location ?? '',
                        'restart_at'    => date('Y-m-d H:i:s'),
                    ]);

                // SECURITY CHECK: If difference Device is detected
                if ($exam_result->device != $data->device_id) {
                    // TODO: Send notidification to creator/initiator
                }

                // SECURITY CHECK: If difference IP is detected
                // if ($exam_result->ip_address != $data->ip_address) {
                //     // TODO: Send notification to creator/initiator
                // }
            }

            Db::commit();
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

        // LAST STAGE (Output Process)
        // ===========================
        // Clearing the output
        unset($exam_result->id_member);
        unset($exam_result->schedule_request_id);
        unset($exam_result->category_id);
        unset($exam_result->ip_address);
        unset($exam_result->location);
        unset($exam_result->device);
        unset($exam_result->cek_score);
        unset($exam_result->status);
        unset($exam_result->score);
        unset($exam_result->passed_grade);

        $result = $exam_result;
        return json($result);
    }

    public function answer(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('schedule_request_id', v::intType()->notEmpty())
                ->attribute('question_id', v::stringType()->notEmpty())
                ->attribute('answered_key', v::stringType()->notEmpty())
                ->attribute('device_id', v::stringType()->notEmpty());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages([
                'attribute' => 'Params [{{name}}] is required',
                'intType' => '[{{name}}] must be integer',
                'notEmpty' => '[{{name}}] must not empty',
            ]);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $user_id = JwtToken::getCurrentId();
        $id_member = JwtToken::getExtendVal('id_member');

        // SESSION CHECK STAGE
        // ===================
        if (self::_check_session($data->schedule_request_id, $id_member, $data->device_id) == false) {
            return jsonr(['message' => 'Another device has been login'], 409);
        }

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            $exam_result = Db::table('exam_results')
                ->selectRaw('id, id_member, schedule_request_id, category_id, question_ids, answer_keys, score, status, click_score, questions, passed_grade, duration, start_at, finish_at, the_keys')
                ->where('schedule_request_id', $data->schedule_request_id ?? null)
                ->where('id_member', $id_member)
                ->first();

            if ($exam_result == null) {
                return jsonr(['message' => "Incorrect examination !!"]);
            }

            if (!empty($exam_result->status)) {
                return jsonr(['message' => "Examination has been finished !!"]);
            }

            // REPLACE ANSWER_KEYS
            $arrQuestions = explode(',', $exam_result->question_ids);
            $arrAnswerKeys = explode(',', $exam_result->answer_keys);
            $index = array_keys($arrQuestions, $data->question_id);
            if ($index == false) {
                return jsonr(['message' => "Incorrect [question_id] !!"]);
            }
            $arrAnswerKeys[$index[0]] = $data->answered_key ?? 'X';

            // Db::beginTransaction();
            $count = Db::table('exam_results')
                ->where('id', $exam_result->id)
                ->update([
                    'answer_keys' => implode(',', $arrAnswerKeys),
                ]);

            Db::commit();
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

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
        try {
            $inputValidator = v::attribute('schedule_request_id', v::intType()->notEmpty())
                ->attribute('device_id', v::stringType()->notEmpty());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages([
                'attribute' => 'Params [{{name}}] is required',
                'intType' => '[{{name}}] must be integer',
                'notEmpty' => '[{{name}}] must not empty',
            ]);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $id_member = JwtToken::getExtendVal('id_member');

        // SESSION CHECK STAGE
        // ===================
        if (self::_check_session($data->schedule_request_id, $id_member, $data->device_id) == false) {
            return jsonr(['message' => 'Another device has been login'], 409);
        }

        // MIDDLE STAGE (Main Process)
        // ===========================
        // Db::beginTransaction();
        try {
            $exam_result = Db::table('exam_results')
                ->selectRaw('id, score, status, click_score, cek_score, questions, passed_grade, duration, start_at, finish_at, answer_keys, the_keys')
                ->where('schedule_request_id', $data->schedule_request_id ?? null)
                ->where('id_member', $id_member)
                ->first();

            if ($exam_result == null) {
                return json(null);
            }

            if ($exam_result->cek_score >= $exam_result->click_score) {
                return jsonr(['message' => "Check score has reached the limit [max: {$exam_result->click_score} times]"]);
            }

            // Answered question count
            $arrAnswerKeys = explode(',', $exam_result->answer_keys);
            $arrCount = array_count_values($arrAnswerKeys);
            $countNotAnswered = $arrCount['X'] ?? 0;
            $exam_result->answered_count = $exam_result->questions - $countNotAnswered;

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

            $exam_result->state = empty($exam_result->status) ? 'ON-GOING' : 'COMPLETED';

            // Db::commit();
        } catch (\Throwable $th) {
            // Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

        Db::beginTransaction();
        try {
            if ($exam_result->state == 'ON-GOING') {
                // Update field check Score
                $exam_result->cek_score = $exam_result->cek_score + 1;
                $exam_result->check_score = $exam_result->cek_score;

                $count = Db::table('exam_results')
                    ->where('id', $exam_result->id)
                    ->update([
                        'cek_score' => $exam_result->cek_score,
                        'score' => $exam_result->score,
                    ]);
            }

            Db::commit();
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

        // LAST STAGE (Output Process)
        // ===========================
        // Clearing the output
        unset($exam_result->answer_keys);
        unset($exam_result->the_keys);
        unset($exam_result->cek_score);

        $result = $exam_result;
        return json($result);
    }

    public function finish(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('schedule_request_id', v::intType()->notEmpty())
                ->attribute('finish_at', v::dateTime('Y-m-d H:i:s')->notEmpty())
                ->attribute('device_id', v::stringType()->notEmpty());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages([
                'attribute' => 'Params [{{name}}] is required',
                'intType' => '[{{name}}] must be integer',
                'dateTime' => '[{{name}}] format is [Y-m-d H:i:s]',
                'notEmpty' => '[{{name}}] must not empty',
            ]);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $user_id = JwtToken::getCurrentId();
        $id_member = JwtToken::getExtendVal('id_member');

        // SESSION CHECK STAGE
        // ===================
        if (self::_check_session($data->schedule_request_id, $id_member, $data->device_id) == false) {
            return jsonr(['message' => 'Another device has been login'], 409);
        }

        // MIDDLE STAGE (Main Process)
        // ===========================
        // Db::beginTransaction();
        try {
            $exam_result = Db::table('exam_results')
                ->selectRaw('id, question_ids, answer_keys, sync_question, status, click_score, cek_score, start_at, finish_at, ip_address, location, device')
                ->where('schedule_request_id', $data->schedule_request_id ?? null)
                ->where('id_member', $id_member)
                ->first();

            if ($exam_result == null) {
                return jsonr(['message' => "Incorrect examination !!"]);
            }

            if (!empty($exam_result->status)) {
                return jsonr(['message' => "Examination has been finished !!"]);
            }

            // FINISH EXAM SESSION
            self::_finish_session($data->schedule_request_id, $id_member);

            // Db::commit();
        } catch (\Throwable $th) {
            // Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

        Db::beginTransaction();
        try {
            $count = Db::table('exam_results')
                ->where('id', $exam_result->id)
                ->update([
                    'status'    => 'completed',
                    'finish_at' => $data->finish_at ?? date('Y-m-d H:i:s'),
                ]);

            Db::commit();
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

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
        try {
            $inputValidator = v::attribute('schedule_request_id', v::intType()->notEmpty())
                ->attribute('question_id', v::intType()->notEmpty())
                ->attribute('shuffle', v::stringType()->notEmpty())
                ->attribute('device_id', v::stringType()->notEmpty());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages([
                'attribute' => 'Params [{{name}}] is required',
                'intType' => '[{{name}}] must be integer',
                'notEmpty' => '[{{name}}] must not empty',
            ]);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $user_id = JwtToken::getCurrentId();
        $id_member = JwtToken::getExtendVal('id_member');

        // SESSION CHECK STAGE
        // ===================
        if (self::_check_session($data->schedule_request_id, $id_member, $data->device_id) == false) {
            return jsonr(['message' => 'Another device has been login'], 409);
        }

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            // Get from Redis
            $question = Redis::get("question-{$data->question_id}");
            if ($question == null) {
                $question = Db::table('questions')
                    ->selectRaw('id, question, answer_option_a, answer_option_b, answer_option_c, answer_option_d, answer_key')
                    ->where('id', $data->question_id ?? null)
                    ->first();

                // Save to Redis
                Redis::set("question-{$data->question_id}", json_encode($question));
            } else {
                $question = json_decode($question);
            }

            // Shuffel the choices
            $question = self::_shuffled_options($question, $data->shuffle);

            // Update field [sync_question] on table exam_results, with question_id
            $sync_question = $data->sync_question ?? false;
            if ($sync_question) {
                $dataQueue['schedule_request_id'] = $data->schedule_request_id;
                $dataQueue['id_member'] = $id_member;
                $dataQueue['question_id'] = $data->question_id;
                RedisQueue::send('sync-question', $dataQueue);
            }

            Db::commit();
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

        // LAST STAGE (Output Process)
        // ===========================
        // Clearing the output
        unset($question->answer_option_a);
        unset($question->answer_option_b);
        unset($question->answer_option_c);
        unset($question->answer_option_d);
        // unset($question->answer_key);

        $result = $question;
        return json($result);
    }

    /**
     * Make a shuffled options
     * @param object $question <p>
     * Object from table question.
     * </p>
     * @return object the question with shuffled options.
     */
    function _shuffled_options($question = new stdClass, $shuffle = 'ABCD'): object
    {
        // Shuffel the choices
        $a = strtolower(substr($shuffle, 0, 1));
        $b = strtolower(substr($shuffle, 1, 1));
        $c = strtolower(substr($shuffle, 2, 1));
        $d = strtolower(substr($shuffle, 3, 1));
        $result = new stdClass;
        $result->id = $question->id;
        $result->question = $question->question;
        $result->answer_key = strtolower($question->answer_key);
        $result->option_a = $a;
        $result->option_b = $b;
        $result->option_c = $c;
        $result->option_d = $d;
        $result->shuffle_option_a = $question->{"answer_option_$a"};
        $result->shuffle_option_b = $question->{"answer_option_$b"};
        $result->shuffle_option_c = $question->{"answer_option_$c"};
        $result->shuffle_option_d = $question->{"answer_option_$d"};

        return $result;
        // return (object) array_merge((array) $result, (array) $question);
    }

    public function questions(Request $request)
    {
        // FIRST STAGE (Parameters)
        // ========================
        $data = (object) $request->post();
        try {
            $inputValidator = v::attribute('schedule_request_id', v::intType()->notEmpty());
            $inputValidator->assert($data);
        } catch (NestedValidationException $e) {
            $errAttr = $e->getMessages([
                'attribute' => 'Params [{{name}}] is required',
                'intType' => '[{{name}}] must be integer',
                'notEmpty' => '[{{name}}] must not empty',
            ]);
            $errMessage = join(", ", (array) $errAttr['attribute']);
            return jsonr(['message' => $errMessage]);
        }
        $user_id = JwtToken::getCurrentId();
        $id_member = JwtToken::getExtendVal('id_member');

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
        try {
            $exam_result = Db::table('exam_results')
                ->selectRaw('id, question_ids, answer_keys')
                ->where('schedule_request_id', $data->schedule_request_id ?? null)
                ->where('id_member', $id_member)
                ->first();

            // Separation the [question_ids] between id and option, eg: 1001ABCD
            // become an array like: [1001 => "ABCD"]
            $questionIds = explode(',', $exam_result->question_ids);
            $keys = [];
            foreach ($questionIds as $key => $value) {
                $id = substr($value, 0, strlen($value) - 4);
                $keys[$id] = substr($value, -4, 4);
            }

            // Fetch the question from database
            $questions = Db::table('questions')
                ->selectRaw('id, question, answer_option_a, answer_option_b, answer_option_c, answer_option_d, answer_key')
                ->whereIn('id', array_keys($keys) ?? null)
                ->get();

            // Shuffle the choice options
            $shuffleQ = [];
            foreach ($questions as $key => $value) {
                $shuffle = $keys[$value->id];
                // $questions[$key] = self::_shuffled_options($value, $shuffle);
                $shuffleQ[$key] = self::_shuffled_options($value, $shuffle);
            }

            // Sort back the result to original
            $shuffleQuestions = [];
            foreach ($keys as $key => $value) {
                $res = array_values(array_filter($shuffleQ, function ($k) use ($key) {
                    return $k->id == $key;
                }));
                array_push($shuffleQuestions, $res[0]);
            }

            Db::commit();
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

        // LAST STAGE (Output Process)
        // ===========================
        // $result = new stdClass;
        // $result->question_ids = $exam_result->question_ids;
        // $result->questions = $shuffleQuestions;
        $result = $shuffleQuestions;
        return json($result);
    }

    private function _start_session(int $schedule_request_id, int $id_member, string $device_id)
    {
        $sessionId = "exam-session-{$schedule_request_id}-{$id_member}";
        $sessionData = $device_id;
        Redis::set($sessionId, $sessionData);
    }

    private function _finish_session(int $schedule_request_id, int $id_member)
    {
        $sessionId = "exam-session-{$schedule_request_id}-{$id_member}";
        Redis::del($sessionId);
    }

    private function _check_session(int $schedule_request_id, int $id_member, string $device_id): bool
    {
        $sessionId = "exam-session-{$schedule_request_id}-{$id_member}";
        // $ret = Redis::exists($sessionId);
        $ret = Redis::get($sessionId);
        if ($ret == null) {
            // Session does not exists
            // it means: exam start has not been executed or exam has been finished
            return true;
        }

        // Session exists
        return $ret == $device_id;
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
        Db::beginTransaction();
        try {
            $photos = Db::table('participant_photo')
                ->selectRaw('id, type, filename, image_url')
                ->where('schedule_request_id', $data->schedule_request_id)
                ->where('id_member', $id_member)
                ->get();

            Db::commit();
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

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
        try {
            $inputValidator = v::attribute('schedule_request_id', v::notEmpty())
                ->attribute('type', v::notEmpty());
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

        $uploadType = ['exam_start', 'exam_finish', 'exam_rnd_1', 'exam_rnd_2'];
        if (!in_array($data->type, $uploadType)) {
            $uploadTypeStr = implode('|', $uploadType);
            return jsonr(['message' => "[type] not allowed, except: [{$uploadTypeStr}]"]);
        }
        $type = $data->type;

        // MIDDLE STAGE (Main Process)
        // ===========================
        Db::beginTransaction();
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

            Db::commit();
        } catch (\Throwable $th) {
            Db::rollBack();
            return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
        }

        // LAST STAGE (Output Process)
        // ===========================
        $result = ['url' => $url];
        return json($result);
    }
}

// Db::beginTransaction();
// try {

//     Db::commit();
// } catch (\Throwable $th) {
//     Db::rollBack();
//     return jsonr(["message" => $th->getMessage(), "trace" => $th->getTrace()]);
// }
