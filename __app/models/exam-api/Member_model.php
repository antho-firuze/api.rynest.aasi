<?php if (!defined('BASEPATH')) {exit('No direct script access allowed');}

define('DEPRECATED_DATE', '2020-03-29');

class Member_model extends CI_Model
{

	function __construct()
	{
		parent::__construct();
		$this->load->library(['f', 'lsp']);
		$this->load->database(DB_CONN[HTTP_HOST]);
	}
	
	function is_activated($request)
	{
    // {"phone":"89530769307","fullname":"Antonio Chan","card_no":"3174093686308150"}
		list($success, $return) = $this->f->check_param_required($request, ['fullname','card_no','phone']);
		if (!$success) return [FALSE, $return];
    
		$DB = $this->load->database(DB_CONN[HTTP_HOST.'/app'], TRUE);
		if (!$result = $DB->get_where('pendaftaran_detail', ['no_ktp' => $request->params->card_no]))
			return [FALSE, ['message' => 'Database Error: '.$DB->error()['message']]];		

		if (!$row = $result->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_member_not_found')]];

		if ($row->is_activated)
			return [TRUE, ['message' => $this->f->_err_msg('info_member_has_been_activated')]];

		return [FALSE, ['message' => $this->f->_err_msg('info_member_not_activated_yet')]];
	}

	function activate($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['fullname','card_no','phone']);
		if (!$success) return [FALSE, $return];
    
		$DB = $this->load->database(DB_CONN[HTTP_HOST.'/app'], TRUE);
		if (!$result = $DB->get_where('pendaftaran_detail', ['no_ktp' => $request->params->card_no]))
			return [FALSE, ['message' => 'Database Error: '.$DB->error()['message']]];		

		if (!$row = $result->row()) 
			return [FALSE, ['message' => $this->f->_err_msg('err_member_not_found')]];

		$DB->update('pendaftaran_detail', ['is_activated' => 1], ['no_ktp' => $request->params->card_no]);

		list($success, $return) = $this->lsp->get_member_user($request);
		if (!$success) return [FALSE, $return];
		
		// Update user password became encrypt
		$this->db->update('users', ['password' => $this->lsp->hash($request->user->password)], ['id' => $request->user->id]);
		
		return [TRUE, ['message' => $this->f->_err_msg('success_member_activated')]];
	}

	function upload_photo_activation($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['fullname','card_no','phone']);
		if (!$success) return [FALSE, $return];
		
		$user_file		= 'userfile';
		if (! isset($_FILES[$user_file]))
			return [FALSE, ['message' => $this->f->_err_msg('error_upload_userfile_not_exist')]];

		if (is_array($_FILES[$user_file]['name'])) 
			return [FALSE, ['message' => $this->f->_err_msg('error_upload_userfile_cannot_be_array')]];

		@ini_set('max_execution_time', 5500);
		require_once APPPATH . 'libraries/FTPConnection.php';
		$ftp_server = '49.0.1.74';
		$ftp_port = 21;
		$ftp_user = 'nana';
		$ftp_pass = 'cmhostermania';

		$DB = $this->load->database(DB_CONN[HTTP_HOST.'/app'], TRUE);

		try
		{
			$ftp = new FTPConnection($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
			$f = '_image_'.date('ymd').time().'.'.pathinfo($_FILES[$user_file]['name'], PATHINFO_EXTENSION);
			$s = $_FILES[$user_file]['tmp_name'];
			$r = "/var/www/html/assets/img/user/$f";
			$ftp->uploadFile($s, $r);

			// update on db: online_certification
			$this->db->update('members', ['photo' => $f], ['identity_card' => $request->params->card_no]);
			// update on db: db_lspv2
			$DB->update('pendaftaran_detail', ['photo' => $f], ['no_ktp' => $request->params->card_no]);

			$link = "http://49.0.1.74/assets/img/user/$f";
			return [TRUE, ['result' => ['uploaded' => true, 'file_name' => $_FILES[$user_file]['name'], 'link' => $link]]];
		}	
		catch (Exception $e)
		{
			return [FALSE, ['result' => ['uploaded' => false, 'file_name' => $_FILES[$user_file]['name'], 'message' => $e->getMessage()]]];
		}
	}

