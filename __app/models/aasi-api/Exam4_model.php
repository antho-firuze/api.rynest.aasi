<?php if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

class Exam4_model extends CI_Model
{

	function __construct()
	{
		parent::__construct();
		$this->load->library(['f', 'aasi']);
		$this->load->database(DB_CONN[HTTP_HOST]);
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
		list($return, $result) = $this->_get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// GET exam category
		list($return, $result) = $this->_get_exam_category($schedule->category_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$category = $result;
		
		// GET exam location
		list($return, $result) = $this->_get_exam_location($schedule->location_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$location = $result;
		
		$data = $schedule;
		$data->category = $category;
		$data->location = $location;

		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result'	=> $data,
		]];
	}

	function status($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['datetime']))
			return [FALSE, ['error' => $this->f->error()]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->_get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// GET exam results
		list($return, $result) = $this->_get_exam_results($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_results = $result;
		
		// CHECK exam still running
		list($return, $result) = $this->_get_exam_logs($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$state = $result;

		// CHECK exam time expired
		if ($state != null) {
			$date_client = date_create($request->params->datetime);
			$exam_end = date_create($state->exam_end);
			if ($date_client >= $exam_end) {
				list($return, $result) = $this->_set_exam_finish($request, $schedule->schedule_request_id, $member->id, $member->member_id);
				if (!$return) return [FALSE, ['error' => $result]];

				$state = $result;
			}
		}

		// GET Questions
		list($return, $result) = $this->_get_question_ids($schedule->category_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$question = $result;

		// $data = $state;
		$result = (object) [];
		$result->exam_start = $state->exam_start;
		$result->exam_end = $state->exam_end;
		$result->duration = $schedule->duration;
		$result->exam_completed = ($exam_results->status == 'completed');
		$result->num_of_question = $state->num_of_question;
		$result->num_answered_question = $state->num_answered_question;
		$result->num_of_correct = $state->num_of_correct;
		$score = explode('/', $exam_results->score);
		$result->score = $score[0];
		$result->passed_grade = $question->passed_grade;

		return [
			TRUE, [
				'message' => $this->f->_msg('success_request_responded'),
				'result'	=> $result,
			]
		];
	}
	
	function start($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['datetime']))
			return [FALSE, ['error' => $this->f->error()]];

		$request->params->coordinate = '';

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->_get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// GET exam results
		list($return, $result) = $this->_get_exam_results($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_results = $result;

		if ($exam_results == NULL) {
			// GET exam setting
			list($return, $result) = $this->_get_exam_setting();
			if (!$return) return [FALSE, ['error' => $result]];

			$exam_setting = $result;
			$num_of_question = isset($request->params->num_of_question)
				? $request->params->num_of_question
				: $exam_setting->num_of_question;

			// CHECK is member on schedule
			list($return, $result) = $this->_set_exam_start($request->params->datetime, $schedule);
			if (!$return) return [FALSE, ['error' => $result]];

			$schedule = $result;

			// GET Questions
			list($return, $result) = $this->_get_question_ids($schedule->category_id);
			if (!$return) return [FALSE, ['error' => $result]];

			$question = $result;

			// return [FALSE, ['error' => $question->question_ids]];

			if (FALSE === $result = $this->db->insert('exam_results', [
				'schedule_request_id' => $schedule->schedule_request_id,
				'category_id' 		=> $schedule->category_id,
				'id_member' 		=> $member->id,
				'member_id' 		=> $member->member_id,
				'begin' 			=> $schedule->exam_start_epoc,
				'question_ids' 		=> $question->question_ids,
				'answer_keys'  		=> $question->answer_keys,
				'sync_time' 	 	=> strtotime(date('Y-m-d H:i:s')),
				'sync_question' 	=> 0,
				'score' 			=> '0/0',
				'status' 			=> '',
				'click_score' 		=> $exam_setting->num_of_repeat,
			]))
				return [FALSE, ['error' => $this->db->error()]];

			$state = [
				'name' 					=> 'start_exam',
				'activity' 				=> 'confirmation',
				'exam_start'			=> $schedule->exam_start,
				'exam_end'				=> $schedule->exam_end,
				'duration'				=> $schedule->duration,
				'exam_completed' 		=> false,
				'longlat'				=> $request->params->coordinate,
				'city'					=> isset($request->params->city) ? $request->params->city : '',
				'num_of_repeat' 		=> $exam_setting->num_of_repeat,
				'num_of_correct'  		=> 0,
				'num_answered_question' => 0,
				'num_of_question'		=> $question->questions,
				// 'num_remain_question' 	=> $num_of_question,
				// 'min_of_answer' 		=> $question->passed_grade,
				'score' 				=> 0,
				'passed_grade' 			=> $question->passed_grade,
			];

			if (FALSE === $result = $this->db->insert('exam_logs', [
				'schedule_request_id' => $schedule->schedule_request_id,
				'id_member' 		=> $member->id,
				'member_id'  		=> $member->member_id,
				'state' 		 	=> json_encode($state),
				'ip_address' 		=> 'mobile',
				'user_agent' 		=> $request->agent,
				'coordinate' 		=> $request->params->coordinate,
				'author' 		 	=> 0,
				'created_on' 		=> strtotime(date('Y-m-d H:i:s')),
			]))
				return [FALSE, ['error' => $this->db->error()]];

			$state['question_ids'] 	= $question->question_ids;
			$state['answer_keys'] 	= $question->answer_keys;
			$state['sync_question'] = 0;
			$data = $state;

			return [TRUE, [
				'message' => $this->f->_msg('success_request_responded'),
				'result' => $data,
			]];
		} else {

			// GET exam start logs
			list($return, $result) = $this->_get_exam_start_logs($schedule->schedule_request_id, $member->id, $member->member_id);
			if (!$return) return [FALSE, ['error' => $result]];

			$exam_logs = $result;
			$state = json_decode($exam_logs->state);

			$state->exam_completed = ($exam_results->status == 'completed');
			$state->question_ids = $exam_results->question_ids;
			$state->answer_keys = $exam_results->answer_keys;
			$state->sync_question = $exam_results->sync_question;
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

		if (!$this->f->check_param_required($request, ['datetime', 'question_id', 'answered_key']))
			return [FALSE, ['error' => $this->f->error()]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->_get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// CHECK exam still running
		list($return, $result) = $this->_is_exam_still_running($request, $schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$state = $result;

		// GET exam results
		list($return, $result) = $this->_get_exam_results($schedule->schedule_request_id, $member->id, $member->member_id);
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
		$result_calc = $this->_process_q_a($question_id, $answer_key, $question_ids, $answer_keys);
		if ($result_calc->num_answer_question > $state->num_of_question)
			return [FALSE, ['error' => $this->f->_msg_code('err_num_of_question_reached')]];

		// PROCESSING remaining questions & score
		$result_calc->num_remain_question = $state->num_of_question - $result_calc->num_answer_question;
		$result_calc->score = $result_calc->score * (100 / $state->num_of_question);

		// UPDATE table exam_results
		if (FALSE === $result = $this->db->update(
			'exam_results',
			[
				'question_ids' 	=> $result_calc->question_ids,
				'answer_keys'  	=> $result_calc->answer_keys,
				'sync_time'  	=> strtotime($request->params->datetime),
				'score'	 		=> $result_calc->score . '/' . $state->num_of_question,
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
		$state->num_answered_question = $result_calc->num_answered_question;
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

		$result = (object) [];
		$result->num_of_correct = $state->num_of_correct;
		$result->num_answered_question = $state->num_answered_question;
		$result->answer_keys = $result_calc->answer_keys;
		$result->score = $result_calc->score;
		
		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result' => $result,
		]];
	}

	/**
	 * Function for processing question & answer
	 *
	 * @param object $request
	 * @param string $question_ids
	 * @param string $answer_keys
	 * @return void
	 */
	function _process_q_a($question_id, $answer_key, $question_ids, $answer_keys)	// repaired
	{
		$result = (object)[];

		// EXTRACT from database
		$arr_x = [];
		if ($question_ids != '') {
			$arr_q = explode(',', $question_ids);
			$arr_a = explode(',', $answer_keys);
			foreach ($arr_q as $k => $v) {
				$arr_x[substr($v, 0, strlen($v) - 4)] = ['abcd' => substr($v, -4, 4), 'key' => $arr_a[$k]];
			}
		}

		// EXTRACT answer from request
		if (isset($question_id)) {
			$id 	= substr($question_id, 0, strlen($question_id) - 4);
			$abcd = strtoupper(substr($question_id, -4, 4));
			$key 	= $answer_key ? strtoupper($answer_key) : 0;

			// PAIRING with data from database
			$arr_x[$id] = ['abcd' => $abcd, 'key' => $key];
		}

		// SEPARATED question & answer
		$arr_q = [];
		$arr_a = [];
		foreach ($arr_x as $k => $v) {
			$arr_q[] = $k . $v['abcd'];
			$arr_a[] = $v['key'];
		}

		$result->question_ids = implode(',', $arr_q);
		$result->answer_keys  = implode(',', $arr_a);
		$result->num_answered_question = count($arr_q) - array_count_values($arr_a)['X'];

		return self::_score_calculation($result, $arr_x);
	}

	function _score_calculation($data, $arr_q = [])
	{
		$score = 0;
		$num_of_correct = 0;
		foreach ($arr_q as $key => $value) {
			if ($row = $this->db->select('score')
				->from('questions')
				->where('id', $key)
				->where('answer_key', $value['key'])
				->get()
				->row()
			) {
				$score += $row->score;
				$num_of_correct += 1;
			}
		}

		$data->score = $score;
		$data->num_of_correct = $num_of_correct;
		// $data->score = $score * (100/$request->num_of_question);
		// $request->num_remain_question = $request->num_of_question - $request->num_answer_question;
		return $data;
	}

	function check_score($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['datetime']))
			return [FALSE, ['error' => $this->f->error()]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->_get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// CHECK exam still running
		list($return, $result) = $this->_is_exam_still_running($request, $schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		// GET exam results
		list($return, $result) = $this->_get_exam_results($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_results = $result;
		if ($exam_results == NULL)
			return [FALSE, ['error' => $this->f->_msg_code('err_exam_not_started')]];
		if ($exam_results->status != '')
			return [FALSE, ['error' => $this->f->_msg_code('err_exam_has_finished')]];
		if ($exam_results->click_score < 1)
			return [FALSE, ['error' => $this->f->_msg_code('err_repetition_had_reached')]];

		// GET exam start logs
		list($return, $result) = $this->_get_exam_start_logs($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_logs = $result;
		$state = json_decode($exam_logs->state);

		$num_of_repeat = $exam_results->click_score - 1;
		if (FALSE === $result = $this->db->update(
			'exam_results',
			[
				'click_score'	=> $num_of_repeat,
				'sync_time' 	=> strtotime($request->params->datetime),
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

		$result = (object) [];
		$result->num_of_repeat = $num_of_repeat;
		
		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result' => $result,
		]];
	}

	function finish($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['datetime']))
			return [FALSE, ['error' => $this->f->error()]];

		$request->params->coordinate = '';

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->_get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// GET exam finish logs
		list($return, $result) = $this->_get_exam_finish_logs($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_finish_logs = $result;
		if ($exam_finish_logs == NULL) {

			// GET exam results
			list($return, $result) = $this->_get_exam_results($schedule->schedule_request_id, $member->id, $member->member_id);
			if (!$return) return [FALSE, ['error' => $result]];

			$exam_results = $result;
			if ($exam_results == NULL)
				return [FALSE, ['error' => $this->f->_msg_code('err_exam_not_started')]];

			list($return, $result) = $this->_set_exam_finish($request, $schedule->schedule_request_id, $member->id, $member->member_id);
			if (!$return) return [FALSE, ['error' => $result]];

			$result = (object) [];
			$result->exam_completed = true;

			return [
				TRUE, [
					'message' => $this->f->_msg('success_request_responded'),
					'result' => $result
				]
			];
		} else {
			$result = (object) [];
			$result->exam_completed = true;
			return [
				TRUE, [
					'message' => $this->f->_msg('success_request_responded'),
					'result' => $result
				]
			];
		}
	}

	function result($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['datetime']))
			return [FALSE, ['error' => $this->f->error()]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->_get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// GET exam results
		list($return, $result) = $this->_get_exam_results($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_results = $result;
		if ($exam_results == NULL)
			return [FALSE, ['error' => $this->f->_msg_code('err_exam_not_started')]];

		// CHECK exam still running
		list($return, $result) = $this->_get_exam_logs($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$state = $result;

		// CHECK exam time expired
		if ($state != null) {
			$date_client = date_create($request->params->datetime);
			$exam_end = date_create($state->exam_end);
			if ($date_client >= $exam_end) {
				list($return, $result) = $this->_set_exam_finish($request, $schedule->schedule_request_id, $member->id, $member->member_id);
				if (!$return) return [FALSE, ['error' => $result]];

				$state = $result;
			}
		}

		// GET Questions
		list($return, $result) = $this->_get_question_ids($schedule->category_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$question = $result;

		// $data = $state;
		$result = (object) [];
		$result->exam_start = $state->exam_start;
		$result->exam_end = $state->exam_end;
		$result->duration = $schedule->duration;
		$result->exam_completed = ($exam_results->status == 'completed');
		$result->num_of_question = $state->num_of_question;
		$result->num_answered_question = $state->num_answered_question;
		$result->num_of_correct = $state->num_of_correct;
		$score = explode('/', $exam_results->score);
		$result->score = $score[0];
		$result->passed_grade = $question->passed_grade;
		
		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result' => $result,
		]];
	}

	function question($request) 
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['question_id']))
			return [FALSE, ['error' => $this->f->error()]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->_get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// GET exam results
		list($return, $result) = $this->_get_exam_results($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_results = $result;

		$question_id = $request->params->question_id;
		$question_ids = $exam_results->question_ids;
		$answer_keys = $exam_results->answer_keys;
		list($return, $result) = $this->_get_question_byId($question_id, $question_ids, $answer_keys);
		if (!$return) return [FALSE, ['error' => $result]];

		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result' => $result,
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
		list($return, $result) = $this->_get_member_schedule($member->id, $member->company_id);
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

	function upload_photo_exam_start($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->_get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// Create filename without extension
		$type = "exam_start";
		$srid = "srid_$schedule->schedule_request_id";
		$filename = "$member->identity_card-$srid-$type";

		// GO Upload to CDN
		list($return, $result) = $this->_upload_to_cdn($filename, 'members', $member->identity_card);
		if (!$return) return [FALSE, ['error' => $result]];

		$linkUrl = $result->link;
		$filename = $result->file;

		// SAVE TO DATABASE
		if (FALSE === $row = $this->db->get_where('participant_photo', [
			'schedule_request_id' => $schedule->schedule_request_id,
			'id_member'  					=> $member->id,
			'member_id'  					=> $member->member_id,
			'type'               	=> $type,
		])->row())
			return [FALSE, $this->db->error()];

		if (!$row) {
			if (FALSE === $result = $this->db->insert('participant_photo', [
				'schedule_request_id' => $schedule->schedule_request_id,
				'id_member'  					=> $member->id,
				'member_id'  					=> $member->member_id,
				'type'               	=> $type,
				'filename'      			=> $filename,
				'created_at'					=> date('Y-m-d H:i:s'),
			]))
				return [FALSE, $this->db->error()];
		} else {
			if (FALSE === $result = $this->db->update('participant_photo', [
				'filename'      			=> $filename,
				'updated_at'					=> date('Y-m-d H:i:s'),
			], [
				'schedule_request_id' => $schedule->schedule_request_id,
				'id_member'  					=> $member->id,
				'member_id'  					=> $member->member_id,
				'type'               	=> $type,
			]))
				return [FALSE, $this->db->error()];
		}

		return [TRUE, [
			'message' => $this->f->_msg('success_photo_upload'),
			'result'	=> $linkUrl,
		]];
	}

	function upload_photo_exam_random1($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->_get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// Create filename without extension
		$type = "exam_rnd-1";
		$srid = "srid_$schedule->schedule_request_id";
		$filename = "$member->identity_card-$srid-$type";

		// GO Upload to CDN
		list($return, $result) = $this->_upload_to_cdn($filename, 'members', $member->identity_card);
		if (!$return) return [FALSE, ['error' => $result]];

		$linkUrl = $result->link;
		$filename = $result->file;

		// SAVE TO DATABASE
		if (FALSE === $row = $this->db->get_where('participant_photo', [
			'schedule_request_id' => $schedule->schedule_request_id,
			'id_member'  					=> $member->id,
			'member_id'  					=> $member->member_id,
			'type'               	=> $type,
		])->row())
			return [FALSE, $this->db->error()];

		if (!$row) {
			if (FALSE === $result = $this->db->insert('participant_photo', [
				'schedule_request_id' => $schedule->schedule_request_id,
				'id_member'  					=> $member->id,
				'member_id'  					=> $member->member_id,
				'type'               	=> $type,
				'filename'      			=> $filename,
				'created_at'					=> date('Y-m-d H:i:s'),
			]))
				return [FALSE, $this->db->error()];
		} else {
			if (FALSE === $result = $this->db->update('participant_photo', [
				'filename'      			=> $filename,
				'updated_at'					=> date('Y-m-d H:i:s'),
			], [
				'schedule_request_id' => $schedule->schedule_request_id,
				'id_member'  					=> $member->id,
				'member_id'  					=> $member->member_id,
				'type'               	=> $type,
			]))
				return [FALSE, $this->db->error()];
		}

		return [TRUE, [
			'message' => $this->f->_msg('success_photo_upload'),
			'result'	=> $linkUrl,
		]];
	}

	function upload_photo_exam_random2($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->_get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// Create filename without extension
		$type = "exam_rnd-2";
		$srid = "srid_$schedule->schedule_request_id";
		$filename = "$member->identity_card-$srid-$type";

		// GO Upload to CDN
		list($return, $result) = $this->_upload_to_cdn($filename, 'members', $member->identity_card);
		if (!$return) return [FALSE, ['error' => $result]];

		$linkUrl = $result->link;
		$filename = $result->file;

		// SAVE TO DATABASE
		if (FALSE === $row = $this->db->get_where('participant_photo', [
			'schedule_request_id' => $schedule->schedule_request_id,
			'id_member'  					=> $member->id,
			'member_id'  					=> $member->member_id,
			'type'               	=> $type,
		])->row())
			return [FALSE, $this->db->error()];

		if (!$row) {
			if (FALSE === $result = $this->db->insert('participant_photo', [
				'schedule_request_id' => $schedule->schedule_request_id,
				'id_member'  					=> $member->id,
				'member_id'  					=> $member->member_id,
				'type'               	=> $type,
				'filename'      			=> $filename,
				'created_at'					=> date('Y-m-d H:i:s'),
			]))
				return [FALSE, $this->db->error()];
		} else {
			if (FALSE === $result = $this->db->update('participant_photo', [
				'filename'      			=> $filename,
				'updated_at'					=> date('Y-m-d H:i:s'),
			], [
				'schedule_request_id' => $schedule->schedule_request_id,
				'id_member'  					=> $member->id,
				'member_id'  					=> $member->member_id,
				'type'               	=> $type,
			]))
				return [FALSE, $this->db->error()];
		}

		return [TRUE, [
			'message' => $this->f->_msg('success_photo_upload'),
			'result'	=> $linkUrl,
		]];
	}

	function upload_photo_exam_finish($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member schedule
		list($return, $result) = $this->_get_member_schedule($member->id, $member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$schedule = $result;

		// Create filename without extension
		$type = "exam_finish";
		$srid = "srid_$schedule->schedule_request_id";
		$filename = "$member->identity_card-$srid-$type";

		// GO Upload to CDN
		list($return, $result) = $this->_upload_to_cdn($filename, 'members', $member->identity_card);
		if (!$return) return [FALSE, ['error' => $result]];

		$linkUrl = $result->link;
		$filename = $result->file;

		// SAVE TO DATABASE
		if (FALSE === $row = $this->db->get_where('participant_photo', [
			'schedule_request_id' => $schedule->schedule_request_id,
			'id_member'  					=> $member->id,
			'member_id'  					=> $member->member_id,
			'type'               	=> $type,
		])->row())
			return [FALSE, $this->db->error()];

		if (!$row) {
			if (FALSE === $result = $this->db->insert('participant_photo', [
				'schedule_request_id' => $schedule->schedule_request_id,
				'id_member'  					=> $member->id,
				'member_id'  					=> $member->member_id,
				'type'               	=> $type,
				'filename'      			=> $filename,
				'created_at'					=> date('Y-m-d H:i:s'),
			]))
				return [FALSE, $this->db->error()];
		} else {
			if (FALSE === $result = $this->db->update('participant_photo', [
				'filename'      			=> $filename,
				'updated_at'					=> date('Y-m-d H:i:s'),
			], [
				'schedule_request_id' => $schedule->schedule_request_id,
				'id_member'  					=> $member->id,
				'member_id'  					=> $member->member_id,
				'type'               	=> $type,
			]))
				return [FALSE, $this->db->error()];
		}

		return [TRUE, [
			'message' => $this->f->_msg('success_photo_upload'),
			'result'	=> $linkUrl,
		]];
	}

	function _upload_to_cdn($filename, $folder, $sub_folder)
	{
		$user_file        = 'userfile';
		if (!isset($_FILES[$user_file]))
			return [FALSE, $this->f->_msg_code('error_upload_userfile_not_exist')];

		if (is_array($_FILES[$user_file]['name']))
			return [FALSE, $this->f->_msg_code('error_upload_userfile_cannot_be_array')];

		$file = $_FILES[$user_file]['tmp_name'];
		$mime = mime_content_type($file);
		$name = $_FILES[$user_file]['name'];
		$cfile = curl_file_create($file, $mime, $name);

		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => 'http://103.31.232.157/cdn_upload_aasi.php',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POST => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_POSTFIELDS => array(
				'folder' => $folder,
				'sub_folder' => $sub_folder,
				'filename' => $filename,
				'userfile' => $cfile,
			),
			CURLOPT_HTTPHEADER => array(
				'Content-Type: multipart/form-data'
			),
		));
		$response = json_decode(curl_exec($curl));
		curl_close($curl);

		if ($response->status) {
			return [TRUE, (object)[
				'link' => $response->link,
				'base' => $response->base,
				'path' => $response->path,
				'file' => $response->file,
			]];
		} else {
			if ($response->error == null) {
				return [FALSE, $this->f->_msg_code('error_cdn_server_not_found')];
			} else {
				return [FALSE, $response->error];
			}
		}
	}

	function _get_exam_setting()	// repaired
	{
		if (FALSE === $row = $this->db->get('exam_setting')->row())
			return [FALSE, $this->db->error()];

		if (!$row)
			return [FALSE, $this->f->_msg_code('err_exam_setting_not_set')];

		// $request->exam_setting = $row;
		return [TRUE, $row];
	}

	// ONLY for start exam
	function _set_exam_start($datetime, $schedule)		// repaired
	{
		if ($schedule == NULL)
			return [FALSE, $this->f->_msg_code('err_member_not_in_schedule')];

		// if (!$this->f->check_param_required($request, ['datetime']))
		// 	return [FALSE, $this->f->error()];

		$dm = $schedule->duration; // on minutes
		$schedule_open = date_create($schedule->open_registration);
		$schedule_close = date_create($schedule->close_registration);

		$exam_start = date_create($datetime);
		$exam_end = date_create($datetime);
		$exam_end = $exam_end->add(new DateInterval('PT' . $dm . 'M'));
		$exam_end = ($exam_end > $schedule_close) ? $schedule_close : $exam_end;

		$exam_start_epoc = strtotime($datetime);
		$exam_end_epoc = strtotime($exam_end->format('Y-m-d H:i:s'));

		if ($exam_start < $schedule_open)
			return [FALSE, $this->f->_msg_code('err_member_schedule_before')];

		if ($exam_start > $schedule_close)
			return [FALSE, $this->f->_msg_code('err_member_schedule_after')];

		$schedule->exam_start_epoc = $exam_start_epoc;
		$schedule->exam_end_epoc = $exam_end_epoc;
		$schedule->exam_start = $exam_start->format('Y-m-d H:i:s');
		$schedule->exam_end = $exam_end->format('Y-m-d H:i:s');
		return [TRUE, $schedule];
	}

	function _get_exam_start_logs($schedule_request_id, $id_member, $member_id)	// repaired
	{
		$str = "(
			SELECT * FROM exam_logs 
			where schedule_request_id = ? and id_member = ? and member_id = ? 
			and JSON_EXTRACT(state, '$.name') = 'start_exam'
		) g0";
		$table = $this->f->compile_qry($str, [$schedule_request_id, $id_member, $member_id]);
		if (FALSE === $row = $this->db->from($table)->get()->row())
			return [FALSE, $this->db->error()];

		return [TRUE, (!$row) ? NULL : $row];
	}

	function _get_exam_finish_logs($schedule_request_id, $id_member, $member_id)	// repaired
	{
		$str = "(
			SELECT * FROM exam_logs 
			where schedule_request_id = ? and id_member = ? and member_id = ? 
			and JSON_EXTRACT(state, '$.name') = 'finish_exam'
			order by id desc
		) g0";
		$table = $this->f->compile_qry($str, [$schedule_request_id, $id_member, $member_id]);
		if (FALSE === $row = $this->db->from($table)->get()->row())
			return [FALSE, $this->db->error()];

		return [TRUE, (!$row) ? NULL : $row];
	}

	function _is_exam_still_running($request, $schedule_request_id, $id_member, $member_id)
	{
		// if (!$this->f->check_param_required($request, ['datetime']))
		// 	return [FALSE, $this->f->error()];

		$dt_client = date_create($request->params->datetime);

		// GET exam start logs
		list($return, $result) = $this->_get_exam_start_logs($schedule_request_id, $id_member, $member_id);
		if (!$return) return [FALSE, $result];

		$exam_logs = $result;
		if ($exam_logs == NULL)
			return [FALSE, $this->f->_msg_code('err_exam_not_started')];

		$state = json_decode($exam_logs->state);
		if ($dt_client >= date_create($state->exam_end)) {

			list($return, $result) = $this->_set_exam_finish($request, $schedule_request_id, $id_member, $member_id);
			if (!$return) return [FALSE, $result];

			return [FALSE, $this->f->_msg_code('err_exam_has_finished')];
		} else if ($dt_client < date_create($state->exam_start)) {

			return [FALSE, $this->f->_msg_code('err_exam_not_started')];
		}

		return [TRUE, $state];
	}

	function _get_exam_results($schedule_request_id, $id_member, $member_id)	// repaired
	{
		$str = "(
			SELECT * FROM exam_results 
			where schedule_request_id = ? and id_member = ? and member_id = ? 
		) g0";
		$table = $this->f->compile_qry($str, [$schedule_request_id, $id_member, $member_id]);
		if (FALSE === $row = $this->db->from($table)->get()->row())
			return [FALSE, $this->db->error()];

		return [TRUE, (!$row) ? NULL : $row];
	}

	function _set_exam_finish($request, $schedule_request_id, $id_member, $member_id)
	{
		if (FALSE === $result = $this->db->update(
			'exam_results',
			['status' => 'completed'],
			[
				'schedule_request_id' => $schedule_request_id,
				'id_member' => $id_member,
				'member_id' => $member_id,
			]
		))
			return [FALSE, $this->db->error()];

		// GET exam start logs
		list($return, $result) = $this->_get_exam_start_logs($schedule_request_id, $id_member, $member_id);
		if (!$return) return [FALSE, $result];

		$exam_logs = $result;
		$state = json_decode($exam_logs->state);

		// GET exam finish logs
		list($return, $result) = $this->_get_exam_finish_logs($schedule_request_id, $id_member, $member_id);
		if (!$return) return [FALSE, $result];

		$exam_finish_logs = $result;
		if ($exam_finish_logs == NULL) {
			$new_state = json_encode([
				'name' 						=> 'finish_exam',
				'activity' 				=> 'confirmation',
				'exam_start'			=> $state->exam_start,
				'exam_end'				=> $state->exam_end,
				'duration'				=> $state->duration,
				'exam_completed' 	=> true,
				'longlat'					=> $state->longlat,
				'num_of_question' => $state->num_of_question,
				'num_answered_question' => $state->num_answered_question,
				'num_of_correct'  => $state->num_of_correct,
				// 'num_remain_question' => $state->num_remain_question,
				'num_of_repeat' 	=> $state->num_of_repeat,
				'score' 					=> $state->score,
			]);

			if (FALSE === $result = $this->db->insert('exam_logs', [
				'schedule_request_id' => $schedule_request_id,
				'id_member'  => $id_member,
				'member_id'  => $member_id,
				'state' 		 => $new_state,
				'ip_address' => 'mobile',
				'user_agent' => $request->agent,
				'coordinate' => $state->longlat,
				'author' 		 => 0,
				'created_on' => strtotime(date('Y-m-d H:i:s')),
			]))
				return [FALSE, $this->db->error()];


			return [TRUE, json_decode($new_state)];
		} else {

			$state = json_decode($exam_finish_logs->state);
			return [TRUE, $state];
		}
	}

	function _get_exam_location($location_id)	// repaired
	{
		$this->db->where(['id' => $location_id]);
		if (FALSE === $row = $this->f->get_db_row('locations'))
			return [FALSE, $this->f->error()];

		return [TRUE, !$row ? null : $row];
	}

	function _get_exam_category($category_id)	// repaired
	{
		$this->db->where(['id' => $category_id]);
		if (FALSE === $row = $this->f->get_db_row('categories'))
			return [FALSE, $this->f->error()];

		$categories = $row;

		$this->db->where(['category_id' => $category_id]);
		if (FALSE === $row = $this->f->get_db_row('category_modules'))
			return [FALSE, $this->f->error()];

		$category_modules = $row;

		$result = $categories;
		$result->num_of_question = $category_modules->questions;

		return [TRUE, $result];
	}

	function _get_member_schedule($id_member, $company_id)	// repaired
	{
		$str = "(
			select t1.id_member, t1.member_id, t2.company_id, t1.schedule_request_id, 
			t2.schedule_id, t2.location_id, t3.category_id, t3.name, t3.duration, t3.notes, 
			t3.open_registration, t3.close_registration 
			from schedule_participants t1 
			left join schedule_requests t2 on t1.schedule_request_id = t2.id 
			left join schedules t3 on t2.schedule_id = t3.id 
			where t1.id_member = ? and t2.company_id = ? 
			order by t1.schedule_request_id desc 
			limit 1
		) g0";
		$table = $this->f->compile_qry($str, [$id_member, $company_id]);
		if (FALSE === $row = $this->f->get_db_row($table))
			return [FALSE, $this->f->error()];

		return [TRUE, !$row ? NULL : $row];
	}

	function _get_exam_logs($schedule_request_id, $id_member, $member_id)	// repaired
	{
		$str = "(
			SELECT * FROM exam_logs 
			where schedule_request_id = ? and id_member = ? and member_id = ? 
			order by id desc limit 1 
		) g0";
		$table = $this->f->compile_qry($str, [$schedule_request_id, $id_member, $member_id]);
		if (FALSE === $row = $this->db->from($table)->get()->row())
			return [FALSE, $this->db->error()];

		$exam_logs = $row;

		$state = !$exam_logs ? null : json_decode($exam_logs->state);
		return [TRUE, $state];
	}

	function _get_question_ids($category_id)
	{
		$this->db->where(['id' => $category_id]);
		if (FALSE === $row = $this->f->get_db_row('categories'))
			return [FALSE, $this->f->error()];

		$category = $row;

		$this->db->where(['category_id' => $category_id]);
		if (FALSE === $row = $this->f->get_db_row('category_modules'))
			return [FALSE, $this->f->error()];

		$category_modules = $row;

		$str = '(
			select id 
			from questions 
			where module_id = ? 
		) g0';
		$table = $this->f->compile_qry($str, [$category_modules->module_id]);
		if (FALSE === $rows = $this->db->from($table)->get()->result())
			return [FALSE, ['error' => $this->db->error()]];


		shuffle($rows);
		$data = array_column($rows, 'id');
		foreach ($data as $key => $value) {
			$data[$key] = $value . str_shuffle('ABCD');
		}

		$result['duration'] = $category->duration;
		$result['passed_grade'] = $category->passed_grade;
		$result['questions'] = $category_modules->questions;

		$tmp = array_slice($data, 0, $category_modules->questions);
		$result['question_ids'] = implode(",", $tmp);

		$tmp = array_fill(0, $category_modules->questions, 'X');
		$result['answer_keys'] = implode(",", $tmp);
		
		return [TRUE, (object) $result];
	}

	function _get_question_byId($question_id, $question_ids = null, $answer_keys = null)
	{
		$id 	= substr($question_id, 0, strlen($question_id) - 4);
		$abcd = strtolower(strtoupper(substr($question_id, -4, 4)));
		$a = substr($abcd, 0, 1);
		$b = substr($abcd, 1, 1);
		$c = substr($abcd, 2, 1);
		$d = substr($abcd, 3, 1);

		$this->db->where(['id' => $id]);
		if (FALSE === $row = $this->f->get_db_row('questions'))
			return [FALSE, $this->f->error()];

		if (!$row) 
			return [TRUE, null];

		$result = $row;
		$result->question_id = $question_id;
		$result->answer_key = strtolower($row->answer_key);
		$result->shuffle = $abcd;
		$result->answered_key = null;

		if ($question_ids != null && $answer_keys != null) {
			$arr_q = explode(',', $question_ids);
			$arr_a = explode(',', strtolower($answer_keys));
			$index = array_search($question_id, $arr_q);
			$answered_key = $arr_a[$index];
			$result->answered_key = $answered_key;
		}

		return [TRUE, $result];
	}
}
