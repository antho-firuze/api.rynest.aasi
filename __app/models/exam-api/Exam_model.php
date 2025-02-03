<?php if (!defined('BASEPATH')) {exit('No direct script access allowed');}

define('DEPRECATED_DATE', '2020-03-29');

class Exam_model extends CI_Model
{

	function __construct()
	{
		parent::__construct();
		$this->load->library(['f', 'lsp']);
		$this->load->database(DB_CONN[HTTP_HOST]);
	}
	
	function login($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['username','password','date_client','time_client']);
		if (!$success) return [FALSE, $return];
    
		list($success, $return) = $this->lsp->is_valid_auth($request);
		if (!$success) return [FALSE, $return];

		$str = "(
      select t1.member_id, t1.schedule_request_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where t1.member_id = ? and t3.date = ? 
			and t3.pre <= ? and ADDTIME(t3.begin, SEC_TO_TIME(t3.duration*60)) >= ? and t2.location_id=2
			limit 1
		) g0";
		$table = $this->f->compile_qry($str, [$request->member->member_id, $request->params->date_client, $request->params->time_client, $request->params->time_client]);
		if (!$result = $this->db->from($table)->get())
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		if (!$row = $result->row())
			return [FALSE, ['message' => $this->f->_err_msg('err_not_in_schedule')]];

		$photo_link = "http://49.0.1.74/assets/img/user/";

		return [TRUE, [
			'message' => $this->f->_err_msg('success_member_in_schedule'),
			'result' => [
				'user' 	 => [
					'fullname'	 => $request->user->fullname,
					'email'			 => $request->user->email,
					'phone'			 => $request->user->phone,
					'photo_link' => $request->member->photo ? $photo_link.$request->member->photo : '',
				],
			],
		]];
	}

	function start($request)
	{
		// Buat nyimpan data lokasi & mulai start ujian apa? Table Exam_logs, Exam_results, Schedule participant
		list($success, $return) = $this->f->check_param_required($request, ['username','password','coordinate','date_client','time_client']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->is_valid_auth($request);
		if (!$success) return [FALSE, $return];
    
		$str = '(
			select t1.member_id, t1.schedule_request_id, t3.category_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where member_id = ? and t3.date = ? and t3.begin <= ? limit 1
		) g0';
		$table = $this->f->compile_qry($str, [$request->member->member_id, $request->params->date_client, $request->params->time_client]);
		if (!$result = $this->db->from($table)->get())
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		if (!$schedule = $result->row())
			return [FALSE, ['message' => $this->f->_err_msg('err_not_in_schedule')]];

		$minutes = $schedule->duration*60;
		$exam_end = date('H:i:s', strtotime($schedule->begin) + $minutes);

		$str = "(
			SELECT * FROM exam_results
			where schedule_request_id = ? and member_id = ?
		) g0";
		$table = $this->f->compile_qry($str, [$schedule->schedule_request_id, $request->member->member_id]);
		if (! $exam_results = $this->db->from($table)->get()->row()) {
			$result = $this->db->insert('exam_results', [
				'schedule_request_id' => $schedule->schedule_request_id, 
				'category_id' 	=> $schedule->category_id, 
				'member_id' 		=> $request->member->member_id, 
				'begin' 				=> strtotime($request->params->date_client.' '.$request->params->time_client),
				'question_ids' 	=> '',
				'answer_keys'  	=> '',
				'sync_time' 	 	=> strtotime(date('Y-m-d H:i:s')),
				'sync_question' => 0,
				'score' 				=> '0/0',
				'status' 				=> '',
			]);
			if (!$result)
				return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

			$json_state = json_encode([
				'name' 				=> 'start_exam',
				'activity' 		=> 'confirmation',
				'date_client' => $request->params->date_client,
				'time_client' => $request->params->time_client,
				'exam_begin'	=> $schedule->begin,
				'exam_end'		=> $exam_end,
				'duration'		=> $schedule->duration,
				'longlat'			=> $request->params->coordinate,
			]);

			$result = $this->db->insert('exam_logs', [
				'schedule_request_id' => $schedule->schedule_request_id, 
				'member_id'  => $request->member->member_id, 
				'state' 		 => $json_state, 
				'ip_address' => 'mobile',
				'user_agent' => $request->agent,
				'coordinate' => $request->params->coordinate,
				'author' 		 => 0,
				'created_on' => strtotime(date('Y-m-d H:i:s')),
			]);
			if (!$result)
				return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

			return [TRUE, ['result' => [
				'date_client' => $request->params->date_client, 
				'time_client' => $request->params->time_client,
				'exam_begin'	=> $schedule->begin,
				'exam_end'		=> $exam_end,
				'duration'		=> $schedule->duration,
			]]];

		} else {

			$json_state = json_encode([
				'name' 				=> 'start_exam',
				'activity' 		=> 'confirmation',
				'date_client' => $request->params->date_client,
				'time_client' => $request->params->time_client,
				'exam_begin'	=> $schedule->begin,
				'exam_end'		=> $exam_end,
				'duration'		=> $schedule->duration,
				'longlat'			=> $request->params->coordinate,
			]);

			$result = $this->db->insert('exam_logs', [
				'schedule_request_id' => $schedule->schedule_request_id, 
				'member_id'  => $request->member->member_id, 
				'state' 		 => $json_state, 
				'ip_address' => 'mobile',
				'user_agent' => $request->agent,
				'coordinate' => $request->params->coordinate,
				'author' 		 => 0,
				'created_on' => strtotime(date('Y-m-d H:i:s')),
			]);
			
			return [TRUE, ['result' => [
				'date_client' => (new DateTime("@$exam_results->begin"))->format('Y-m-d'), 
				'time_client' => (new DateTime("@$exam_results->begin"))->format('H:i:s'),
				'exam_begin'	=> $schedule->begin,
				'exam_end'		=> $exam_end,
				'duration'		=> $schedule->duration,
			]]];
		} 

	}

	function score($num_of_correct, $num_of_question)
	{
		$num_of_question = $num_of_question ?? 1;

		try {

			$result = $num_of_correct * (100/$num_of_question);

		} catch(Exception $e) {

			return [FALSE, ['message' => $e->getMessage()]];

		}

		return [TRUE, ['result' => $result]];
	}

	function answer($request)
	{
		// Buat nyimpan data lokasi & mulai start ujian apa? Table Exam_logs, Exam_results, Schedule participant
		list($success, $return) = $this->f->check_param_required($request, ['username','password','date_client','time_client','question_id','answer_key','num_of_correct','num_of_question']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->is_valid_auth($request);
		if (!$success) return [FALSE, $return];
    
		$str = '(
			select t1.member_id, t1.schedule_request_id, t3.category_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where member_id = ? and t3.date = ? limit 1
		) g0';
		$table = $this->f->compile_qry($str, [$request->member->member_id, $request->params->date_client]);
		$this->db->from($table);
		$schedule = $this->db->get()->row();

		$str = "(
			SELECT * FROM exam_results
			where schedule_request_id = ? and member_id = ?
		) g0";
		$table = $this->f->compile_qry($str, [$schedule->schedule_request_id, $request->member->member_id]);
		if (! $exam_results = $this->db->from($table)->get()->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_exam_not_started')]];

		// Logic for calculate scoring
		list($success, $return) = $this->score($request->params->num_of_correct, $request->params->num_of_question);
		if (!$success) return [FALSE, $return];

		$score = $return['result'];
		
		// Logic for saving question & answer
		$id 	= substr($request->params->question_id, 0, strlen($request->params->question_id)-4);
		$abcd = substr($request->params->question_id, -4, 4);
		$key 	= $request->params->answer_key ? $request->params->answer_key : 0;

		$question_ids = strtoupper($exam_results->question_ids);
		$answer_keys  = strtoupper($exam_results->answer_keys);
		$arr_x = [];

		if ($question_ids != '') {
			$arr_q = explode(',',$question_ids);
			$arr_a = explode(',',$answer_keys);
			foreach($arr_q as $k => $v) {
				$arr_x[substr($v, 0, strlen($v)-4)] = ['abcd' => substr($v, -4, 4), 'key' => $arr_a[$k]];
			}
		}

		$arr_x[$id] = ['abcd' => $abcd, 'key' => $key];

		$arr_q = []; $arr_a = [];
		foreach($arr_x as $k => $v) {
			$arr_q[] = $k.$v['abcd'];
			$arr_a[] = $v['key'];
		}
		$question_ids = implode(',',$arr_q);
		$answer_keys  = implode(',',$arr_a);

		$result = $this->db->update('exam_results', 
			[
				'question_ids' => $question_ids,
				'answer_keys'  => $answer_keys,
				'score'	 		 	 => $score.'/'.$request->params->num_of_question,
				'sync_time'  	 => strtotime($request->params->date_client.' '.$request->params->time_client),
			],
			[
				'schedule_request_id' => $schedule->schedule_request_id, 
				'member_id' => $request->member->member_id, 
			]
		);
		if (!$result)
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		return [TRUE, ['result' => [
			'num_of_correct'  => $request->params->num_of_correct,
			'num_of_question' => $request->params->num_of_question,
			'score' 					=> $score,
		]]];
	}

	function finish($request)
	{
		// Buat nyimpan data lokasi & mulai start ujian apa? Table Exam_logs, Exam_results, Schedule participant
		list($success, $return) = $this->f->check_param_required($request, ['username','password','coordinate','date_client','time_client','num_of_correct','num_of_question']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->is_valid_auth($request);
		if (!$success) return [FALSE, $return];
    
		$str = "(
			select t1.member_id, t1.schedule_request_id, t3.category_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where member_id = ? and t3.date = ? limit 1
		) g0";
		$table = $this->f->compile_qry($str, [$request->member->member_id, $request->params->date_client]);
		$this->db->from($table);
		$schedule = $this->db->get()->row();

		$str = "(
			SELECT * FROM exam_results
			where schedule_request_id = ? and member_id = ?  
		) g0";
		$table = $this->f->compile_qry($str, [$schedule->schedule_request_id, $request->member->member_id]);
		if (! $exam_results = $this->db->from($table)->get()->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_exam_not_started')]];

		// Logic for calculate scoring
		list($success, $return) = $this->score($request->params->num_of_correct, $request->params->num_of_question);
		if (!$success) return [FALSE, $return];

		$score = $return['result'];

		if ($exam_results->status == '') {
				
			$result = $this->db->update('exam_results', 
				[
					'status' 		 => 'completed',
					'score'	 		 => $score.'/'.$request->params->num_of_question,
				],
				[
					'schedule_request_id' => $schedule->schedule_request_id, 
					'member_id' => $request->member->member_id, 
				]
			);
			if (!$result)
				return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		}

		$str = "(
			SELECT * FROM exam_logs
			where schedule_request_id = ? and member_id = ? and JSON_EXTRACT(state, '$.name') = 'finish_exam'
		) g0";
		$table = $this->f->compile_qry($str, [$schedule->schedule_request_id, $request->member->member_id]);
		if (! $exam_log = $this->db->from($table)->get()->row()) {
			$json_state = json_encode([
				'name' 				=> 'finish_exam',
				'activity' 		=> 'confirmation',
				'date_client' => $request->params->date_client,
				'time_client' => $request->params->time_client,
				'num_of_correct'  => $request->params->num_of_correct,
				'num_of_question' => $request->params->num_of_question,
				'score' 			=> $score,
				'longlat'			=> $request->params->coordinate,
			]);

			$result = $this->db->insert('exam_logs', [
				'schedule_request_id' => $schedule->schedule_request_id, 
				'member_id'  => $request->member->member_id, 
				'state' 		 => $json_state, 
				'ip_address' => 'mobile',
				'user_agent' => $request->agent,
				'coordinate' => $request->params->coordinate,
				'author' 		 => 0,
				'created_on' => strtotime(date('Y-m-d H:i:s')),
			]);
			if (!$result)
				return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

			return [TRUE, ['result' => [
				'date_client' 		=> $request->params->date_client, 
				'time_client' 		=> $request->params->time_client,
				'num_of_correct'  => $request->params->num_of_correct,
				'num_of_question' => $request->params->num_of_question,
				'score' 					=> $score,
			]]];

		} else {

			$state = json_decode($exam_log->state);
			return [TRUE, ['result' => [
				'date_client' 		=> $state->date_client, 
				'time_client' 		=> $state->time_client,
				'num_of_correct'  => $state->num_of_correct,
				'num_of_question' => $state->num_of_question,
				'score' 					=> $state->score,
			]]];

		}
	}

	function question_all($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['username','password']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->is_valid_auth($request);
		if (!$success) return [FALSE, $return];
    
		$str = '(
			select t1.member_id, t1.schedule_request_id, t3.category_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where member_id = ? limit 1
		) g0';
		$table = $this->f->compile_qry($str, [$request->member->member_id]);
		$this->db->from($table);
		$schedule = $this->db->get()->row();
		$str = '(
      select id, sts, module_id, question, answer_option_a, answer_option_b, answer_option_c, answer_option_d, option_ganda, answer_key, score from questions
			where module_id in (select module_id from category_modules where category_id = ?)
		) g0';
		$table = $this->f->compile_qry($str, [$schedule->category_id]);
		$this->db->from($table);
		return [TRUE, ['result' => $this->db->get()->result()]];
	}

	function check_score($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['username','password']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->is_valid_auth($request);
		if (!$success) return [FALSE, $return];
    
		$str = '(
			select t1.member_id, t1.schedule_request_id, t3.category_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where member_id = ? and t3.date = ? limit 1
		) g0';
		$table = $this->f->compile_qry($str, [$request->member->member_id, $request->params->date_client]);
		$this->db->from($table);
		$schedule = $this->db->get()->row();

		$str = "(
			SELECT * FROM exam_results
			where schedule_request_id = ? and member_id = ?
		) g0";
		$table = $this->f->compile_qry($str, [$schedule->schedule_request_id, $request->member->member_id]);
		if (! $exam_results = $this->db->from($table)->get()->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_exam_not_started')]];

		if ($exam_results->click_score >= 3)
			return [FALSE, ['message' => $this->f->_err_msg('err_check_score_had_reached')]];

		$result = $this->db->update('exam_results', 
			[
				'click_score' => $exam_results->click_score + 1,
			],
			[
				'schedule_request_id' => $schedule->schedule_request_id, 
				'member_id' => $request->member->member_id, 
			]
		);
		if (!$result)
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		return [TRUE, ['result' => ['score' => $exam_results->score]]];
	}

}