	function upload_photo_idcard($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['fullname','card_no','phone']);
		if (!$success) return [FALSE, $return];
		
		$user_file		= 'userfile';
		if (! isset($_FILES[$user_file]))
			return [FALSE, ['message' => $this->f->_err_msg('error_upload_userfile_not_exist')]];

		if (is_array($_FILES[$user_file]['name'])) 
			return [FALSE, ['message' => $this->f->_err_msg('error_upload_userfile_cannot_be_array')]];

		@ini_set('max_execution_time', 5500);
		require_once APPPATH . 'libraries/FTPConnection.php';
		$ftp_server = '49.0.1.74';
		$ftp_port = 21;
		$ftp_user = 'nana';
		$ftp_pass = 'cmhostermania';

		$DB = $this->load->database(DB_CONN[HTTP_HOST.'/app'], TRUE);

		try
		{
			$ftp = new FTPConnection($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
			$f = '_image_'.$request->params->card_no.'.'.pathinfo($_FILES[$user_file]['name'], PATHINFO_EXTENSION);
			$s = $_FILES[$user_file]['tmp_name'];
			$r = "/var/www/html/assets/img/user/$f";
			$ftp->uploadFile($s, $r);

			// // update on db: online_certification
			// $this->db->update('members', ['photo' => $f], ['identity_card' => $request->params->card_no]);
			// // update on db: db_lspv2
			// $DB->update('pendaftaran_detail', ['photo' => $f], ['no_ktp' => $request->params->card_no]);

			$link = "http://49.0.1.74/assets/img/user/$f";
			return [TRUE, ['result' => ['uploaded' => true, 'file_name' => $_FILES[$user_file]['name'], 'link' => $link]]];
		}	
		catch (Exception $e)
		{
			return [FALSE, ['result' => ['uploaded' => false, 'file_name' => $_FILES[$user_file]['name'], 'message' => $e->getMessage()]]];
		}
	}

	function upload_photo_selfie($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['fullname','card_no','phone']);
		if (!$success) return [FALSE, $return];
		
		$user_file		= 'userfile';
		if (! isset($_FILES[$user_file]))
			return [FALSE, ['message' => $this->f->_err_msg('error_upload_userfile_not_exist')]];

		if (is_array($_FILES[$user_file]['name'])) 
			return [FALSE, ['message' => $this->f->_err_msg('error_upload_userfile_cannot_be_array')]];

		@ini_set('max_execution_time', 5500);
		require_once APPPATH . 'libraries/FTPConnection.php';
		$ftp_server = '49.0.1.74';
		$ftp_port = 21;
		$ftp_user = 'nana';
		$ftp_pass = 'cmhostermania';

		$DB = $this->load->database(DB_CONN[HTTP_HOST.'/app'], TRUE);

		try
		{
			$ftp = new FTPConnection($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
			$f = '_image_'.$request->params->card_no.'_selfie.'.pathinfo($_FILES[$user_file]['name'], PATHINFO_EXTENSION);
			$s = $_FILES[$user_file]['tmp_name'];
			$r = "/var/www/html/assets/img/user/$f";
			$ftp->uploadFile($s, $r);

			// // update on db: online_certification
			// $this->db->update('members', ['photo' => $f], ['identity_card' => $request->params->card_no]);
			// // update on db: db_lspv2
			// $DB->update('pendaftaran_detail', ['photo' => $f], ['no_ktp' => $request->params->card_no]);

			$link = "http://49.0.1.74/assets/img/user/$f";
			return [TRUE, ['result' => ['uploaded' => true, 'file_name' => $_FILES[$user_file]['name'], 'link' => $link]]];
		}	
		catch (Exception $e)
		{
			return [FALSE, ['result' => ['uploaded' => false, 'file_name' => $_FILES[$user_file]['name'], 'message' => $e->getMessage()]]];
		}
	}

