<?php defined('BASEPATH') or exit('No direct script access allowed');

class Qonstantatryout
{

	function __construct()
	{
	}

	/**
	 * Function for getting data Member
	 *
	 * @param [type] int
	 * @return void
	 */
	function get_member($user_id)
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$id = implode('-', ['member', $user_id]);
		if (FALSE !== $result = $ci->f->get_from_cache($id))
			return [TRUE, $result];

		$ci->db->where(['user_id' => $user_id]);
		if (FALSE === $row = $ci->f->get_db_row('members'))
			return [FALSE, $ci->f->error()];
		elseif (!$row)
			return [FALSE, $ci->f->_msg_code('err_member_not_found')];

		$ci->f->save_to_cache($id, $row, 60 * 60);

		return [TRUE, $row];
	}

	/**
	 * Function for getting data Schedules
	 *
	 * @param [type] int
	 * @param [type] date('Y-m-d')
	 * @return void
	 */
	function get_member_schedules_by_date($member_id, $dt_client)
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$sql = [
			"(
				select t1.schedule_request_id, t1.exam_series_id, t2.schedule_id, t2.location_id, 
				t3.category_id, t3.notes as category_name, t3.duration, 
				(select count(1) from questions a 
				where a.exam_series_id = t1.exam_series_id and a.module_id = (select module_id from category_modules where category_id = t3.category_id)) as num_of_questions, 
				t3.date, t3.open_registration, t3.close_registration, t1.finish 
				from schedule_participants t1
				left join schedule_requests t2 on t1.schedule_request_id = t2.id
				left join schedules t3 on t2.schedule_id = t3.id
				where t1.member_id = ? and t3.date = ? 
				order by t3.category_id
			) g0",
			[$member_id, $dt_client]
		];
		if (FALSE === $result = $ci->f->get_db_query($sql))
			return [FALSE, $ci->f->error()];
		elseif (!$result)
			return [FALSE, $ci->f->_msg_code('err_member_not_in_schedule')];

		// Remove schedule if finish is true
		$new_result = [];
		foreach ($result as $key => $val)
			if ($val->finish == 0)
				$new_result[] = $result[$key];

		// Check, if new_result is empty then throw error
		if (!$new_result || count($new_result) == 0)
			return [FALSE, $ci->f->_msg_code('err_member_schedule_finish')];

		return [TRUE, $new_result];
	}

	/**
	 * Function for getting data Schedules
	 *
	 * @param [type] int
	 * @param [type] date('Y-m-d')
	 * @return void
	 */
	function get_member_schedules($member_id)
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$sql = [
			"(
				select t1.schedule_request_id, t1.exam_series_id, t2.schedule_id, t2.location_id, 
				t3.category_id, t3.notes as category_name, t3.duration, 
				(select count(1) from questions a 
				where a.exam_series_id = t1.exam_series_id and a.module_id = (select module_id from category_modules where category_id = t3.category_id)) as num_of_questions, 
				t3.date, t3.open_registration, t3.close_registration, t3.open_datetime, t3.close_datetime, t1.finish, 
				(select create_date from exam_results where schedule_request_id = t1.schedule_request_id and category_id = t3.category_id) as exam_date,
				(select truefalse from exam_results where schedule_request_id = t1.schedule_request_id and category_id = t3.category_id) as truefalse 
				from schedule_participants t1
				left join schedule_requests t2 on t1.schedule_request_id = t2.id
				left join schedules t3 on t2.schedule_id = t3.id
				where t1.member_id = ? and t3.date = (select MAX(a3.date)
				from schedule_participants a1 
				left join schedule_requests a2 on a1.schedule_request_id = a2.id 
				left join schedules a3 on a2.schedule_id = a3.id 
				where a1.member_id = ?) 
				order by t3.category_id
			) g0",
			[$member_id, $member_id]
		];
		if (FALSE === $result = $ci->f->get_db_query($sql))
			return [FALSE, $ci->f->error()];
		elseif (!$result)
			return [FALSE, $ci->f->_msg_code('err_member_not_in_schedule')];

		return [TRUE, $result];
	}

	// function get_member_exam_results($member_id)
	// {
	// 	$ci = &get_instance();
	// 	$ci->load->library('f');

	// 	$sql = [
	// 		"(
	// 			select t1.schedule_request_id, t3.category_id, 
	// 			(select truefalse from exam_results where schedule_request_id = t1.schedule_request_id and category_id = t3.category_id) as truefalse, 
	// 			(select create_date from exam_results where schedule_request_id = t1.schedule_request_id and category_id = t3.category_id) as create_date 
	// 			from schedule_participants t1
	// 			left join schedule_requests t2 on t1.schedule_request_id = t2.id
	// 			left join schedules t3 on t2.schedule_id = t3.id
	// 			where t1.member_id = ? and t3.date = (select MAX(a3.date)
	// 			from schedule_participants a1 
	// 			left join schedule_requests a2 on a1.schedule_request_id = a2.id 
	// 			left join schedules a3 on a2.schedule_id = a3.id 
	// 			where a1.member_id = ?) 
	// 			order by t3.category_id
	// 		) g0",
	// 		[$member_id, $member_id]
	// 	];
	// 	if (FALSE === $result = $ci->f->get_db_query($sql))
	// 		return [FALSE, $ci->f->error()];

	// 	return [TRUE, $result];
	// }

	function check_member_on_schedule($request, $schedule)
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$schedule_open = date_create($schedule->date . ' ' . $schedule->open_registration);
		$schedule_close = date_create($schedule->date . ' ' . $schedule->close_registration);
		$dt_client = date_create($request->params->date_client . ' ' . $request->params->time_client);
		if ($dt_client < $schedule_open)
			return [FALSE, $ci->f->_msg_code('err_tryout_not_started')];

		if ($dt_client > $schedule_close)
			return [FALSE, $ci->f->_msg_code('err_tryout_has_finished')];

		return [TRUE, NULL];
	}

	/**
	 * Function for Checking is Member On Schedule
	 *
	 * @param [type] int
	 * @param [type] date('Y-m-d')
	 * @return void
	 */
	function get_member_schedule($member_id, $schedule_request_id, $category_id)
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$sql = [
			"(
				select t1.schedule_request_id, t1.exam_series_id, t2.schedule_id, t2.location_id, 
				t3.category_id, t3.notes as category_name, t3.duration, 
				(select count(1) from questions a 
				where a.exam_series_id = t1.exam_series_id and a.module_id = (select module_id from category_modules where category_id = t3.category_id)) as num_of_questions, 
				t3.date, t3.open_registration, t3.close_registration, t3.open_datetime, t3.close_datetime, t1.finish 
				from schedule_participants t1
				left join schedule_requests t2 on t1.schedule_request_id = t2.id
				left join schedules t3 on t2.schedule_id = t3.id
				where t1.finish = 0 and t1.member_id = ? and t1.schedule_request_id = ? and t3.category_id = ?
			) g0",
			[$member_id, $schedule_request_id, $category_id]
		];
		if (FALSE === $row = $ci->f->get_db_query($sql)[0])
			return [FALSE, $ci->f->error()];
		elseif (!$row)
			return [FALSE, $ci->f->_msg_code('err_member_not_in_schedule')];

		return [TRUE, $row];
	}

	/**
	 * Function for getting data Exam Results
	 *
	 * @param [type] $request
	 * @param [type] array
	 * @param [type] array
	 * @param [type] bool
	 * @return void
	 */
	function get_exam_results($request, $member, $schedule, $create_if_not_exist = FALSE)
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$dt_client = strtotime($request->params->date_client . ' ' . $request->params->time_client);

		$ci->db->where([
			'member_id' 		=> $member->member_id,
			'schedule_date' => $schedule->date,
			'category_id' 	=> $schedule->category_id,
			'schedule_request_id' => $schedule->schedule_request_id,
		]);
		if (FALSE === $row = $ci->f->get_db_row('exam_results'))
			return [FALSE, $ci->f->error()];
		elseif (!$row && $create_if_not_exist) {
			$data = [
				'member_id' 		=> $member->member_id,
				'schedule_date' => $schedule->date,
				'category_id' 	=> $schedule->category_id,
				'schedule_request_id' => $schedule->schedule_request_id,
				'begin' 				=> $dt_client,
				'question_ids' 	=> '',
				'answer_keys'  	=> '',
				'truefalse'  		=> '',
				'sync_time' 	 	=> strtotime(date('Y-m-d H:i:s')),
				'sync_question' => 0,
				'score' 				=> 0,
				'status' 				=> '',
			];
			if (!$result = $ci->db->insert('exam_results', $data))
				return [FALSE, $ci->db->error()];

			return [TRUE, (object) $data];
		} elseif (!$row && !$create_if_not_exist) {
			return [FALSE, $ci->f->_msg_code('err_tryout_not_started')];
		} else {
			return [TRUE, $row];
		}
	}

	/**
	 * Function for getting data Question by Series & Category
	 *
	 * @param [type] int
	 * @param [type] bool
	 * @return void
	 */
	function get_exam_questions($category_id, $series_id, $with_answer_key = FALSE)
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$id = implode('-', ['questions', $category_id, $series_id, $with_answer_key ? 'true' : 'false']);
		if (FALSE !== $result = $ci->f->get_from_cache($id))
			return [TRUE, $result];

		if ($with_answer_key)
			$sql = [
				"(
					select t1.exam_series_id as series_id, t1.id, answer_key  
					from questions t1 
					left join category_modules t2 on t1.module_id = t2.module_id
					left join categories t3 on t2.category_id = t3.id
					where t1.exam_series_id = ? and t1.module_id in (select module_id from category_modules where category_id = ?)
				) g0",
				[$series_id, $category_id]
			];
		else
			$sql = [
				"(
					select t1.exam_series_id as series_id, t1.id, question, answer_option_a, answer_option_b, answer_option_c, answer_option_d, answer_option_e 
					from questions t1 
					left join category_modules t2 on t1.module_id = t2.module_id
					left join categories t3 on t2.category_id = t3.id
					where t1.exam_series_id = ? and t1.module_id in (select module_id from category_modules where category_id = ?)
				) g0",
				[$series_id, $category_id]
			];

		if (FALSE === $result = $ci->f->get_db_query($sql))
			return [FALSE, $ci->f->error()];
		elseif (!$result)
			return [FALSE, $ci->f->_msg_code('err_questions_not_available')];

		$ci->f->save_to_cache($id, $result, 60 * 60);

		return [TRUE, $result];
	}

	/**
	 * Function for getting data Exam Logs
	 *
	 * @param [type] $request
	 * @param [type] array
	 * @param [type] array
	 * @param [type] string
	 * @return void
	 */
	function get_exam_logs($request, $member, $schedule, $session = null, $state = 'start_exam')
	{
		$ci = &get_instance();
		$ci->load->library('f');

		if ($state == 'start_exam')
			$json_state = json_encode([
				'name' 				=> 'start_exam',
				'activity' 		=> 'confirmation',
				'dt_client'		=> strtotime($request->params->date_client . ' ' . $request->params->time_client),
				'exam_begin'	=> $session->begin_time,
				'exam_finish'	=> $session->finish_time,
				'duration'		=> $schedule->duration,
				'longlat'			=> $request->params->coordinate,
			]);
		else
			$json_state = json_encode([
				'name' 				=> 'finish_exam',
				'activity' 		=> 'confirmation',
				'dt_client'		=> strtotime($request->params->date_client . ' ' . $request->params->time_client),
				'longlat'			=> $request->params->coordinate,
			]);

		$data = [
			'member_id'  => $member->member_id,
			'schedule_request_id' => $schedule->schedule_request_id,
			'state' 		 => $json_state,
			'ip_address' => 'mobile',
			'user_agent' => $request->agent,
			'coordinate' => $request->params->coordinate,
			'author' 		 => 0,
			'created_on' => strtotime(date('Y-m-d H:i:s')),
		];
		if (!$result = $ci->db->insert('exam_logs', $data))
			return [FALSE, $ci->db->error()];

		return [TRUE, NULL];
	}

	function is_valid_token($request)
	{
		$ci = &get_instance();
		$ci->load->library('f');

		if (!isset($request->token) || empty($request->token))
			return [FALSE, $ci->f->_msg_code('err_token_invalid')];

		if (!$ci->f->is_valid_token($request->token))
			return [FALSE, $ci->f->error()];

		$request->user_id = $ci->f->result()->sub;

		list($return, $msg) = $this->_check_single_device_login($request);
		if (!$return) return [FALSE, $msg];

		return [TRUE, NULL];
	}

	function _check_single_device_login($request)
	{
		$ci = &get_instance();
		$ci->load->library('f');

		// Get from cache
		$id = implode('-', ['session', $request->user_id, $request->agent]);
		if (FALSE !== $result = $ci->f->get_from_cache($id))
			if ($result == $request->token)
				return [TRUE, NULL];

		$ci->db->where([
			'user_id' => $request->user_id,
			'agent' => $request->agent,
			'token' => $request->token,
		]);
		if (FALSE === $row = $ci->f->get_db_row('login_session'))
			return [FALSE, $ci->f->error()];
		elseif (!$row)
			return [FALSE, $ci->f->_msg_code('err_token_invalid')];

		// Save to cache
		$ci->f->save_to_cache($id, $request->token, 60 * 60 * 24);
		return [TRUE, NULL];
	}

	/**
	 * Function for getting data Archive Category by Series
	 *
	 * @param [type] int
	 * @param [type] bool
	 * @return void
	 */
	// function get_category_by_series($series_id)
	// {
	// 	$ci =& get_instance();
	// 	$ci->load->library('f');

	// 	$id = implode('-', ['archive', 'category_by_series', $series_id]);
	// 	if (FALSE !== $result = $ci->f->get_from_cache($id))
	// 		return [TRUE, $result];

	// 	if ($with_answer_key)
	// 		$sql = [
	// 			"(
	// 				select t1.exam_series_id as series_id, t1.id, answer_key  
	// 				from questions t1 
	// 				left join category_modules t2 on t1.module_id = t2.module_id
	// 				left join categories t3 on t2.category_id = t3.id
	// 				where t1.exam_series_id = ? and t1.module_id in (select module_id from category_modules where category_id = ?)
	// 			) g0", 
	// 			[$series_id, $category_id]
	// 		];
	// 	else 
	// 		$sql = [
	// 			"(
	// 				select t1.exam_series_id as series_id, t1.id, question, answer_option_a, answer_option_b, answer_option_c, answer_option_d, answer_option_e 
	// 				from questions t1 
	// 				left join category_modules t2 on t1.module_id = t2.module_id
	// 				left join categories t3 on t2.category_id = t3.id
	// 				where t1.exam_series_id = ? and t1.module_id in (select module_id from category_modules where category_id = ?)
	// 			) g0", 
	// 			[$series_id, $category_id]
	// 		];

	// 	if (FALSE === $result = $ci->f->get_db_query($sql))
	// 		return [FALSE, $ci->f->error()];
	// 	elseif (!$result)
	// 		return [FALSE, $ci->f->_msg_code('err_questions_not_available')];

	// 	$ci->f->save_to_cache($id, $result, 60*60);

	// 	return [TRUE, $result];
	// }

	/**
	 * Function for getting data Archive Question by Series
	 *
	 * @param [type] int
	 * @param [type] bool
	 * @return void
	 */
	// function get_question_by_series($series_id)
	// {
	// 	$ci =& get_instance();
	// 	$ci->load->library('f');

	// 	$id = implode('-', ['archive', 'question_by_series', $series_id]);
	// 	if (FALSE !== $result = $ci->f->get_from_cache($id))
	// 		return [TRUE, $result];

	// 	if ($with_answer_key)
	// 		$sql = [
	// 			"(
	// 				select t1.exam_series_id as series_id, t1.id, answer_key  
	// 				from questions t1 
	// 				left join category_modules t2 on t1.module_id = t2.module_id
	// 				left join categories t3 on t2.category_id = t3.id
	// 				where t1.exam_series_id = ? and t1.module_id in (select module_id from category_modules where category_id = ?)
	// 			) g0", 
	// 			[$series_id, $category_id]
	// 		];
	// 	else 
	// 		$sql = [
	// 			"(
	// 				select t1.exam_series_id as series_id, t1.id, question, answer_option_a, answer_option_b, answer_option_c, answer_option_d, answer_option_e 
	// 				from questions t1 
	// 				left join category_modules t2 on t1.module_id = t2.module_id
	// 				left join categories t3 on t2.category_id = t3.id
	// 				where t1.exam_series_id = ? and t1.module_id in (select module_id from category_modules where category_id = ?)
	// 			) g0", 
	// 			[$series_id, $category_id]
	// 		];

	// 	if (FALSE === $result = $ci->f->get_db_query($sql))
	// 		return [FALSE, $ci->f->error()];
	// 	elseif (!$result)
	// 		return [FALSE, $ci->f->_msg_code('err_questions_not_available')];

	// 	$ci->f->save_to_cache($id, $result, 60*60);

	// 	return [TRUE, $result];
	// }

	function get_mail_config()
	{
		return [
			'useragent' => 'CI Webservice',
			'charset'		=> 'utf-8',		// Character set (utf-8, iso-8859-1, etc.).
			'protocol'	=> 'smtp',		// mail, sendmail, or smtp
			'mailtype'	=> 'html',		// text or html
			'priority'	=> '1',				// 1, 2, 3, 4, 5
			'newline'		=> "\r\n",		// “\r\n” or “\n” or “\r”
			'crlf'			=> "\r\n",		// “\r\n” or “\n” or “\r”
			'smtp_host'	=> 'smtp.sendgrid.net',
			'smtp_port'	=> '465',			// ssl=465 or tls=587
			'smtp_user'	=> SMTP_USER,
			'smtp_pass'	=> SMTP_PASS,
			'smtp_crypto'	=> 'ssl',		// ssl/tls	
			'smtp_timeout' => 7,
			'from'				=> 'info@qonstanta.com',		// email sender
			'from_name'		=> 'Info Qonstanta',
		];
	}

	/**
	 * Function for getting data User
	 *
	 * @param [type] int
	 * @return void
	 */
	function get_user($user_id) // repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		// $id = implode('-', ['user', $user_id]);
		// if (FALSE !== $result = $ci->f->get_from_cache($id))
		// 	return [TRUE, $result];

		$ci->db->where(['id' => $user_id]);
		if (FALSE === $row = $ci->f->get_db_row('users'))
			return [FALSE, $ci->f->error()];
		elseif (!$row)
			return [FALSE, $ci->f->_msg_code('err_user_not_found')];

		// $ci->f->save_to_cache($id, $row, 60*60);

		return [TRUE, $row];
	}

}
