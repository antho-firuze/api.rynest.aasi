<?php if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

class App_model extends CI_Model
{

	function __construct()
	{
		parent::__construct();
		$this->load->library(['f', 'lsp']);
		$this->load->database(DB_CONN[HTTP_HOST]);
	}

	function check_version($request)
	{
		$str = "(
			select * from app_version 
			where agent = ? 
		) g0";
		$table = $this->f->compile_qry($str, [$request->agent]);
		if (!$result = $this->db->from($table)->get())
			return [FALSE, ['message' => 'Database Error: ' . $this->db->error()['message']]];

		if (!$row = $result->row())
			return [FALSE, ['message' => 'Versi Aplikasi belum terdaftar']];

		$result = [
			'app_name' => $row->name,
			'app_agent' => $row->agent,
			'app_version' => $row->version
		];
		return [TRUE, ['message' => $this->f->_msg('success_request_responded'), 'result' => $result]];
	}

	function update_device_info($request)
	{
		list($return, $result) = $this->lsp->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (gettype($request->params->device_info) == 'object') {
			$request->params->device_info = json_encode($request->params->device_info);
		}

		if (FALSE === $result = $this->db->update(
			'users',
			[
				'device_info' => $request->params->device_info,
			],
			[
				'id' => $request->user_id,
			]
		))
			return [FALSE, ['error' => $this->db->error()]];

		return [TRUE, ['message' => $this->f->_msg('success_request_responded')]];
	}

	function send_notification($request)
	{
		list($return, $result) = $this->lsp->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['topic', 'title', 'message']))
			return [FALSE, ['error' => $this->f->error()]];

		$data = [
			'token' => $request->token,
		];
		if (isset($request->params->data)) {
			if (gettype($request->params->data) == 'object') {
				$data = array_merge($data, (array) $request->params->data);
			}
		}

		list($return, $result) = $this->lsp->send_fcm_notification($request->params->topic, $request->params->title, $request->params->message, $data);
		if (!$return) return [FALSE, ['error' => $this->f->_msg_code($result)]];

		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
		]];
	}
}