	function login($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['username','password','date_client','time_client']);
		if (!$success) return [FALSE, $return];
    
		list($success, $return) = $this->lsp->is_valid_auth($request);
		if (!$success) return [FALSE, $return];

		$str = "(
      select t1.member_id, t1.schedule_request_id, t3.name, t3.date, t3.pre, t3.begin, t3.duration, t3.notes
			from schedule_participants t1
			left join schedule_requests t2 on t1.schedule_request_id = t2.id
			left join schedules t3 on t2.schedule_id = t3.id
			where t1.member_id = ? and t3.date = ? 
			and t3.pre <= ? and ADDTIME(t3.begin, SEC_TO_TIME(t3.duration*60)) >= ? and t2.location_id=2
			limit 1
		) g0";
		$table = $this->f->compile_qry($str, [$request->member->member_id, $request->params->date_client, $request->params->time_client, $request->params->time_client]);
		if (!$result = $this->db->from($table)->get())
			return [FALSE, ['message' => 'Database Error: '.$this->db->error()['message']]];

		if (!$row = $result->row())
			return [FALSE, ['message' => $this->f->_err_msg('err_not_in_schedule')]];

		$photo_link = "http://49.0.1.74/assets/img/user/";

		return [TRUE, [
			'message' => $this->f->_err_msg('success_member_in_schedule'),
			'result' => [
				'user' 	 => [
					'fullname'	 => $request->user->fullname,
					'email'			 => $request->user->email,
					'phone'			 => $request->user->phone,
					'photo_link' => $request->member->photo ? $photo_link.$request->member->photo : '',
				],
			],
		]];
	}

	/**
	 * Upload file for attachment email, can be upload multiple file at once
	 *
	 * @param [type] $request
	 * @return void
	 */
	function _upload_photo($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['fullname','card_no','phone']);
		if (!$success) return [FALSE, $return];
		
		$upload_url 	= BASE_URL.'images'.SEPARATOR;
		$upload_path 	= FCPATH.'images'.DIRECTORY_SEPARATOR;
		is_dir($upload_path) OR mkdir($upload_path, 0777, true);
		
		$user_file		= 'userfile';
		if (! isset($_FILES[$user_file]))
			return [FALSE, ['message' => $this->f->_err_msg('error_upload_userfile_not_exist')]];

		function go_upload($request, $config = [], &$result = []) {
			$ci = &get_instance();

			$user_file 				= $config['file'];
			$config = [
				'upload_path' 	=> $config['upload_path'] 	?? null, 
				'allowed_types' => $config['allowed_types'] ?? 'gif|jpg|png|pdf|jpeg', 
				'overwrite' 		=> $config['overwrite'] 		?? TRUE,
				'max_size' 			=> $config['max_size'] 			?? 5120,
				'file_name' 		=> $config['file_name'] 		?? $_FILES[$user_file]['name'],
			];
			$ci->upload->initialize($config);

			if (! $ci->upload->do_upload($user_file)) {
				$result[] = ['uploaded' => false, 'file_name' => $config['file_name'], 'message' => $ci->upload->display_errors()];
			} else {
				$result[] = ['uploaded' => true, 'file_name' => $config['file_name']];
			}
		}

		$config['file'] 					= $user_file;
		$config['upload_path'] 		= $upload_path;
		$config['allowed_types'] 	= 'gif|jpg|png|pdf|jpeg';
		$config['overwrite'] 			= TRUE;
		$config['max_size'] 			= 5120;
		$this->load->library('upload', $config);
		if (is_array($_FILES[$user_file]['name'])) {
			foreach($_FILES[$user_file]['name'] as $key => $val) {
				$user_file_arr			 = 'userfile_array';
				$config['file'] 		 = $user_file_arr;
				$config['file_name'] = 'image_'.date('ymd').time().'.'.pathinfo($_FILES[$user_file]['name'][$key], PATHINFO_EXTENSION);

				$_FILES[$user_file_arr]['name'] 			= $_FILES[$user_file]['name'][$key];
				$_FILES[$user_file_arr]['type'] 			= $_FILES[$user_file]['type'][$key];
				$_FILES[$user_file_arr]['tmp_name'] 	= $_FILES[$user_file]['tmp_name'][$key];
				$_FILES[$user_file_arr]['error'] 			= $_FILES[$user_file]['error'][$key];
				$_FILES[$user_file_arr]['size'] 			= $_FILES[$user_file]['size'][$key];
				go_upload($request, $config, $result);
			}
		} else {
			$config['file_name'] = 'image_'.date('ymd').time().'.'.pathinfo($_FILES[$user_file]['name'], PATHINFO_EXTENSION);
			go_upload($request, $config, $result);
		}

		$DB = $this->load->database(DB_CONN[HTTP_HOST.'/app'], TRUE);
		foreach($result as $key => $val) {
			if ($val['uploaded']) {

				// update on db: online_certification
				$this->db->update('members', ['photo' => $val['file_name']], ['identity_card' => $request->params->card_no]);
				// update on db: db_lspv2
				$DB->update('pendaftaran_detail', ['photo' => $val['file_name']], ['no_ktp' => $request->params->card_no]);

				// list($success, $return) = $this->f->dbFind($DB, 'pendaftaran_detail', ['no_ktp' => $request->params->card_no]);
				// if (!$success) {
				// 	$result[$key]['saving'] = 'Failed';
				// } else {
				// 	$DB->update('pendaftaran_detail', ['photo' => $val['file_name']], ['no_ktp' => $request->params->card_no]);
				// 	$result[$key]['saving'] = 'Success';
				// }
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
	function _upload_photo2ftp($request)
	{
		list($success, $return) = $this->f->check_param_required($request, ['fullname','card_no','phone']);
		if (!$success) return [FALSE, $return];
		
		$user_file		= 'userfile';
		if (! isset($_FILES[$user_file]))
			return [FALSE, ['message' => $this->f->_err_msg('error_upload_userfile_not_exist')]];

		@ini_set('max_execution_time', 5500);
		require_once APPPATH . 'libraries/FTPConnection.php';
		$ftp_server = '49.0.1.74';
		$ftp_port = 21;
		$ftp_user = 'nana';
		$ftp_pass = 'cmhostermania';

		$DB = $this->load->database(DB_CONN[HTTP_HOST.'/app'], TRUE);

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

					// update on db: online_certification
					$this->db->update('members', ['photo' => $f], ['identity_card' => $request->params->card_no]);
					// update on db: db_lspv2
					$DB->update('pendaftaran_detail', ['photo' => $f], ['no_ktp' => $request->params->card_no]);

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

				// update on db: online_certification
				$this->db->update('members', ['photo' => $f], ['identity_card' => $request->params->card_no]);
				// update on db: db_lspv2
				$DB->update('pendaftaran_detail', ['photo' => $f], ['no_ktp' => $request->params->card_no]);

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

				return [TRUE, ['message' => $this->f->_err_msg('success_file_transfered')]];
				
				// $conn_id = ftp_connect($ftp_server);
				// $login_result = ftp_login($conn_id, $ftp_user, $ftp_pass);
				// ftp_pasv($conn_id, true);
				// if (ftp_put($conn_id, $r, $s, FTP_BINARY, 0)) {
				// 	return [TRUE, ['message' => $this->f->_err_msg('success_file_transfered')]];
				// } else {
				// 	return [FALSE, ['message' => $this->f->_err_msg('error_file_transfer')]];
				// }

		}
		catch (Exception $e)
		{
				return [FALSE, ['message' => $e->getMessage()]];
		}

	}

}