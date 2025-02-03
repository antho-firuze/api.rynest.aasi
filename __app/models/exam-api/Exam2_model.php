<?php if (!defined('BASEPATH')) {exit('No direct script access allowed');}

class Exam2_model extends CI_Model
{

	CONST NUM_OF_QUESTIONS = 70;
	CONST NUM_OF_REPEAT = 2;

	function __construct()
	{
		parent::__construct();
		$this->load->library(['f', 'lsp']);
		$this->load->database(DB_CONN[HTTP_HOST]);
	}
	
	function start($request)
	{
		list($success, $return) = $this->lsp->is_valid_token($request);
		if (!$success) return [FALSE, $return];
		
		if (!$this->f->check_param_required($request, ['date_client','time_client','coordinate']))
			return [FALSE, ['error' => $this->f->error()]];

		list($success, $return) = $this->lsp->get_user_member($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->is_member_on_schedule($request);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->get_exam_setting($request);
		if (!$success) return [FALSE, $return];

		return [TRUE, ['result' => $request]];

		// $minutes = $request->schedule->duration*60;
		// $exam_end = date('H:i:s', strtotime($request->schedule->begin) + $minutes);

		list($success, $return) = $this->lsp->is_exam_started($request);
		if (! isset($request->exam_result)) {
			$result = $this->db->insert('exam_results', [
				'schedule_request_id' => $request->schedule->schedule_request_id, 
				'category_id' 	=> $request->schedule->category_id, 
				'member_id' 		=> $request->member->member_id, 
				'begin' 				=> strtotime($request->params->date_client.' '.$request->params->time_client),
				'question_ids' 	=> '',
				'answer_keys'  	=> '',
				'sync_time' 	 	=> strtotime(date('Y-m-d H:i:s')),
				'sync_question' => 0,
				'score' 				=> '0/0',
				'status' 				=> '',
				'cek_score' 		=> $request->exam_setting->num_of_repeat,
				// 'cek_score' 		=> self::NUM_OF_REPEAT,
			]);
			if (!$result)
				return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

			$json_state = json_encode([
				'name' 				=> 'start_exam',
				'activity' 		=> 'confirmation',
				'date_client' => $request->params->date_client,
				'time_client' => $request->params->time_client,
				'exam_begin'	=> $request->params->time_client,
				'exam_end'		=> $request->schedule->exam_end,
				'duration'		=> $request->schedule->duration,
				'longlat'			=> $request->params->coordinate,
			]);

			$result = $this->db->insert('exam_logs', [
				'schedule_request_id' => $request->schedule->schedule_request_id, 
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

			$num_of_question = isset($request->params->num_of_question) 
												? $request->params->num_of_question 
												: $request->exam_setting->num_of_question;
												// : self::NUM_OF_QUESTIONS;

			return [TRUE, [
				'message' => $this->f->_msg('success_request_responded'),
				'result' => [
					'date_client' => $request->params->date_client, 
					'time_client' => $request->params->time_client,
					'exam_begin'	=> $request->params->time_client,
					'exam_end'		=> $request->schedule->exam_end,
					'exam_completed' => 0,
					'duration'		=> $request->schedule->duration,
					'num_of_correct'  => 0,
					'num_of_question' => $num_of_question,
					'num_answer_question' => 0,
					'num_remain_question' => $num_of_question,
					'score' 					=> 0,
					'num_of_repeat' 	=> $request->exam_setting->num_of_repeat,
					// 'num_of_repeat' 	=> self::NUM_OF_REPEAT,
				]
			]];

		} else {

			$this->lsp->process_q_a($request, $request->exam_result->question_ids, $request->exam_result->answer_keys);

			return [TRUE, [
				'message' => $this->f->_msg('success_request_responded'),
				'result' => [
					// 'date_client' => (new DateTime("@$exam_results->begin"))->format('Y-m-d'), 
					// 'time_client' => (new DateTime("@$exam_results->begin"))->format('H:i:s'),
					'date_client' => date_format($request->schedule->dt_client, "Y-m-d"), 
					'time_client' => date_format($request->schedule->dt_client, "H:i:s"), 
					// 'exam_begin'	=> $request->schedule->begin,
					'exam_end'		=> $request->exam_result->exam_end,
					'exam_completed' => ($request->exam_result->status == 'completed') ? 1 : 0,
					'duration'		=> $request->schedule->duration,
					'num_of_correct'  => $request->num_of_correct,
					'num_of_question' => $request->num_of_question,
					'num_answer_question' => $request->num_answer_question,
					'num_remain_question' => $request->num_remain_question,
					'score' 					=> $request->score,
					'num_of_repeat' 	=> $request->exam_result->cek_score,
				]
			]];
		} 

	}

	function answer($request)
	{
		list($success, $return) = $this->lsp->is_valid_token($request);
		if (!$success) return [FALSE, $return];
		
		// Buat nyimpan data lokasi & mulai start ujian apa? Table Exam_logs, Exam_results, Schedule participant
		list($success, $return) = $this->f->check_param_required($request, ['date_client','time_client','question_id','answer_key']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->get_user_member($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->is_member_on_schedule($request);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->is_exam_started($request);
		if (!$success) return [FALSE, $return];

		if ($request->exam_result->status != '')
			return [FALSE, ['message' => $this->f->_msg('err_exam_has_finished')]];

		$this->lsp->process_q_a($request, $request->exam_result->question_ids, $request->exam_result->answer_keys);
		if ($request->num_answer_question > $request->num_of_question)
			return [FALSE, ['message' => $this->f->_msg('err_num_of_question_reached')]];

		$result = $this->db->update('exam_results', 
			[
				'question_ids' => $request->question_ids,
				'answer_keys'  => $request->answer_keys,
				'score'	 		 	 => $request->score.'/'.$request->num_of_question,
				'sync_time'  	 => strtotime($request->params->date_client.' '.$request->params->time_client),
			],
			[
				'schedule_request_id' => $request->schedule->schedule_request_id, 
				'member_id' => $request->member->member_id, 
			]
		);
		if (!$result)
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'), 
			'result' => [
				'num_of_correct'  => $request->num_of_correct,
				'num_of_question' => $request->num_of_question,
				'num_answer_question' => $request->num_answer_question,
				'num_remain_question' => $request->num_remain_question,
				'score' 					=> $request->score,
			]
		]];
	}

	function repeat($request)
	{
		list($success, $return) = $this->lsp->is_valid_token($request);
		if (!$success) return [FALSE, $return];
		
		// Buat nyimpan data lokasi & mulai start ujian apa? Table Exam_logs, Exam_results, Schedule participant
		list($success, $return) = $this->f->check_param_required($request, ['date_client','time_client']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->get_user_member($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->is_member_on_schedule($request);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->is_exam_started($request);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->get_exam_setting($request);
		if (!$success) return [FALSE, $return];

		if ($request->exam_result->status != '')
			return [FALSE, ['message' => $this->f->_msg('err_exam_has_finished')]];

		$num_of_repeat = ($request->exam_result->cek_score ?? $request->exam_setting->num_of_repeat) - 1;
		// $num_of_repeat = ($request->exam_result->cek_score ?? self::NUM_OF_REPEAT) - 1;
		$result = $this->db->update('exam_results', 
			[
				'cek_score'	 	 => $num_of_repeat,
				'sync_time'  	 => strtotime($request->params->date_client.' '.$request->params->time_client),
			],
			[
				'schedule_request_id' => $request->schedule->schedule_request_id, 
				'member_id' => $request->member->member_id, 
			]
		);
		if (!$result)
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result' => [
				'date_client' => date_format($request->schedule->dt_client, "Y-m-d"), 
				'time_client' => date_format($request->schedule->dt_client, "H:i:s"), 
				'exam_end'		=> $request->exam_result->exam_end,
				'exam_completed' => ($request->exam_result->status == 'completed') ? 1 : 0,
				'duration'		=> $request->schedule->duration,
				'num_of_repeat' 	=> $num_of_repeat,
			]
		]];
	}

	function finish($request)
	{
		list($success, $return) = $this->lsp->is_valid_token($request);
		if (!$success) return [FALSE, $return];
		
		// Buat nyimpan data lokasi & mulai start ujian apa? Table Exam_logs, Exam_results, Schedule participant
		list($success, $return) = $this->f->check_param_required($request, ['date_client','time_client','coordinate']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->get_user_member($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->get_member_schedule($request);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->is_exam_started($request);
		if (!$success) return [FALSE, $return];

		$this->lsp->process_q_a($request, $request->exam_result->question_ids, $request->exam_result->answer_keys);

		if ($request->exam_result->status == '') {
				
			$result = $this->db->update('exam_results', 
				[
					'status' 		 => 'completed',
					'score'	 		 	 => $request->score.'/'.$request->num_of_question,
				],
				[
					'schedule_request_id' => $request->schedule->schedule_request_id, 
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
		$table = $this->f->compile_qry($str, [$request->schedule->schedule_request_id, $request->member->member_id]);
		if (! $exam_log = $this->db->from($table)->get()->row()) {
			$json_state = json_encode([
				'name' 				=> 'finish_exam',
				'activity' 		=> 'confirmation',
				'date_client' => $request->params->date_client,
				'time_client' => $request->params->time_client,
				'num_of_correct'  => $request->num_of_correct,
				'num_of_question' => $request->num_of_question,
				'num_answer_question' => $request->num_answer_question,
				'num_remain_question' => $request->num_remain_question,
				'score' 			=> $request->score,
				'longlat'			=> $request->params->coordinate,
			]);

			$result = $this->db->insert('exam_logs', [
				'schedule_request_id' => $request->schedule->schedule_request_id, 
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

			return [TRUE, [
				'message' => $this->f->_msg('success_request_responded'),
				'result' => [
					'date_client' 		=> $request->params->date_client, 
					'time_client' 		=> $request->params->time_client,
					'num_of_correct'  => $request->num_of_correct,
					'num_of_question' => $request->num_of_question,
					'num_answer_question' => $request->num_answer_question,
					'num_remain_question' => $request->num_remain_question,
					'score' 					=> $request->score,
				]
			]];

		} else {

			$state = json_decode($exam_log->state);
			return [TRUE, [
				'message' => $this->f->_msg('success_request_responded'),
				'result' => [
					'date_client' 		=> $state->date_client, 
					'time_client' 		=> $state->time_client,
					'num_of_correct'  => $state->num_of_correct,
					'num_of_question' => $state->num_of_question,
					'num_answer_question' => $state->num_answer_question,
					'num_remain_question' => $state->num_remain_question,
					'score' 					=> $state->score,
				]
			]];

		}
	}

	function result($request)
	{
		list($success, $return) = $this->lsp->is_valid_token($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->f->check_param_required($request, ['date_client','time_client']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->get_user_member($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->get_member_schedule($request);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->is_exam_started($request);
		if (!$success) return [FALSE, $return];

		$this->lsp->process_q_a($request, $request->exam_result->question_ids, $request->exam_result->answer_keys);

		if ($request->exam_result->status == '') 
			return [FALSE, ['message' => $this->f->_msg('err_exam_result')]];

		$str = "(
			SELECT * FROM exam_logs
			where schedule_request_id = ? and member_id = ? and JSON_EXTRACT(state, '$.name') = 'finish_exam'
		) g0";
		$table = $this->f->compile_qry($str, [$request->schedule->schedule_request_id, $request->member->member_id]);
		if (! $result = $this->db->from($table)->get())
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		if (! $exam_log = $result->row()) 
			return [FALSE, ['message' => $this->f->_msg('err_exam_result')]];

		$state = json_decode($exam_log->state);
		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result' => [
				'date_client' 		=> $state->date_client, 
				'time_client' 		=> $state->time_client,
				'num_of_correct'  => $state->num_of_correct,
				'num_of_question' => $state->num_of_question,
				'num_answer_question' => $state->num_answer_question,
				'num_remain_question' => $state->num_remain_question,
				'score' 					=> $state->score,
				'exam'						=> [
					'date'				=> date_format(date_create($request->exam_start->date_client), "d M Y"),
					'time'				=> date_format(date_create($request->exam_start->exam_begin), "H:i"),
					'longlat'			=> $request->exam_start->longlat,
				],
			]
		]];
	}

	function questions($request)
	{
		list($success, $return) = $this->lsp->is_valid_token($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->f->check_param_required($request, ['date_client','time_client']);
		if (!$success) return [FALSE, $return];
    
		list($success, $return) = $this->lsp->get_user_member($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->is_member_on_schedule($request);
		if (!$success) return [FALSE, $return];

		$this->lsp->get_answered_question($request);
		$question_ids = 0;
		if (isset($request->answered_question)) {
			if (count($request->answered_question) > 0)
				$question_ids = implode(',', $request->answered_question);
		}
		$str = '(
      select id, question, answer_option_a, answer_option_b, answer_option_c, answer_option_d from questions 
			where module_id in (select module_id from category_modules where category_id = ?) and id not in ('.$question_ids.') 
		) g0';
		$table = $this->f->compile_qry($str, [$request->schedule->category_id]);
		if (!$result = $this->db->from($table)->get())
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		$questions['total'] = $result->num_rows();
		$questions['rows'] 	= $result->result();

		// ============================ ANSWERED QUESTION ===============================
		$str = '(
      select id, question, answer_option_a, answer_option_b, answer_option_c, answer_option_d from questions 
			where module_id in (select module_id from category_modules where category_id = ?) and id in ('.$question_ids.') 
		) g0';
		$table = $this->f->compile_qry($str, [$request->schedule->category_id]);
		if (!$result = $this->db->from($table)->get())
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		$mod_result = [];
		foreach($result->result() as $key => $val) {
			$mod_result[$key] = $val;
			$mod_result[$key]->answer = $request->answered_keys[$val->id];
		}
		$answered_questions['total'] = $result->num_rows();
		$answered_questions['rows']  = $mod_result;

		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result' => [
				'answered_questions'	=> $answered_questions,
				'questions'	=> $questions,
			],
		]];
	}

	/*
	function question_all($request)
	{
		list($success, $return) = $this->lsp->is_valid_token($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->f->check_param_required($request, ['date_client','time_client']);
		if (!$success) return [FALSE, $return];
    
		list($success, $return) = $this->lsp->get_user_member($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->is_member_on_schedule($request);
		if (!$success) return [FALSE, $return];

		$this->lsp->get_answered_question($request);
		$question_ids = 0;
		if (isset($request->answered_question)) {
			if (count($request->answered_question) > 0)
				$question_ids = implode(',', $request->answered_question);
		}
		$str = '(
      select id, question, answer_option_a, answer_option_b, answer_option_c, answer_option_d from questions 
			where module_id in (select module_id from category_modules where category_id = ?) and id not in ('.$question_ids.') 
		) g0';
		$table = $this->f->compile_qry($str, [$request->schedule->category_id]);
		if (!$result = $this->db->from($table)->get())
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		return [TRUE, ['rows' => $result->num_rows(), 'result' => $result->result()]];
	}*/

	/*
	function answered_question($request)
	{
		list($success, $return) = $this->lsp->is_valid_token($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->f->check_param_required($request, ['date_client','time_client']);
		if (!$success) return [FALSE, $return];
    
		list($success, $return) = $this->lsp->get_user_member($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->is_member_on_schedule($request);
		if (!$success) return [FALSE, $return];

		$this->lsp->get_answered_question($request);
		$question_ids = 0;
		if (isset($request->answered_question)) {
			if (count($request->answered_question) > 0)
				$question_ids = implode(',', $request->answered_question);
		}
		$str = '(
      select id, question, answer_option_a, answer_option_b, answer_option_c, answer_option_d from questions 
			where module_id in (select module_id from category_modules where category_id = ?) and id in ('.$question_ids.') 
		) g0';
		$table = $this->f->compile_qry($str, [$request->schedule->category_id]);
		if (!$result = $this->db->from($table)->get())
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		$mod_result = [];
		foreach($result->result() as $key => $val) {
			$mod_result[$key] = $val;
			$mod_result[$key]->answer = $request->answered_keys[$val->id];
		}

		return [TRUE, ['rows' => $result->num_rows(), 'result' => $mod_result]];
	}*/

	/*
	function check_score($request)
	{
		list($success, $return) = $this->lsp->is_valid_token($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->f->check_param_required($request, ['date_client','time_client']);
		if (!$success) return [FALSE, $return];
    
		list($success, $return) = $this->lsp->get_user_member($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->is_member_on_schedule($request);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->is_exam_started($request);
		if (!$success) return [FALSE, $return];

		if ($request->exam_result->click_score >= 3)
			return [FALSE, ['message' => $this->f->_msg('err_check_score_had_reached')]];

		$result = $this->db->update('exam_results', 
			[
				'click_score' => $request->exam_result->click_score + 1,
			],
			[
				'schedule_request_id' => $request->schedule->schedule_request_id, 
				'member_id' => $request->member->member_id, 
			]
		);
		if (!$result)
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		return [TRUE, ['result' => [
			'score' => $request->exam_result->score
		]]];
	}*/

}