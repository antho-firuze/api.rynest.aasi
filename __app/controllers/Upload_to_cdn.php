<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** 
 *  Title : Upload photo Scripts for LSP-MOBILE APPS
 *  Desc	: This script is for Mobile Apps Only. 
 *  Environment : Codeigniter 3 on Controllers.
 * 
 * 					===================================================================
 * 					PLEASE DO NOT MODIF OR DELETE, only if your know what you are doing.
 * 					===================================================================
 * 
 * 					Thanks.
 * 
 *  Created by : antho.firuze@gmail.com
 *  Created at : 29-Dec-2020 14:37
 */

class Upload_to_cdn extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
	}

	function index()
	{
		echo 'LSP-MOBILE APPS';
	}

	function info()
	{
		phpinfo();
	}

	function save_photo()
	{
		$this->db1 = $this->load->database([
			'dsn'				=> '',
			'dbdriver'	=> 'mysqli',
			'port'			=> '3306',
			'hostname'	=> '103.84.195.58',
			'username'	=> 'rynst',
			'password'	=> 'rynstTST2019@#!',
			'database'	=> 'online_certification',
			'debug'			=> FALSE,
		], TRUE);

		if (!in_array($_SERVER['REQUEST_METHOD'], ['POST'])) 
			$this->response(FALSE, ['error' => ['message' => "Authentication is required !", 'code' => 0]]);

		// // 1. First check this Non Form-Data Request
		// $request = json_decode(file_get_contents('php://input'));

		// if (! $request || empty($request)) {
		// 	// 2. Next check Form-Data Request : Using for uploading file/image
		// 	$request = json_decode(json_encode($_POST));
		// }

		// $this->response(FALSE, ['error' => $request]);
		
		$type = $this->input->post('type');		// 'selfie' or 'idcard'
		$card_no = $this->input->post('card_no');
		$company_code = $this->input->post('company_code');

		$err_msg = "";
		if (empty($type))
			$err_msg .= (empty($err_msg) ? "" : ", ")."[type]";
		if (empty($card_no))
			$err_msg .= (empty($err_msg) ? "" : ", ")."[card_no]";
		if (empty($company_code))
			$err_msg .= (empty($err_msg) ? "" : ", ")."[company_code]";

		if (!empty($err_msg))
			$this->response(FALSE, ['error' => ['message' => $err_msg." is required", 'code' => 0]]);

		// BASE destination folder
		$upload_url  = base_url().'assets/img/relax/';
		$upload_path = FCPATH.'assets/img/relax/';
		is_dir($upload_path) OR mkdir($upload_path, 0777, true);
		
		try {
			// Be sure about destination folder
			$upload_url  = $upload_url.$company_code.'/';
			$upload_path = $upload_path.$company_code.'/';
			is_dir($upload_path) OR mkdir($upload_path, 0777, true);

			// File naming conversion
			$prefix = '';
			$middle = $card_no;
			$suffix = $type;
			$filename = implode("-", [$middle, $suffix]);

			// Save file to local
			$config = ['upload_path' => $upload_path, 'file_name' => $filename];
			list($return, $result) = $this->save_file_local('userfile', $config);
			if (!$return) $this->response(FALSE, ['error' => $result]);

			$filename = $result['file_name'];
			$link = $upload_url.$filename;

			if ($type == "selfie") {
				// Update table members
				if (FALSE === $result = $this->db1->update('members', ['photo' => $filename], ['identity_card' => $card_no]))
					$this->response(FALSE, ['error' => $this->db1->error()]);
			}

			if ($type == "idcard") {
				// Update table members
				if (FALSE === $result = $this->db1->update('members', ['photo_idcard' => $filename], ['identity_card' => $card_no]))
					$this->response(FALSE, ['error' => $this->db1->error()]);
			}

			$this->response(TRUE, [
				'message' => 'Request has been responded', 
				'result' => $link
			]);

		}
		catch (Exception $e)
		{
			$this->response(FALSE, ['error' => ['message' => $e->getMessage(), 'code' => 0]]);
		}
	}

	function save_photo_exam()
	{
		$this->db1 = $this->load->database([
			'dsn'				=> '',
			'dbdriver'	=> 'mysqli',
			'port'			=> '3306',
			'hostname'	=> '103.84.195.58',
			'username'	=> 'rynst',
			'password'	=> 'rynstTST2019@#!',
			'database'	=> 'online_certification',
			'debug'			=> FALSE,
		], TRUE);

		if (!in_array($_SERVER['REQUEST_METHOD'], ['POST'])) 
			$this->response(FALSE, ['error' => ['message' => "Authentication is required !", 'code' => 0]]);

		$type = $this->input->post('type');		// 'selfie' or 'idcard'
		$card_no = $this->input->post('card_no');
		$schedule_request_id = $this->input->post('schedule_request_id');
		$member_id = $this->input->post('member_id');

		$err_msg = "";
		if (empty($type))
			$err_msg .= (empty($err_msg) ? "" : ", ")."[type]";
		if (empty($card_no))
			$err_msg .= (empty($err_msg) ? "" : ", ")."[card_no]";
		if (empty($schedule_request_id))
			$err_msg .= (empty($err_msg) ? "" : ", ")."[schedule_request_id]";
		if (empty($member_id))
			$err_msg .= (empty($err_msg) ? "" : ", ")."[member_id]";

		if (!empty($err_msg))
			$this->response(FALSE, ['error' => ['message' => $err_msg." is required", 'code' => 0]]);

		// BASE destination folder
		$upload_url  = base_url().'assets/img/relax/';
		$upload_path = FCPATH.'assets/img/relax/';
		is_dir($upload_path) OR mkdir($upload_path, 0777, true);
		
		try {
			// Be sure about destination folder
			$upload_url = $upload_url.'schedule_request_id/'.$schedule_request_id.'/';
			$upload_path = $upload_path.'schedule_request_id/'.$schedule_request_id.'/';
			is_dir($upload_path) OR mkdir($upload_path, 0777, true);

			// File naming conversion
			$prefix = '';
			$middle = $card_no;
			$suffix = $type;
			$filename = implode("-", [$middle, $suffix]);

			// Save file to local
			$config = ['upload_path' => $upload_path, 'file_name' => $filename];
			list($return, $result) = $this->save_file_local('userfile', $config);
			if (!$return) $this->response(FALSE, ['error' => $result]);

			$filename = $result['file_name'];
			$link = $upload_url.$filename;

			if (FALSE === $row = $this->db1->get_where('participant_photo', [
				'schedule_request_id' => $schedule_request_id,
				'member_id'  					=> $member_id,
				'type'               	=> $type,
			])->row())
				$this->response(FALSE, ['error' => $this->db1->error()]);
			
			if (!$row) {
				if (FALSE === $result = $this->db1->insert('participant_photo', [
					'schedule_request_id' => $schedule_request_id,
					'member_id'  					=> $member_id,
					'type'               	=> $type,
					'filename'      			=> $filename,
					'created_at'					=> date('Y-m-d H:i:s'),
				]))
					$this->response(FALSE, ['error' => $this->db1->error()]);
			} else {
				if (FALSE === $result = $this->db1->update('participant_photo', [
					'filename'      			=> $filename,
					'updated_at'					=> date('Y-m-d H:i:s'),
				], [
					'schedule_request_id' => $schedule_request_id,
					'member_id'  					=> $member_id,
					'type'               	=> $type,
				]))
					$this->response(FALSE, ['error' => $this->db1->error()]);
			}

			$this->response(TRUE, [
				'message' => 'Request has been responded', 
				'result' => $link
			]);

		}
		catch (Exception $e)
		{
			$this->response(FALSE, ['error' => ['message' => $e->getMessage(), 'code' => 0]]);
		}
	}

	/*
	 * For saving file to local
	 * 
	 */
	private function save_file_local($user_file, $config = []) 
	{
		$this->load->library('upload', $config);

		if (! isset($_FILES[$user_file]))
			return [FALSE, ['message' => 'error_upload_userfile_not_exist', 'code' => 0]];

		if (is_array($_FILES[$user_file]['name']))
			return [FALSE, ['message' => 'error_upload_userfile_cannot_be_array', 'code' => 0]];

		// Be sure file name not empty
		if (empty($config['file_name']))
			return [FALSE, ['message' => 'error_filename_not_set', 'code' => 0]];

		$ext = pathinfo($_FILES[$user_file]['name'], PATHINFO_EXTENSION);
		$config = [
			'upload_path'   => $config['upload_path'] 	?? null,
			'allowed_types' => $config['allowed_types'] ?? 'gif|jpg|png|pdf|jpeg',
			'overwrite'     => $config['overwrite']     ?? TRUE,
			'max_size'      => $config['max_size']      ?? 5120,
			'file_name'     => $config['file_name'].".$ext",
		];

		$this->upload->initialize($config);

		if (! $this->upload->do_upload($user_file)) {
			return [FALSE, $this->upload->display_errors()];
		} 

		chmod($config['upload_path'].$config['file_name'],0777);
		// chown($config['upload_path'].$config['file_name'], 'nana');
		// chgrp($config['upload_path'].$config['file_name'],"nana");

		return [TRUE, [
			'file_name' => $config['file_name'], 
			'save_path' => $config['upload_path'],
			]
		];
	}

	/*
	 * Standard json output from me antho.firuze@gmail.com
	 * 
	 */
	private function response($status=TRUE, $payload=[], $status_code=200, $exit=TRUE)
	{
		$output['status'] 		  = $status;

    if (ENVIRONMENT != 'production')
			$output['environment'] = ENVIRONMENT;

		if (!$exit)
			return array_merge($output, $payload);

		self::json_out(array_merge($output, $payload), $status_code);
	}

	/**
	 * JSON Output
	 *
	 * @param object|array $result
	 * @return void
	 */
	private function json_out($payload, $status_code=200)
	{
		header("HTTP/1.0 $status_code");
		// === for Allow Cross Domain Webservice ===
		header('Access-Control-Allow-Origin: *');
		header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type");
		header("Access-Control-Allow-Headers: Origin");
		// === for Allow Cross Domain Webservice ===
		header('Accept-Ranges: bytes');
		header('Content-Type: application/json');

		if (is_array($payload)){
			if (count($payload) > 0)
				die(json_encode($payload));
		} else {
			if ($payload || !empty($payload))
				die(json_encode($payload));
		}
	}

}
