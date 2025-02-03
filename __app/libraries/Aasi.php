<?php defined('BASEPATH') or exit('No direct script access allowed');

define('CDN_PHOTO_URL', "http://103.31.232.157/assets/img/aasi/");

class Aasi
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
	function get_member($user_id) // repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		// $id = implode('-', ['member', $user_id]);
		// if (FALSE !== $result = $ci->f->get_from_cache($id))
		// 	return [TRUE, $result];

		$ci->db->where(['user_id' => $user_id]);
		$ci->db->order_by('id desc');
		if (FALSE === $row = $ci->f->get_db_row('members'))
			return [FALSE, $ci->f->error()];
		elseif (!$row)
			return [FALSE, $ci->f->_msg_code('err_member_not_found')];

		// $ci->f->save_to_cache($id, $row, 60*60);

		return [TRUE, $row];
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
		if (FALSE === $row = $ci->f->get_db_row('tbl_users'))
			return [FALSE, $ci->f->error()];
		elseif (!$row)
			return [FALSE, $ci->f->_msg_code('err_user_not_found')];

		// $ci->f->save_to_cache($id, $row, 60*60);

		return [TRUE, $row];
	}

	/**
	 * Function for getting data User
	 *
	 * @param [type] int
	 * @return void
	 */
	function get_pendaftaran_detail($identity_card) // repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		// $id = implode('-', ['mst_pendaftaran_detail', $identity_card]);
		// if (FALSE !== $result = $ci->f->get_from_cache($id))
		// 	return [TRUE, $result];

		// $ci->db->where(['no_ktp' => $identity_card]);
		// if (FALSE === $row = $ci->f->get_db_row('mst_pendaftaran_detail'))
		// 	return [FALSE, $ci->f->error()];

		$str = "(
			select * 
			from mst_pendaftaran_detail 
			where no_ktp = ? 
			order by id desc 
			limit 1
		) g0";
		$table = $ci->f->compile_qry($str, [$identity_card]);
		if (FALSE === $row = $ci->f->get_db_row($table))
			return [FALSE, $ci->f->error()];

		if (!$row)
			return [TRUE, NULL];

		// if (!$row)
		// 	return [FALSE, $ci->f->_msg_code('err_registration_not_found')];

		// $ci->f->save_to_cache($id, $row, 60*60);

		return [TRUE, $row];
	}

	/**
	 * Function for getting data Company
	 *
	 * @param [type] int
	 * @return void
	 */
	function get_company($company_id) // repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		// $id = implode('-', ['member', $user_id]);
		// if (FALSE !== $result = $ci->f->get_from_cache($id))
		// 	return [TRUE, $result];

		$ci->db->where(['id' => $company_id]);
		if (FALSE === $row = $ci->f->get_db_row('mst_anggota'))
			return [FALSE, $ci->f->error()];

		// $ci->f->save_to_cache($id, $row, 60*60);

		return [TRUE, !$row ? null : $row];
	}

	/**
	 * Function for getting data certificate
	 *
	 * @param [type] int
	 * @return void
	 */
	function get_certificate($id_member) // repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		// $id = implode('-', ['member', $user_id]);
		// if (FALSE !== $result = $ci->f->get_from_cache($id))
		// 	return [TRUE, $result];

		$str = "(
			select t2.no_sertifikat, t1.* 
			from exam_results_sertifikat t1 
			inner join no_sertifikat t2 on t1.id_no_sertfikat = t2.id 
			where t1.id_member = ? 
			limit 1
		) g0";
		$table = $ci->f->compile_qry($str, [$id_member]);
		if (FALSE === $row = $ci->f->get_db_row($table))
			return [FALSE, $ci->f->error()];

		// $ci->f->save_to_cache($id, $row, 60*60);

		return [TRUE, !$row ? null : $row];
	}

	function is_valid_token($request)	// repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		// if (!isset($request->token) || empty($request->token)) 
		// 	return [FALSE, $ci->f->_msg_code('err_token_invalid')];

		if (!$ci->f->is_valid_token($request->token))
			return [FALSE, $ci->f->error()];

		$request->user_id = $ci->f->result()->sub;

		list($return, $result) = $this->_check_single_device_login($request);
		if (!$return) return [FALSE, $result];

		return [TRUE, NULL];
	}

	function _check_single_device_login($request)	// repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		// Get from cache
		// $id = implode('-', ['session', $request->user_id, $request->agent]);
		// if (FALSE !== $result = $ci->f->get_from_cache($id)) 
		// 	if ($result == $request->token)
		// 		return [TRUE, NULL];

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
		// $ci->f->save_to_cache($id, $request->token, 60*60*24);
		return [TRUE, NULL];
	}

	function hash($password)
	{
		$ci = &get_instance();

		$encrypted = substr($password, 0, 1) == '$' ? TRUE : FALSE;
		if ($encrypted)
			return $password;

		$params['rounds'] 		 = 8;
		$params['salt_prefix'] = version_compare(PHP_VERSION, '5.3.7', '<') ? '$2a$' : '$2y$';
		$ci->load->library('bcrypt', $params);

		return $ci->bcrypt->hash($password);
	}

	function get_exam_setting()	// repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');
		// $ci->lang->load('member2', $request->idiom);

		if (FALSE === $row = $ci->db->get('exam_setting')->row())
			return [FALSE, $ci->db->error()];

		if (!$row)
			return [FALSE, $ci->f->_msg_code('err_exam_setting_not_set')];

		// $request->exam_setting = $row;
		return [TRUE, $row];
	}

	function get_exam_category($category_id)	// repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$ci->db->where(['id' => $category_id]);
		if (FALSE === $row = $ci->f->get_db_row('categories'))
			return [FALSE, $ci->f->error()];

		return [TRUE, !$row ? null : $row];
	}

	function get_exam_location($location_id)	// repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$ci->db->where(['id' => $location_id]);
		if (FALSE === $row = $ci->f->get_db_row('locations'))
			return [FALSE, $ci->f->error()];

		return [TRUE, !$row ? null : $row];
	}

	function get_member_schedule($id_member, $company_id)	// repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

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
		$table = $ci->f->compile_qry($str, [$id_member, $company_id]);
		if (FALSE === $row = $ci->f->get_db_row($table))
			return [FALSE, $ci->f->error()];

		// if ($row) {
		// 	$row->pre_time = date_format(date_create($row->date . ' ' . $row->pre), 'Y-m-d H:i:s');
		// 	$row->begin_time = date_format(date_create($row->date . ' ' . $row->begin), 'Y-m-d H:i:s');
		// 	$row->open_reg = date_format(date_create($row->date . ' ' . $row->open_registration), 'Y-m-d H:i:s');
		// 	$row->close_reg = date_format(date_create($row->date . ' ' . $row->close_registration), 'Y-m-d H:i:s');
		// }

		return [TRUE, !$row ? NULL : $row];
	}

	// ONLY for start exam
	function set_exam_start($request, $schedule)		// repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		if ($schedule == NULL)
			return [FALSE, $ci->f->_msg_code('err_member_not_in_schedule')];

		if (!$ci->f->check_param_required($request, ['date_client', 'time_client']))
			return [FALSE, $ci->f->error()];

		$dm = $schedule->duration; // on minutes
		$schedule_open = date_create($schedule->date . ' ' . $schedule->open_registration);
		$schedule_close = date_create($schedule->date . ' ' . $schedule->close_registration);

		$exam_start = date_create($request->params->date_client . ' ' . $request->params->time_client);
		$exam_end = date_create($request->params->date_client . ' ' . $request->params->time_client);
		$exam_end = $exam_end->add(new DateInterval('PT' . $dm . 'M'));
		$exam_end = ($exam_end > $schedule_close) ? $schedule_close : $exam_end;

		$exam_start_epoc = strtotime($request->params->date_client . ' ' . $request->params->time_client);
		$exam_end_epoc = strtotime($exam_end->format('Y-m-d H:i:s'));

		if ($exam_start < $schedule_open)
			return [FALSE, $ci->f->_msg_code('err_member_schedule_before')];

		if ($exam_start > $schedule_close)
			return [FALSE, $ci->f->_msg_code('err_member_schedule_after')];

		$schedule->exam_start_epoc = $exam_start_epoc;
		$schedule->exam_end_epoc = $exam_end_epoc;
		$schedule->exam_start = $exam_start->format('Y-m-d H:i:s');
		$schedule->exam_end = $exam_end->format('Y-m-d H:i:s');
		return [TRUE, $schedule];
	}

	function get_member_photo($id_member, $member_id, $card_no, $schedule_request_id)	// repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$str = "(
			select * 
			from participant_photo 
			where id_member = ? and member_id = ? and schedule_request_id = ? 
		) g0";
		$table = $ci->f->compile_qry($str, [$id_member, $member_id, $schedule_request_id]);
		if (FALSE === $rows = $ci->db->from($table)->get()->result())
			return [FALSE, $ci->db->error()];

		$link = [];
		$base_path = CDN_PHOTO_URL;
		foreach ($rows as $key => $val) {
			$folder 	= "members/";
			$sub_folder = "$card_no/";
			$filename 	= $val->filename;
			$type				= $val->type;
			$link[$type] = $base_path . $folder . $sub_folder . $filename;
		}

		return [TRUE, count($link) < 1 ? null : $link];
	}

	function get_exam_results($schedule_request_id, $id_member, $member_id)	// repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$str = "(
			SELECT * FROM exam_results 
			where schedule_request_id = ? and id_member = ? and member_id = ? 
		) g0";
		$table = $ci->f->compile_qry($str, [$schedule_request_id, $id_member, $member_id]);
		if (FALSE === $row = $ci->db->from($table)->get()->row())
			return [FALSE, $ci->db->error()];

		return [TRUE, (!$row) ? NULL : $row];
	}

	function get_exam_start_logs($schedule_request_id, $id_member, $member_id)	// repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$str = "(
			SELECT * FROM exam_logs 
			where schedule_request_id = ? and id_member = ? and member_id = ? 
			and JSON_EXTRACT(state, '$.name') = 'start_exam'
		) g0";
		$table = $ci->f->compile_qry($str, [$schedule_request_id, $id_member, $member_id]);
		if (FALSE === $row = $ci->db->from($table)->get()->row())
			return [FALSE, $ci->db->error()];

		return [TRUE, (!$row) ? NULL : $row];
	}

	function get_exam_finish_logs($schedule_request_id, $id_member, $member_id)	// repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$str = "(
			SELECT * FROM exam_logs 
			where schedule_request_id = ? and id_member = ? and member_id = ? 
			and JSON_EXTRACT(state, '$.name') = 'finish_exam'
			order by id desc
		) g0";
		$table = $ci->f->compile_qry($str, [$schedule_request_id, $id_member, $member_id]);
		if (FALSE === $row = $ci->db->from($table)->get()->row())
			return [FALSE, $ci->db->error()];

		return [TRUE, (!$row) ? NULL : $row];
	}

	function get_exam_logs($schedule_request_id, $id_member, $member_id)	// repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$str = "(
			SELECT * FROM exam_logs 
			where schedule_request_id = ? and id_member = ? and member_id = ? 
			order by id desc limit 1 
		) g0";
		$table = $ci->f->compile_qry($str, [$schedule_request_id, $id_member, $member_id]);
		if (FALSE === $row = $ci->db->from($table)->get()->row())
			return [FALSE, $ci->db->error()];

		$exam_logs = $row;
		// if (!$exam_logs) {
		// 	$state = (object)[
		// 		"exam_start" => null,
		// 		"exam_end" => null,
		// 		"duration" => null,
		// 		"exam_completed" => null,
		// 		"num_of_correct" => null,
		// 		"num_of_question" => null,
		// 		"num_answered_question" => null,
		// 		"num_remain_question" => null,
		// 		"num_of_repeat" => null,
		// 		"score" => null,
		// 	];
		// } else {
		// 	$state = json_decode($exam_logs->state);
		// }

		$state = !$exam_logs ? null : json_decode($exam_logs->state);
		return [TRUE, $state];
	}

	function is_exam_still_running($request, $schedule_request_id, $id_member, $member_id)
	{
		$ci = &get_instance();
		$ci->load->library('f');

		if (!$ci->f->check_param_required($request, ['date_client', 'time_client']))
			return [FALSE, $ci->f->error()];

		$dt_client = date_create($request->params->date_client . ' ' . $request->params->time_client);

		// GET exam start logs
		list($return, $result) = $this->get_exam_start_logs($schedule_request_id, $id_member, $member_id);
		if (!$return) return [FALSE, $result];

		$exam_logs = $result;
		if ($exam_logs == NULL)
			return [FALSE, $ci->f->_msg_code('err_exam_not_started')];

		$state = json_decode($exam_logs->state);
		if ($dt_client >= date_create($state->exam_end)) {

			list($return, $result) = $this->set_exam_finish($request, $schedule_request_id, $id_member, $member_id);
			if (!$return) return [FALSE, $result];

			return [FALSE, $ci->f->_msg_code('err_exam_has_finished')];
		} else if ($dt_client < date_create($state->exam_start)) {

			return [FALSE, $ci->f->_msg_code('err_exam_not_started')];
		}

		return [TRUE, $state];
	}

	function set_exam_finish($request, $schedule_request_id, $id_member, $member_id)
	{
		$ci = &get_instance();
		$ci->load->library('f');

		if (FALSE === $result = $ci->db->update(
			'exam_results',
			['status' => 'completed'],
			[
				'schedule_request_id' => $schedule_request_id,
				'id_member' => $id_member,
				'member_id' => $member_id,
			]
		))
			return [FALSE, $ci->db->error()];

		// GET exam start logs
		list($return, $result) = $this->get_exam_start_logs($schedule_request_id, $id_member, $member_id);
		if (!$return) return [FALSE, $result];

		$exam_logs = $result;
		$state = json_decode($exam_logs->state);

		// GET exam finish logs
		list($return, $result) = $this->get_exam_finish_logs($schedule_request_id, $id_member, $member_id);
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
				'num_of_correct'  => $state->num_of_correct,
				'num_of_question' => $state->num_of_question,
				'num_answered_question' => $state->num_answered_question,
				'num_remain_question' => $state->num_remain_question,
				'num_of_repeat' 	=> $state->num_of_repeat,
				'score' 					=> $state->score,
			]);

			if (FALSE === $result = $ci->db->insert('exam_logs', [
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
				return [FALSE, $ci->db->error()];


			return [TRUE, json_decode($new_state)];
		} else {

			$state = json_decode($exam_finish_logs->state);
			return [TRUE, $state];
		}
	}

	/**
	 * Function for processing question & answer
	 *
	 * @param object $request
	 * @param string $question_ids
	 * @param string $answer_keys
	 * @return void
	 */
	function process_q_a($question_id, $answer_key, $question_ids, $answer_keys)	// repaired
	{
		$ci = &get_instance();

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
		$result->num_answer_question = count($arr_q);

		return self::score_calculation($result, $arr_x);
	}

	function score_calculation($data, $arr_q = [])
	{
		$ci = &get_instance();

		$score = 0;
		$num_of_correct = 0;
		foreach ($arr_q as $key => $value) {
			if ($row = $ci->db->select('score')
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
			'smtp_host'	=> 'smtp-relay.sendinblue.com',
			'smtp_port'	=> '587',			// ssl=465 or tls=587
			'smtp_user'	=> 'deddy@rynest-technology.com',
			'smtp_pass'	=> 'X3yFd9a8qwsUItc0',
			'smtp_crypto'	=> 'tls',		// ssl/tls	
			'smtp_timeout' => 7,
			'from'				=> 'info@aasi.or.id',		// email sender
			'from_name'		=> 'AASI APP',
		];
	}

	function send_fcm_notification($topic, $title, $message, $data = [])
	{
		$ci = &get_instance();
		$ci->load->library('f');

		if ($topic)

			$extra_data = ['click_action'	=> 'FLUTTER_NOTIFICATION_CLICK'];
		if (count($data) > 0) {
			$extra_data = array_merge($extra_data, $data);
		}

		$payload = json_encode([
			'priority' 	=> 'high',
			'to' 				=> '/topics/' . $topic,
			'notification' => [
				'title'	=> $title,
				'body'	=> $message,
				'icon'	=> 'ic_launcher',
			],
			'data'	=> $extra_data,
		]);
		//FCM API end-point
		$url = 'https://fcm.googleapis.com/fcm/send';
		//api_key in Firebase Console -> Project Settings -> CLOUD MESSAGING -> Server key
		$server_key = 'AAAAnAPVdF8:APA91bECV3TufaZDJ52gQzVQiYAoKCfXf7R9t9j2ho9oRmAObxYb7fDZ914wXlA6uOAZjPSFgvNZN_5HMJUqgLT8o0CgBs-oopa3Z2lM1dTFtaEVlGnPEykc2UMXbxXYw_6I27xO0oc2';
		//header with content_type api key
		$headers = [
			'Content-Type: application/json',
			'Authorization: key=' . $server_key
		];
		//CURL request to route notification to FCM connection server (provided by Google)
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		$result = curl_exec($ch);
		if ($result === FALSE) {
			return [FALSE, curl_error($ch)];
			// die('Oops! FCM Send Error: ' . curl_error($ch));
		}
		curl_close($ch);

		return [TRUE, NULL];
	}
}
