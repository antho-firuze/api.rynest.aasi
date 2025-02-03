<?php if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

class App_model extends CI_Model
{

	function __construct()
	{
		parent::__construct();
		$this->load->library(['f', 'aasi']);
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
		list($return, $result) = $this->aasi->is_valid_token($request);
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
		list($return, $result) = $this->aasi->is_valid_token($request);
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

		list($return, $result) = $this->aasi->send_fcm_notification($request->params->topic, $request->params->title, $request->params->message, $data);
		if (!$return) return [FALSE, ['error' => $this->f->_msg_code($result)]];

		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
		]];
	}

	function upload_to_cdn($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['filename']))
			return [FALSE, ['error' => $this->f->error()]];

		$filename = $request->params->filename;
		$folder = !isset($request->params->folder) ? "" : $request->params->folder;
		$sub_folder = !isset($request->params->sub_folder) ? "" : $request->params->sub_folder;

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

		if ($response->status)
			return [TRUE, [
				'message' => $this->f->_msg('success_request_responded'),
				'result' => [
					'link' => $response->link,
					'base' => $response->base,
					'path' => $response->path,
					'file' => $response->file,
				],
			]];
		else
			return [FALSE, ['error' => $response->error]];
	}
}
