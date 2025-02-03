<?php if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

class Member3_model extends CI_Model
{

	function __construct()
	{
		parent::__construct();
		$this->load->library(['f', 'aasi']);
		$this->load->database(DB_CONN[HTTP_HOST]);
	}

	function profile($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET user
		list($return, $result) = $this->aasi->get_user($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$user = $result;

		// GET company
		list($return, $result) = $this->aasi->get_company($member->company_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$company = $result;

		// return [FALSE, ['error' => $member->identity_card]];
		// table pendaftaran_detail 
		list($return, $result) = $this->aasi->get_pendaftaran_detail($member->identity_card);
		if (!$return) return [FALSE, ['error' => $result]];

		$pendaftaran_detail = $result;

		$data = [
			'id_user' 				=> $user->id,
			'id_member' 			=> $member->id,
			'member_id' 			=> $member->member_id,
			'identity_card' 	=> $member->identity_card,
			'fullname' 				=> $member->fullname,
			'place_of_birth' 	=> $member->place_of_birth,
			'date_of_birth' 	=> $member->date_of_birth,
			'gender' 					=> $member->gender,
			'phone' 					=> $member->phone,
			'photo' 					=> empty($member->photo)
				? $member->photo
				: (substr(strtolower($member->photo), 0, 4) == 'http'
					? $member->photo
					: CDN_PHOTO_URL . $company->kd . '/' . $member->photo),
			'photo_idcard' 		=> empty($member->photo_idcard)
				? $member->photo_idcard
				: (substr(strtolower($member->photo_idcard), 0, 4) == 'http'
					? $member->photo_idcard
					: CDN_PHOTO_URL . $company->kd . '/' . $member->photo_idcard),
			'status' 					=> $pendaftaran_detail == null ? null : $pendaftaran_detail->status,
			'email' 					=> $user->email,
			'company'					=> $company,
			'certificate'			=> null,
			'schedule'				=> null,
			'photos'					=> null,
		];

		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result'	=> $data,
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

	function certificate($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// GET certificate
		list($return, $result) = $this->aasi->get_certificate($member->id);
		if (!$return) return [FALSE, ['error' => $result]];

		$certificate = $result;

		$data = $certificate;

		return [TRUE, [
			'message' => $this->f->_msg('success_request_responded'),
			'result'	=> $data,
		]];
	}

	// function profile_edit($request)
	// {
	// 	list($return, $result) = $this->aasi->is_valid_token($request);
	// 	if (!$return) return [FALSE, ['error' => $result]];

	// 	$data = [];
	// 	foreach ($request->params as $key => $val) {
	// 		if (isset($request->params->{$key}))
	// 			$data[$key] = $val;
	// 	}
	// 	if (count($data) < 1)
	// 		return [FALSE, ['error' => $this->f->_msg_code('err_param_required', 'minimum 1 field')]];

	// 	$data['modified'] = strtotime(date('Y-m-d H:i:s'));

	// 	$data_member = [];
	// 	if ($data['fullname']) $data_member['fullname'] = $data['fullname'];
	// 	if ($data['place_of_birth']) $data_member['place_of_birth'] = $data['place_of_birth'];
	// 	if ($data['date_of_birth']) $data_member['date_of_birth'] = $data['date_of_birth'];
	// 	if ($data['gender']) $data_member['gender'] = $data['gender'];
	// 	if ($data['phone']) $data_member['phone'] = $data['phone'];
	// 	if (FALSE === $result = $this->db->update('members', $data_member, ['user_id' => $request->user_id]))
	// 		return [FALSE, ['error' => $this->db->error()]];

	// 	$data_user = [];
	// 	if ($data['email']) $data_user['email'] = $data['email'];
	// 	if ($data['fullname']) $data_user['fullname'] = $data['fullname'];
	// 	if ($data['phone']) $data_user['phone'] = $data['phone'];
	// 	if (count($data_user) > 0) {
	// 		if (FALSE === $result = $this->db->update('users', $data_user, ['id' => $request->user_id]))
	// 			return [FALSE, ['error' => $this->db->error()]];
	// 	}

	// 	return [TRUE, ['message' => $this->f->lang('success_update')]];
	// }

	// function activate($request)
	// {
	// 	list($return, $result) = $this->aasi->is_valid_token($request);
	// 	if (!$return) return [FALSE, ['error' => $result]];

	// 	// GET member
	// 	list($return, $result) = $this->aasi->get_member($request->user_id);
	// 	if (!$return) return [FALSE, ['error' => $result]];

	// 	$member = $result;

	// 	$DB = $this->load->database(DB_CONN['api.aasi-ps.id/app'], TRUE);
	// 	if (FALSE === $row = $DB->get_where('pendaftaran_detail', ['no_ktp' => $member->identity_card])->row())
	// 		return [FALSE, ['error' => $DB->error()]];

	// 	if (!$row)
	// 		return [FALSE, ['error' => $this->f->_msg_code('err_member_not_found')]];

	// 	if (FALSE === $result = $DB->update('pendaftaran_detail', ['is_activated' => 1], ['no_ktp' => $member->identity_card]))
	// 		return [FALSE, ['error' => $DB->error()]];

	// 	// table users
	// 	$this->db->where(['id' => $request->user_id]);
	// 	if (FALSE === $row = $this->f->get_db_row('users'))
	// 		return [FALSE, ['error' => $this->f->error()]];
	// 	elseif (!$row)
	// 		return [FALSE, ['error' => $this->f->_msg_code('err_record_empty')]];
	// 	else
	// 		$user = $row;

	// 	// Update user password became encrypt
	// 	if (FALSE === $result = $this->db->update('users', ['password' => $this->aasi->hash($user->password)], ['id' => $request->user_id]))
	// 		return [FALSE, ['error' => $this->db->error()]];

	// 	return [TRUE, ['message' => $this->f->_msg('success_member_activated')]];
	// }

	function upload_photo_idcard($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// Create filename without extension
		$filename = "$member->identity_card-idcard";

		// GO Upload to CDN
		list($return, $result) = $this->_upload_to_cdn($filename, 'members', $member->identity_card);
		if (!$return) return [FALSE, ['error' => $result]];

		$linkUrl = $result->link;

		// SAVE TO DATABASE
		if (FALSE === $result = $this->db->update(
			'members',
			['photo_idcard' => $linkUrl],
			['id' => $member->id]
		))
			return [FALSE, $this->db->error()];

		return [TRUE, [
			'message' => $this->f->_msg('success_photo_upload'),
			'result'	=> $linkUrl,
		]];
	}

	function upload_photo_selfie($request)
	{
		list($return, $result) = $this->aasi->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		// GET member
		list($return, $result) = $this->aasi->get_member($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$member = $result;

		// Create filename without extension
		$filename = "$member->identity_card-selfie";

		// GO Upload to CDN
		list($return, $result) = $this->_upload_to_cdn($filename, 'members', $member->identity_card);
		if (!$return) return [FALSE, ['error' => $result]];

		$linkUrl = $result->link;

		// SAVE TO DATABASE
		if (FALSE === $result = $this->db->update(
			'members',
			['photo' => $linkUrl],
			['id' => $member->id]
		))
			return [FALSE, $this->db->error()];

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

	function _upload_photo_for_profile($member_id, $card_no, $type)
	{
		$user_file        = 'userfile';
		if (!isset($_FILES[$user_file]))
			return [FALSE, $this->f->_msg_code('error_upload_userfile_not_exist')];

		if (is_array($_FILES[$user_file]['name']))
			return [FALSE, $this->f->_msg_code('error_upload_userfile_cannot_be_array')];

		@ini_set('max_execution_time', 5500);
		require_once APPPATH . 'libraries/FTPConnection.php';
		$ftp_server = '49.0.1.74';
		$ftp_port = 21;
		$ftp_user = 'nana';
		$ftp_pass = 'cmhostermania';

		try {
			$ftp = new FTPConnection($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
			$ext = pathinfo($_FILES[$user_file]['name'], PATHINFO_EXTENSION);
			// $card_no = $request->params->card_no;
			// $type = $request->params->type;
			$filename = "$card_no-$type.$ext";
			$s = $_FILES[$user_file]['tmp_name'];
			// $base_dir = "/var/www/html/assets/img/user";
			$base_dir = "";

			$sub_dir = $member_id;
			if ($ftp->mk_dir($base_dir, $sub_dir)) {
				$remote_file = $base_dir . '/' . $sub_dir . '/' . $filename;
				$ftp->uploadFile($s, $remote_file);

				if ($type == "selfie") {

					if (FALSE === $result = $this->db->update(
						'members',
						['photo' => $filename],
						['member_id' => $member_id]
					))
						return [FALSE, $this->db->error()];
				} else {

					if (FALSE === $result = $this->db->update(
						'members',
						['photo_idcard' => $filename],
						['member_id' => $member_id]
					))
						return [FALSE, $this->db->error()];
				}

				$link = "http://49.0.1.74/assets/img/user/$sub_dir/$filename";
				return [TRUE, $link];
			}
		} catch (Exception $e) {
			die($e);
			return [FALSE, ['message' => $e->getMessage(), 'code' => 0]];
		}
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
		list($return, $result) = $this->aasi->get_member_schedule($member->id, $member->company_id);
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
		list($return, $result) = $this->aasi->get_member_schedule($member->id, $member->company_id);
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
		list($return, $result) = $this->aasi->get_member_schedule($member->id, $member->company_id);
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
		list($return, $result) = $this->aasi->get_member_schedule($member->id, $member->company_id);
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
}
