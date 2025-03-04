<?php

namespace app\api;

use support\Request;
use Firuze\Jwt\JwtToken;
use support\Db;

class Admin_v1
{
    protected $noNeedLogin = ['index', 'user', 'member', 'update_schedule', 'clear_exam'];

    public function index(Request $request)
    {
        return json(['message' => "Rynest => Admin API v1"]);
    }

    public function user(Request $request)
    {
        $data = (object) $request->post();

        $user = Db::table('tbl_users')->where('username', $data->identifier)->first();
        $member = Db::table('members')->where('user_id', $user->id ?? null)->first();
        $certificate = Db::table('exam_results_sertifikat')
            ->selectRaw('no_sertifikat.id, no_sertifikat.no_sertifikat, exam_results_sertifikat.id_member, exam_results_sertifikat.realese_date, exam_results_sertifikat.expired_date')
            ->join('no_sertifikat', 'exam_results_sertifikat.id_no_sertfikat', '=', 'no_sertifikat.id')
            ->where('exam_results_sertifikat.id_member', $member->id)
            ->first();
        $company = Db::table('companies')->where('id', $member->company_id ?? null)->first();
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
            ->selectRaw('id, id_member, member_id, category_id, schedule_request_id, question_ids, answer_keys, score, status, click_score, questions, passed_grade, duration, start_at, finish_at, device, ip_address, lat, lng')
            ->where('schedule_request_id', $schedule->schedule_request_id ?? null)
            ->where('id_member', $member->id ?? null)
            ->first();

        $result = (object) [];
        $result->user = $user;
        $result->member = $member;
        $result->certificate = $certificate;
        $result->company = $company;
        $result->schedule = $schedule;
        $result->photos = $photos;
        $result->category = $category;
        $result->location = $location;
        $result->exam_result = $exam_result;
        return json($result);
    }

    public function member(Request $request)
    {
        $data = (object) $request->post();

        $member = Db::table('members')
            ->orWhereLike('member_id', $data->identifier)
            ->orWhereLike('identity_card', $data->identifier)
            ->first();
        $user = Db::table('tbl_users')->where('id', $member->user_id ?? null)->first();
        $certificate = Db::table('exam_results_sertifikat')
            ->selectRaw('no_sertifikat.id, no_sertifikat.no_sertifikat, exam_results_sertifikat.id_member, exam_results_sertifikat.realese_date, exam_results_sertifikat.expired_date')
            ->join('no_sertifikat', 'exam_results_sertifikat.id_no_sertfikat', '=', 'no_sertifikat.id')
            ->where('exam_results_sertifikat.id_member', $member->id)
            ->first();
        $company = Db::table('companies')->where('id', $member->company_id ?? null)->first();
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
            ->selectRaw('id, id_member, member_id, category_id, schedule_request_id, question_ids, answer_keys, score, status, click_score, questions, passed_grade, duration, start_at, finish_at, device, ip_address, lat, lng')
            ->where('schedule_request_id', $schedule->schedule_request_id ?? null)
            ->where('id_member', $member->id ?? null)
            ->first();

        $result = (object) [];
        $result->member = $member;
        $result->user = $user;
        $result->certificate = $certificate;
        $result->company = $company;
        $result->schedule = $schedule;
        $result->photos = $photos;
        $result->category = $category;
        $result->location = $location;
        $result->exam_result = $exam_result;
        return json($result);
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
}
