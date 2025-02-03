<?php if (!defined('BASEPATH')) {exit('No direct script access allowed');}

class Member2_model extends CI_Model
{

	function __construct()
	{
		parent::__construct();
		$this->load->library(['f', 'lsp']);
		$this->load->database(DB_CONN[HTTP_HOST]);
	}
	
	function check_token($request)
	{
		list($success, $return) = $this->lsp->is_valid_token($request);
		if (!$success) return [FALSE, $return];

		return [TRUE, ['message' => $this->f->_msg('success_token_valid')]];
	}

	function schedule($request)
	{
		list($success, $return) = $this->lsp->is_valid_token($request);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->get_user_member($request);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->get_member_schedule($request);
		if (!$success) return [FALSE, $return];

		// Schedule
		$schedule_date = date_format(date_create($request->schedule->date), "d M Y");
		$open_time = date_format(date_create($request->schedule->open_registration), "H:i");
		$close_time = date_format(date_create($request->schedule->close_registration), "H:i");
		$duration = $request->schedule->duration . " Menit";

		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result' => [
				'schedule'	=> [
					'date'			 => $schedule_date,
					'open_time'	 => $open_time,
					'close_time' => $close_time,
					'duration' 	 => $duration,
				],
			],
		]];
	}

	function login($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['username','password','date_client','time_client']);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->is_valid_auth($request);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->lsp->get_member_schedule($request);
		if (!$success) return [FALSE, $return];

		if ($request->schedule->location_id != 2)
			return [FALSE, ['message' => $this->f->_msg('err_member_not_in_mobile')]];

		$this->lsp->get_member_photo($request);

		list($success, $return) = $this->lsp->generate_token($request);
		if (!$success) return [FALSE, $return];

		// Generate Serial Number
		$dt_client = date_create($request->params->date_client .' '. $request->params->time_client);
		$serialnumber = str_pad($request->schedule->schedule_request_id, 6, "0", STR_PAD_LEFT) . date_format($dt_client, "YmdHis");

		// Schedule
		$schedule_date = date_format(date_create($request->schedule->date), "d M Y");
		$open_time = date_format(date_create($request->schedule->open_registration), "H:i");
		$close_time = date_format(date_create($request->schedule->close_registration), "H:i");
		$duration = $request->schedule->duration . " Menit";

		// Get member activation status (on Server App)
		$DB = $this->load->database(DB_CONN['api.lsp-ps.id/app'], TRUE);
		if (!$result = $DB->get_where('pendaftaran_detail', ['no_ktp' => $request->member->identity_card]))
			return [FALSE, ['message' => 'Database Error: '.$DB->error()['message']]];

		if (!$row = $result->row())
			return [FALSE, ['message' => $this->f->_msg('err_member_not_found')]];
		
		$is_activated = $row->is_activated;

		return [TRUE, [
			'message' => $this->f->_msg('success_member_in_schedule'),
			'result' => [
				'token'    	=> $request->token,
				'user'      => [
					'fullname'    => $request->user->fullname,
					'email'       => $request->user->email,
					'phone'       => $request->user->phone,
					'card_no'     => $request->member->identity_card,
					'serialnumber'=> $serialnumber,
					'is_activated'=> $is_activated,
				],
				'schedule'	=> [
					'date'			 => $schedule_date,
					'open_time'	 => $open_time,
					'close_time' => $close_time,
					'duration' 	 => $duration,
				],
				'photo'			=> $request->photo,
			],
		]];
	}

	function is_activated($request)
	{
		// {"phone":"89530769307","fullname":"Antonio Chan","card_no":"3174093686308150"}
		list($success, $return) = $this->f->check_param_required($request, ['fullname','card_no','phone']);
		if (!$success) return [FALSE, $return];

		$DB = $this->load->database(DB_CONN['api.lsp-ps.id/app'], TRUE);
		if (!$result = $DB->get_where('pendaftaran_detail', ['no_ktp' => $request->params->card_no]))
			return [FALSE, ['message' => 'Database Error: '.$DB->error()['message']]];

		if (!$row = $result->row())
			return [FALSE, ['message' => $this->f->_msg('err_member_not_found')]];

		if (!$row->is_activated)
			return [FALSE, ['message' => $this->f->_msg('info_member_not_activated_yet')]];
			
		list($success, $return) = $this->lsp->get_member_user($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->get_member_schedule($request);
		if (!$success) return [FALSE, $return];

		$this->lsp->get_member_photo($request);

		return [TRUE, [
			'message' => $this->f->_msg('info_member_has_been_activated'),
			'result'	=> ['photo' => $request->photo],
		]];
	}

	function activate($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['fullname','card_no','phone']);
		if (!$success) return [FALSE, $return];

		$DB = $this->load->database(DB_CONN['api.lsp-ps.id/app'], TRUE);
		if (!$result = $DB->get_where('pendaftaran_detail', ['no_ktp' => $request->params->card_no]))
			return [FALSE, ['message' => 'Database Error: '.$DB->error()['message']]];

		if (!$row = $result->row())
			return [FALSE, ['message' => $this->f->_msg('err_member_not_found')]];

		$DB->update('pendaftaran_detail', ['is_activated' => 1], ['no_ktp' => $request->params->card_no]);

		list($success, $return) = $this->lsp->get_member_user($request);
		if (!$success) return [FALSE, $return];
		
		// Update user password became encrypt
		$this->db->update('users', ['password' => $this->lsp->hash($request->user->password)], ['id' => $request->user->id]);
		
		return [TRUE, ['message' => $this->f->_msg('success_member_activated')]];
	}

	function upload_photo_idcard($request)
	{
		if (isset($request->params))
			$request->params->type = "idcard";
		
		list($success, $return) = $this->f->check_param_required($request, ['card_no']);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->get_member_user($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->get_member_schedule($request);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->_upload_photo_base($request);
		if (!$success) return [FALSE, $return];
		
		// return [TRUE, $return];
		return [TRUE, [
			'message' => $this->f->_msg('success_photo_upload'),
			'result'	=> $return,
		]];
	}

	function upload_photo_activation($request)
	{
		if (isset($request->params))
			$request->params->type = "activation";
			
		list($success, $return) = $this->f->check_param_required($request, ['card_no']);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->get_member_user($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->get_member_schedule($request);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->_upload_photo_base($request);
		if (!$success) return [FALSE, $return];
		
		// return [TRUE, $return];
		return [TRUE, [
			'message' => $this->f->_msg('success_photo_upload'),
			'result'	=> $return,
		]];
	}

	function upload_photo_exam_start($request)
	{
		if (isset($request->params))
			$request->params->type = "exam_start";
			
		list($success, $return) = $this->f->check_param_required($request, ['card_no']);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->get_member_user($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->get_member_schedule($request);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->_upload_photo_base($request);
		if (!$success) return [FALSE, $return];
		
		// return [TRUE, $return];
		return [TRUE, [
			'message' => $this->f->_msg('success_photo_upload'),
			'result'	=> $return,
		]];
	}

	function upload_photo_exam_finish($request)
	{
		if (isset($request->params))
			$request->params->type = "exam_finish";
			
		list($success, $return) = $this->f->check_param_required($request, ['card_no']);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->get_member_user($request);
		if (!$success) return [FALSE, $return];
		
		list($success, $return) = $this->lsp->get_member_schedule($request);
		if (!$success) return [FALSE, $return];

		list($success, $return) = $this->_upload_photo_base($request);
		if (!$success) return [FALSE, $return];
		
		// return [TRUE, $return];
		return [TRUE, [
			'message' => $this->f->_msg('success_photo_upload'),
			'result'	=> $return,
		]];
	}

	function _upload_photo_base($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['card_no','type']);
		if (!$success) return [FALSE, $return];
		
		$user_file        = 'userfile';
		if (! isset($_FILES[$user_file]))
			return [FALSE, ['message' => $this->f->_msg('error_upload_userfile_not_exist')]];

		if (is_array($_FILES[$user_file]['name']))
			return [FALSE, ['message' => $this->f->_msg('error_upload_userfile_cannot_be_array')]];

		@ini_set('max_execution_time', 5500);
		require_once APPPATH . 'libraries/FTPConnection.php';
		$ftp_server = '49.0.1.74';
		$ftp_port = 21;
		$ftp_user = 'nana';
		$ftp_pass = 'cmhostermania';

		try
		{
			$ftp = new FTPConnection($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
			$ext = pathinfo($_FILES[$user_file]['name'], PATHINFO_EXTENSION);
			$card_no = $request->params->card_no;
			$type = $request->params->type;
			$filename = "$card_no-$type.$ext";
			$s = $_FILES[$user_file]['tmp_name'];
			// $base_dir = "/var/www/html/assets/img/user";
			$base_dir = "";

			$sub_dir = $request->schedule->schedule_request_id;
			if ($ftp->mk_dir($base_dir, $sub_dir)) {
				$remote_file = $base_dir .'/'. $sub_dir .'/'.$filename;
				$ftp->uploadFile($s, $remote_file);

				$result = $this->db->get_where('participant_photo', [
					'schedule_request_id' => $request->schedule->schedule_request_id,
					'member_id'  					=> $request->member->member_id,
					'type'               	=> $request->params->type,
				]);
				if (!$result)
					return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];
				
				if (!$row = $result->row()) {
					$result = $this->db->insert('participant_photo', [
						'schedule_request_id' => $request->schedule->schedule_request_id,
						'member_id'  					=> $request->member->member_id,
						'type'               	=> $request->params->type,
						'filename'      			=> $filename,
						'created_at'					=> date('Y-m-d H:i:s'),
					]);
					if (!$result)
						return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];
				} else {
					$result = $this->db->update('participant_photo', [
						'filename'      			=> $filename,
						'updated_at'					=> date('Y-m-d H:i:s'),
					], [
						'schedule_request_id' => $request->schedule->schedule_request_id,
						'member_id'  					=> $request->member->member_id,
						'type'               	=> $request->params->type,
					]);
					if (!$result)
						return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];
				}

				$link = "http://49.0.1.74/assets/img/user/$sub_dir/$filename";
				return [TRUE, ['link' => $link]];
			}
		}
		catch (Exception $e)
		{
			return [FALSE, ['message' => $e->getMessage()]];
		}
	}

	/**
	 * Upload file for attachment email, can be upload multiple file at once
	 *
	 * @param [type] $request
	 * @return void
	 */
	function _upload_photos($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['fullname','card_no','phone']);
		if (!$success) return [FALSE, $return];
		
		$upload_url     = BASE_URL.'images'.SEPARATOR;
		$upload_path     = FCPATH.'images'.DIRECTORY_SEPARATOR;
		is_dir($upload_path) OR mkdir($upload_path, 0777, true);
		
		$user_file        = 'userfile';
		if (! isset($_FILES[$user_file]))
				return [FALSE, ['message' => $this->f->_msg('error_upload_userfile_not_exist')]];

		function go_upload($request, $config = [], &$result = []) {
			$ci = &get_instance();

			$user_file                 = $config['file'];
			$config = [
				'upload_path'     => $config['upload_path']     ?? null,
				'allowed_types' => $config['allowed_types'] ?? 'gif|jpg|png|pdf|jpeg',
				'overwrite'         => $config['overwrite']         ?? TRUE,
				'max_size'             => $config['max_size']             ?? 5120,
				'file_name'         => $config['file_name']         ?? $_FILES[$user_file]['name'],
			];
			$ci->upload->initialize($config);

			if (! $ci->upload->do_upload($user_file)) {
				$result[] = ['uploaded' => false, 'file_name' => $config['file_name'], 'message' => $ci->upload->display_errors()];
			} else {
				$result[] = ['uploaded' => true, 'file_name' => $config['file_name']];
			}
		}

		$config['file']                     = $user_file;
		$config['upload_path']         = $upload_path;
		$config['allowed_types']     = 'gif|jpg|png|pdf|jpeg';
		$config['overwrite']             = TRUE;
		$config['max_size']             = 5120;
		$this->load->library('upload', $config);
		if (is_array($_FILES[$user_file]['name'])) {
			foreach($_FILES[$user_file]['name'] as $key => $val) {
				$user_file_arr             = 'userfile_array';
				$config['file']          = $user_file_arr;
				$config['file_name'] = 'image_'.date('ymd').time().'.'.pathinfo($_FILES[$user_file]['name'][$key], PATHINFO_EXTENSION);

				$_FILES[$user_file_arr]['name']             = $_FILES[$user_file]['name'][$key];
				$_FILES[$user_file_arr]['type']             = $_FILES[$user_file]['type'][$key];
				$_FILES[$user_file_arr]['tmp_name']     = $_FILES[$user_file]['tmp_name'][$key];
				$_FILES[$user_file_arr]['error']             = $_FILES[$user_file]['error'][$key];
				$_FILES[$user_file_arr]['size']             = $_FILES[$user_file]['size'][$key];
				go_upload($request, $config, $result);
			}
		} else {
			$config['file_name'] = 'image_'.date('ymd').time().'.'.pathinfo($_FILES[$user_file]['name'], PATHINFO_EXTENSION);
			go_upload($request, $config, $result);
		}

		$DB = $this->load->database(DB_CONN['api.lsp-ps.id/app'], TRUE);
		foreach($result as $key => $val) {
			if ($val['uploaded']) {

				// update on db: online_certification
				$this->db->update('members', ['photo' => $val['file_name']], ['identity_card' => $request->params->card_no]);
				// update on db: db_lspv2
				$DB->update('pendaftaran_detail', ['photo' => $val['file_name']], ['no_ktp' => $request->params->card_no]);
			}
		}

		return [TRUE, ['result' => $result]];
	}

	/**
	 * Upload file/photo, can be upload multiple file/photo at once
	 *
	 * @param [type] $request
	 * @return void
	 */
	function _upload_photos2ftp($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['fullname','card_no','phone']);
		if (!$success) return [FALSE, $return];
		
		$user_file        = 'userfile';
		if (! isset($_FILES[$user_file]))
			return [FALSE, ['message' => $this->f->_msg('error_upload_userfile_not_exist')]];

		@ini_set('max_execution_time', 5500);
		require_once APPPATH . 'libraries/FTPConnection.php';
		$ftp_server = '49.0.1.74';
		$ftp_port = 21;
		$ftp_user = 'nana';
		$ftp_pass = 'cmhostermania';

		// $DB = $this->load->database(DB_CONN['api.lsp-ps.id/app'], TRUE);

		if (is_array($_FILES[$user_file]['name'])) {
			$result = [];
			foreach($_FILES[$user_file]['name'] as $key => $val) {
				try
				{
					$ftp = new FTPConnection($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
					$f = '_image_'.date('ymd').time().'.'.pathinfo($_FILES[$user_file]['name'][$key], PATHINFO_EXTENSION);
					$s = $_FILES[$user_file]['tmp_name'][$key];
					$r = "/var/www/html/assets/img/user/$f";
					$ftp->uploadFile($s, $r);

					$link = "http://49.0.1.74/assets/img/user/$f";
					$result[] = ['uploaded' => true, 'file_name' => $_FILES[$user_file]['name'][$key], 'link' => $link];
				}
				catch (Exception $e)
				{
					$result[] = ['uploaded' => false, 'file_name' => $_FILES[$user_file]['name'][$key], 'message' => $e->getMessage()];
				}
			}

			return [FALSE, ['result' => $result]];

		} else {
			try
			{
				$ftp = new FTPConnection($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
				$f = '_image_'.date('ymd').time().'.'.pathinfo($_FILES[$user_file]['name'], PATHINFO_EXTENSION);
				$s = $_FILES[$user_file]['tmp_name'];
				$r = "/var/www/html/assets/img/user/$f";
				$ftp->uploadFile($s, $r);

				$link = "http://49.0.1.74/assets/img/user/$f";
				return [TRUE, ['result' => ['uploaded' => true, 'file_name' => $_FILES[$user_file]['name'], 'link' => $link]]];
			}
			catch (Exception $e)
			{
				return [FALSE, ['result' => ['uploaded' => false, 'file_name' => $_FILES[$user_file]['name'], 'message' => $e->getMessage()]]];
			}
		}
	}

	function _ftp($request)
	{
		@ini_set('max_execution_time', 5500);
		require_once APPPATH . 'libraries/FTPConnection.php';
		try
		{
			$ftp_server = '49.0.1.74';
			$ftp_port = 21;
			$ftp_user = 'nana';
			$ftp_pass = 'cmhostermania';

			$f = '_image_1911151573773888.jpg';
			$s = FCPATH.'images'.DIRECTORY_SEPARATOR.$f;
			$r = "/var/www/html/assets/img/user/$f";

			$ftp = new FTPConnection($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
			$ftp->uploadFile($s, $r);

			return [TRUE, ['message' => $this->f->_msg('success_file_transfered')]];
		}
		catch (Exception $e)
		{
			return [FALSE, ['message' => $e->getMessage()]];
		}

	}

}
