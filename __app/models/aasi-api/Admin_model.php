<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Admin_model extends CI_Model
{

	function __construct()
	{
		parent::__construct();
		$this->load->library(['f', 'aasi']);
		$this->load->database(DB_CONN[HTTP_HOST]);
	}

    function user($request)
    {
		if (!$this->f->check_param_required($request, ['identifier']))
			return [FALSE, ['error' => $this->f->error()]];

		// GET user
		list($return, $result) = $this->_get_user($request->params->identifier);
		if (!$return) return [FALSE, ['error' => $result]];

		$user = $result;

		// GET member
		list($return, $result) = $this->_get_member_by_userId($user->id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET member photo base on schedule_request_id
		list($return, $result) = $this->aasi->get_member_photo($member->id, $member->member_id, $member->identity_card, $schedule->schedule_request_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$photos = $result;

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

		// GET exam results
		list($return, $result) = $this->_get_exam_results($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_results = $result;
		
		// CHECK exam still running
		list($return, $result) = $this->_get_exam_logs($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$state = $result;

		// GET Questions
		list($return, $result) = $this->_get_question_ids($schedule->category_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$question = $result;

		$result = (object) [];
		$result->user = $user;
		$result->member = $member;
		$result->photos = $photos;
		$result->schedule = $schedule;
		$result->category = $category;
		$result->location = $location;
		$result->exam_results = $exam_results;
		$result->exam_state = $state;
		$result->question = $question;
        return [TRUE, ['result' => $result]];
    }

    function member($request)
    {
		if (!$this->f->check_param_required($request, ['identifier']))
			return [FALSE, ['error' => $this->f->error()]];

		// GET member
		list($return, $result) = $this->_get_member_by_identifier($request->params->identifier);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET user
		list($return, $result) = $this->_get_user_by_id($member->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$user = $result;

		// GET member photo base on schedule_request_id
		list($return, $result) = $this->aasi->get_member_photo($member->id, $member->member_id, $member->identity_card, $schedule->schedule_request_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$photos = $result;

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

		// GET exam results
		list($return, $result) = $this->_get_exam_results($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$exam_results = $result;
		
		// CHECK exam still running
		list($return, $result) = $this->_get_exam_logs($schedule->schedule_request_id, $member->id, $member->member_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$state = $result;

		// GET Questions
		list($return, $result) = $this->_get_question_ids($schedule->category_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$question = $result;

		$result = (object) [];
		$result->member = $member;
		$result->user = $user;
		$result->photos = $photos;
		$result->schedule = $schedule;
		$result->category = $category;
		$result->location = $location;
		$result->exam_results = $exam_results;
		$result->exam_state = $state;
		$result->question = $question;
        return [TRUE, ['result' => $result]];
    }

	function _get_user($identifier) // repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$ci->db->where(['username' => $identifier]);
		if (FALSE === $row = $ci->f->get_db_row('tbl_users'))
			return [FALSE, $ci->f->error()];
		elseif (!$row)
			return [FALSE, $ci->f->_msg_code('err_user_not_found')];

		// $ci->f->save_to_cache($id, $row, 60*60);

		return [TRUE, $row];
	}

	function _get_user_by_id($id) // repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$ci->db->where(['id' => $id]);
		if (FALSE === $row = $ci->f->get_db_row('tbl_users'))
			return [FALSE, $ci->f->error()];
		elseif (!$row)
			return [FALSE, $ci->f->_msg_code('err_user_not_found')];

		// $ci->f->save_to_cache($id, $row, 60*60);

		return [TRUE, $row];
	}

	function _get_member_by_userId($id) // repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$ci->db->where(['user_id' => $id]);
		$ci->db->order_by('id desc');
		if (FALSE === $row = $ci->f->get_db_row('members'))
			return [FALSE, $ci->f->error()];
		elseif (!$row)
			return [FALSE, $ci->f->_msg_code('err_member_not_found')];

		// $ci->f->save_to_cache($id, $row, 60*60);

		return [TRUE, $row];
	}

	function _get_member_by_identifier($identifier) // repaired
	{
		$ci = &get_instance();
		$ci->load->library('f');

		$ci->db->or_like(['member_id' => $identifier]);
		$ci->db->or_like(['identity_card' => $identifier]);
		$ci->db->order_by('id desc');
		if (FALSE === $row = $ci->f->get_db_row('members'))
			return [FALSE, $ci->f->error()];
		elseif (!$row)
			return [FALSE, $ci->f->_msg_code('err_member_not_found')];

		// $ci->f->save_to_cache($id, $row, 60*60);

		return [TRUE, $row];
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
		$result->category_modules = $category_modules;

		return [TRUE, $result];
	}

	function _get_exam_location($location_id)	// repaired
	{
		$this->db->where(['id' => $location_id]);
		if (FALSE === $row = $this->f->get_db_row('locations'))
			return [FALSE, $this->f->error()];

		return [TRUE, !$row ? null : $row];
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

}