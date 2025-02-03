<?php if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

class Member4_model extends CI_Model
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
		];

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
}
