<?php if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

class Exam3_model extends CI_Model
{

	function __construct()
	{
		parent::__construct();
		$this->load->library(['f', 'aasi']);
		$this->load->database(DB_CONN[HTTP_HOST]);
	}

	function start($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['date_client', 'time_client']))
			return [FALSE, ['error' => $this->f->error()]];

		$request->params->coordinate = '';

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->aasi->get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// GET exam results
		list($return, $result) = $this->aasi->get_exam_results($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_results = $result;

		if ($exam_results == NULL) {
			// GET exam setting
			list($return, $result) = $this->aasi->get_exam_setting();
			if (!$return) return [FALSE, ['error' => $result]];

			$exam_setting = $result;
			$num_of_question = isset($request->params->num_of_question)
				? $request->params->num_of_question
				: $exam_setting->num_of_question;

			// CHECK is member on schedule
			list($return, $result) = $this->aasi->set_exam_start($request, $schedule);
			if (!$return) return [FALSE, ['error' => $result]];

			$schedule = $result;

			if (FALSE === $result = $this->db->insert('exam_results', [
				'schedule_request_id' => $schedule->schedule_request_id,
				'category_id' 	=> $schedule->category_id,
				'id_member' 		=> $member->id,
				'member_id' 		=> $member->member_id,
				'begin' 				=> $schedule->exam_start_epoc,
				'question_ids' 	=> '',
				'answer_keys'  	=> '',
				'sync_time' 	 	=> strtotime(date('Y-m-d H:i:s')),
				'sync_question' => 0,
				'score' 				=> '0/0',
				'status' 				=> '',
				'click_score' 	=> $exam_setting->num_of_repeat,
			]))
				return [FALSE, ['error' => $this->db->error()]];

			$state = [
				'name' 					=> 'start_exam',
				'activity' 			=> 'confirmation',
				'exam_start'		=> $schedule->exam_start,
				'exam_end'			=> $schedule->exam_end,
				'duration'			=> $schedule->duration,
				'exam_completed' 	=> false,
				'longlat'				=> $request->params->coordinate,
				'city'					=> isset($request->params->city) ? $request->params->city : '',
				'num_of_correct'  			=> 0,
				'num_of_question'				=> $num_of_question,
				'num_answered_question' => 0,
				'num_remain_question' 	=> $num_of_question,
				'num_of_repeat' 				=> $exam_setting->num_of_repeat,
				'score' 								=> 0,
				'min_of_answer' 				=> $exam_setting->min_of_answer,
			];

			if (FALSE === $result = $this->db->insert('exam_logs', [
				'schedule_request_id' => $schedule->schedule_request_id,
				'id_member' 		=> $member->id,
				'member_id'  		=> $member->member_id,
				'state' 		 		=> json_encode($state),
				'ip_address' 		=> 'mobile',
				'user_agent' 		=> $request->agent,
				'coordinate' 		=> $request->params->coordinate,
				'author' 		 		=> 0,
				'created_on' 		=> strtotime(date('Y-m-d H:i:s')),
			]))
				return [FALSE, ['error' => $this->db->error()]];

			$data = $state;

			return [TRUE, [
				'message' => $this->f->_msg('success_request_responded'),
				'result' => $data,
			]];
		} else {

			// GET exam start logs
			list($return, $result) = $this->aasi->get_exam_start_logs($schedule->schedule_request_id, $member->id, $member->member_id);
			if (!$return) return [FALSE, ['error' => $result]];

			$exam_logs = $result;
			$state = json_decode($exam_logs->state);

			$state->exam_completed = ($exam_results->status == 'completed');
			$data = $state;

			return [TRUE, [
				'message' => $this->f->_msg('success_request_responded'),
				'result' => $data,
			]];
		}
	}

	function answer($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['date_client', 'time_client', 'question_id', 'answered_key']))
			return [FALSE, ['error' => $this->f->error()]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->aasi->get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// CHECK exam still running
		list($return, $result) = $this->aasi->is_exam_still_running($request, $schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$state = $result;

		// GET exam results
		list($return, $result) = $this->aasi->get_exam_results($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_results = $result;
		if ($exam_results == NULL)
			return [FALSE, ['error' => $this->f->_msg_code('err_exam_not_started')]];
		if ($exam_results->status != '')
			return [FALSE, ['error' => $this->f->_msg_code('err_exam_has_finished')]];

		// PROCESSING question & answer
		$question_id = $request->params->question_id;
		$answer_key = $request->params->answered_key;
		$question_ids = $exam_results->question_ids;
		$answer_keys = $exam_results->answer_keys;
		$result_calc = $this->aasi->process_q_a($question_id, $answer_key, $question_ids, $answer_keys);
		if ($result_calc->num_answer_question > $state->num_of_question)
			return [FALSE, ['error' => $this->f->_msg_code('err_num_of_question_reached')]];

		// PROCESSING remaining questions & score
		$result_calc->num_remain_question = $state->num_of_question - $result_calc->num_answer_question;
		$result_calc->score = $result_calc->score * (100 / $state->num_of_question);

		// UPDATE table exam_results
		if (FALSE === $result = $this->db->update(
			'exam_results',
			[
				'question_ids' => $result_calc->question_ids,
				'answer_keys'  => $result_calc->answer_keys,
				'score'	 		 	 => $result_calc->score . '/' . $request->num_of_question,
				'sync_time'  	 => strtotime($request->params->date_client . ' ' . $request->params->time_client),
			],
			[
				'schedule_request_id' => $schedule->schedule_request_id,
				'id_member' => $member->id,
				'member_id' => $member->member_id,
			]
		))
			return [FALSE, ['error' => $this->db->error()]];

		// UPDATE table exam_logs
		$state->num_of_correct = $result_calc->num_of_correct;
		$state->num_answered_question = $result_calc->num_answer_question;
		$state->num_remain_question = $result_calc->num_remain_question;
		$state->score = $result_calc->score;
		if (FALSE === $result = $this->db->update(
			'exam_logs',
			[
				'state' 		=> json_encode($state),
			],
			[
				'schedule_request_id' => $schedule->schedule_request_id,
				'id_member' => $member->id,
				'member_id' => $member->member_id,
			]
		))
			return [FALSE, ['error' => $this->db->error()]];

		$state->exam_completed = ($exam_results->status == 'completed');
		$data = $state;
		
		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result' => $data,
		]];
	}

	function repeat($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['date_client', 'time_client']))
			return [FALSE, ['error' => $this->f->error()]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->aasi->get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// CHECK exam still running
		list($return, $result) = $this->aasi->is_exam_still_running($request, $schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		// GET exam results
		list($return, $result) = $this->aasi->get_exam_results($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_results = $result;
		if ($exam_results == NULL)
			return [FALSE, ['error' => $this->f->_msg_code('err_exam_not_started')]];
		if ($exam_results->status != '')
			return [FALSE, ['error' => $this->f->_msg_code('err_exam_has_finished')]];
		if ($exam_results->click_score < 1)
			return [FALSE, ['error' => $this->f->_msg_code('err_repetition_had_reached')]];

		// GET exam start logs
		list($return, $result) = $this->aasi->get_exam_start_logs($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_logs = $result;
		$state = json_decode($exam_logs->state);

		$num_of_repeat = $exam_results->click_score - 1;
		if (FALSE === $result = $this->db->update(
			'exam_results',
			[
				'click_score'	=> $num_of_repeat,
				'sync_time' 	=> strtotime($request->params->date_client . ' ' . $request->params->time_client),
			],
			[
				'schedule_request_id' => $schedule->schedule_request_id,
				'id_member' 	=> $member->id,
				'member_id' 	=> $member->member_id,
			]
		))
			return [FALSE, ['error' => $this->db->error()]];

		// UPDATE table exam_logs
		$state->num_of_repeat = $num_of_repeat;
		if (FALSE === $result = $this->db->update(
			'exam_logs',
			['state' => json_encode($state)],
			[
				'schedule_request_id' => $schedule->schedule_request_id,
				'id_member' => $member->id,
				'member_id' 	=> $member->member_id,
			]
		))
			return [FALSE, ['error' => $this->db->error()]];

		$state->exam_completed = ($exam_results->status == 'completed');
		$data = $state;
		
		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result' => $data,
		]];
	}

	function finish($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['date_client', 'time_client']))
			return [FALSE, ['error' => $this->f->error()]];

		$request->params->coordinate = '';

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->aasi->get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// GET exam finish logs
		list($return, $result) = $this->aasi->get_exam_finish_logs($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_finish_logs = $result;
		if ($exam_finish_logs == NULL) {

			// GET exam results
			list($return, $result) = $this->aasi->get_exam_results($schedule->schedule_request_id, $member->id, $member->member_id);
			if (!$return) return [FALSE, ['error' => $result]];

			$exam_results = $result;
			if ($exam_results == NULL)
				return [FALSE, ['error' => $this->f->_msg_code('err_exam_not_started')]];

			list($return, $result) = $this->aasi->set_exam_finish($request, $schedule->schedule_request_id, $member->id, $member->member_id);
			if (!$return) return [FALSE, ['error' => $result]];

			return [
				TRUE, [
					'message' => $this->f->_msg('success_request_responded'),
					'result' => $result
				]
			];
		} else {

			$state = json_decode($exam_finish_logs->state);
			return [
				TRUE, [
					'message' => $this->f->_msg('success_request_responded'),
					'result' => $state
				]
			];
		}
	}

	function status($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['date_client', 'time_client']))
			return [FALSE, ['error' => $this->f->error()]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->aasi->get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// CHECK exam still running
		list($return, $result) = $this->aasi->get_exam_logs($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$state = $result;

		// CHECK exam time expired
		if ($state != null) {
			$date_client = date_create($request->params->date_client . ' ' . $request->params->time_client);
			$exam_end = date_create($state->exam_end);
			if ($date_client >= $exam_end) {
				list($return, $result) = $this->aasi->set_exam_finish($request, $schedule->schedule_request_id, $member->id, $member->member_id);
				if (!$return) return [FALSE, ['error' => $result]];

				$state = $result;
			}
		}

		$data = $state;

		return [
			TRUE, [
				'message' => $this->f->_msg('success_request_responded'),
				'result'	=> $data,
			]
		];
	}

	function result($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['date_client', 'time_client']))
			return [FALSE, ['error' => $this->f->error()]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->aasi->get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// GET exam results
		list($return, $result) = $this->aasi->get_exam_results($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_results = $result;
		if ($exam_results == NULL)
			return [FALSE, ['error' => $this->f->_msg_code('err_exam_not_started')]];

		// CHECK exam still running
		list($return, $result) = $this->aasi->get_exam_logs($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$state = $result;

		// CHECK exam time expired
		if ($state != null) {
			$date_client = date_create($request->params->date_client . ' ' . $request->params->time_client);
			$exam_end = date_create($state->exam_end);
			if ($date_client >= $exam_end) {
				list($return, $result) = $this->aasi->set_exam_finish($request, $schedule->schedule_request_id, $member->id, $member->member_id);
				if (!$return) return [FALSE, ['error' => $result]];

				$state = $result;
			}
		}

		$data = $state;
		
		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result' => $data,
		]];
	}

	function questions($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['date_client', 'time_client']))
			return [FALSE, ['error' => $this->f->error()]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->aasi->get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// CHECK exam still running
		list($return, $result) = $this->aasi->is_exam_still_running($request, $schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$state = $result;

		// GET exam results
		list($return, $result) = $this->aasi->get_exam_results($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_results = $result;

		// EXTRACT from database
		$question_ids = 0;
		$answered_keys = [];
		if ($exam_results != NULL) {
			if ($exam_results->question_ids != '') {
				$arr_x = [];
				$arr_q = explode(',', $exam_results->question_ids);
				$arr_a = explode(',', $exam_results->answer_keys);
				foreach ($arr_q as $k => $v) {
					$arr_x[substr($v, 0, strlen($v) - 4)] =  $arr_a[$k];
				}
				$question_ids = implode(',', array_keys($arr_x));
				$answered_keys = $arr_x;
			}
		}

		// GET question has not answered
		$str = '(
			select id, question, answer_option_a, answer_option_b, answer_option_c, answer_option_d 
			from questions 
			where module_id in (select module_id from category_modules where category_id = ?) and id not in (' . $question_ids . ') 
		) g0';
		$table = $this->f->compile_qry($str, [$schedule->category_id]);
		if (FALSE === $rows = $this->db->from($table)->get()->result())
			return [FALSE, ['error' => $this->db->error()]];

		$tmp_array = [];
		foreach ($rows as $key => $val) {
			$tmp_array[$key] = $val;
			$tmp_array[$key]->answered_key = NULL;
		}
		shuffle($tmp_array);
		$questions['rows'] 	= $tmp_array;

		// GET question has answered
		$str = '(
      select id, question, answer_option_a, answer_option_b, answer_option_c, answer_option_d from questions 
			where module_id in (select module_id from category_modules where category_id = ?) and id in (' . $question_ids . ') 
		) g0';
		$table = $this->f->compile_qry($str, [$schedule->category_id]);
		if (FALSE === $rows = $this->db->from($table)->get()->result())
			return [FALSE, ['error' => $this->db->error()]];

		$tmp_array = [];
		foreach ($rows as $key => $val) {
			$tmp_array[$key] = $val;
			$tmp_array[$key]->answered_key = $answered_keys[$val->id];
		}
		$answered_questions['rows']  = $tmp_array;

		$result = array_merge($answered_questions['rows'], $questions['rows']);
		$result = array_slice($result, 0, $state->num_of_question);
		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result' => $result,
		]];
	}

	function schedule($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->aasi->get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		$data = $schedule;

		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result'	=> $data,
		]];
	}
	
	function photos($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->aasi->get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// GET member photo base on schedule_request_id
		list($return, $result) = $this->aasi->get_member_photo($member->id, $member->member_id, $member->identity_card, $schedule->schedule_request_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$photos = $result;

		$data = $photos;

		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result'	=> $data,
		]];
	}
}
