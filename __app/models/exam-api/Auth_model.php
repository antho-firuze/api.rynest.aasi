<?php if (!defined('BASEPATH')) {exit('No direct script access allowed');}

class Auth_model extends CI_Model
{
	private $table_user = 'users';						 
	private $table_session = 'login_session';					 
	private $login_token_expiration = 60*60*24; // second*minute*hour

	private $hash_method = 'bcrypt';	// sha1 or bcrypt or md5, bcrypt is STRONGLY recommended
	private $min_password_length = 8;
	private $max_password_length = 0;
	private $max_login_attempts = 3;
	private $lockout_time = 60*3;			// second*minute
	private $sender_id = 1;

	function __construct()
	{
		parent::__construct();
		$this->load->library(['f', 'lsp']);
		$this->load->database(DB_CONN[HTTP_HOST]);

		$params['rounds'] 		 = 8;
		$params['salt_prefix'] = version_compare(PHP_VERSION, '5.3.7', '<') ? '$2a$' : '$2y$';
		$this->load->library('bcrypt',$params);
	}
	
	function check_token($request)
	{
		if (!$this->f->is_valid_token($request->token))
			return [FALSE, ['error' => $this->f->error()]];

		$request->user_id = $this->f->result()->sub;

		list($return, $result) = $this->_check_single_device_login($request);
		if (!$return) return [FALSE, ['error' => $result]];
		
		return [TRUE, [
			'message' => $this->f->_msg('success_token_valid'),
			// 'payload'	=> $result,
		]];
	}

	/**
	 * Method for register, 
	 *
	 * @param json_object $request
	 * @return void
	 */
	function register($request) 
	{
		if (!$this->f->check_param_required($request, ['username','password','fullname','phone']))
			return [FALSE, ['error' => $this->f->error()]];

		$identifier_type = 'username';
		if ($this->f->is_this_email($request->params->username)) {
			$identifier_type = 'email';
		} else if ($this->f->is_this_phone($request->params->username)) {
			$identifier_type = 'username';
		}
		
		if (FALSE === $row = $this->db->get_where($this->table_user, [$identifier_type => $request->params->username])->row())
			return [FALSE, ['error' => $this->db->error()]];		

		if ($row) 
			return [FALSE, ['error' => $this->f->_msg_code('err_username_already_exists')]];

		$data = [
			'email' 			=> $request->params->username,
			'password'		=> $this->encrypt_password($request->params->password),
			'phone'				=> $request->params->phone,
			'created_on'	=> time(),
			'last_login'	=> time(),
			'active'			=> 1,
			'fullname'		=> $request->params->fullname ?? '',
		];
		if (FALSE === $this->db->insert($this->table_user, $data))
			return [FALSE, ['error' => $this->db->error()]];
		
		$data['id'] = $this->db->insert_id();
		$row = (object)$data;

		$token = $this->f->get_token([
			'sub' => $row->id, 
			'iat' => strtotime(date('Y-m-d H:i:s')),
		]);
		// Invalidate old session
		$request->user_id = $row->id;
		$request->token = $token;
		list($return, $result) = $this->_revalidate_session($request);
		if (!$return) return [FALSE, ['error' => $result]];

		// Check data from table Members
		list($return, $result) = $this->_check_member($request->user_id, $request->params->fullname, $row->phone);
		if (!$return) return [FALSE, ['error' => $result]];
		
		return [TRUE, [
			'message' => $this->f->lang('success_register'),
			'result' => [
				'token' => $token,
			], 
		]];
	}

	/**
	 * Method for login, with checking of login attempt and generate session token
	 *
	 * @param json_object $request
	 * @return void
	 */
	function login($request)
	{
		if (!$this->f->check_param_required($request, ['username','password']))
			return [FALSE, ['error' => $this->f->error()]];

		$identifier_type = 'username';
		if ($this->f->is_this_email($request->params->username)) {
			$identifier_type = 'email';
		} else if ($this->f->is_this_phone($request->params->username)) {
			$identifier_type = 'username';
		}
		
		if (FALSE === $row = $this->db->get_where($this->table_user, [$identifier_type => $request->params->username])->row())
			return [FALSE, ['error' => $this->db->error()]];		

		if (!$row) 
			return [FALSE, ['error' => $this->f->_msg_code('err_username_or_email_not_found')]];
		
		if (!$row->active) {
			$login_last = date_create($row->login_last);
			$diff = date_diff($login_last,date_create());
			if ($diff->days > 30)
				return [FALSE, ['error' => $this->f->_msg_code('err_username_or_email_not_active')]];
		}
		
		list($return, $result) = $this->is_account_locked($request, $row);
		if (!$return) return [FALSE, ['error' => $result]];

		// LOGIN FAILED steps
		if (! $this->is_correct_password($request->params->password, $row->password)) 
		{
			// LOGIN FAILED => AFTER MAX => RESET login_try
			if ($row->login_try >= $this->max_login_attempts) 
			{
				if (FALSE === $this->db->update($this->table_user, ['login_try' => 1], ['id' => $row->id]))
					return [FALSE, ['error' => $this->db->error()]];
			} 
			// LOGIN FAILED => BEFORE MAX - 1 => LOCKED Account
			else if ($row->login_try == $this->max_login_attempts - 1) 
			{
				if (FALSE === $this->db->update($this->table_user, [
					'account_locked_until' => date('Y-m-d H:i:s', time() + $this->lockout_time), 
					'login_try' => $row->login_try + 1
				], ['id' => $row->id]))
					return [FALSE, ['error' => $this->db->error()]];
			} 
			// LOGIN FAILED => BEFORE MAX => INCREMENT login_try counter
			else 
			{
				if (FALSE === $this->db->update($this->table_user, ['login_try' => $row->login_try + 1], ['id' => $row->id]))
					return [FALSE, ['error' => $this->db->error()]];
			}
			return [FALSE, ['error' => $this->f->_msg_code('err_login_failed')]];
		} 
		
		// GENERATE TOKEN
		// $dt_client = date_create($request->params->date_client .' '. $request->params->time_client);
		// $dt_server = new DateTime(date('Y-m-d H:i:s'));
		// $token_expired = $dt_server->add(new DateInterval('PT'.$this->login_token_expiration.'S'));

		$token = $this->f->get_token([
			'sub' => $row->id, 
			'iat' => strtotime(date('Y-m-d H:i:s')),
			// 'exp' => strtotime($token_expired->format('Y-m-d H:i:s')),
		]);
		
		// INVALIDATE old session
		$request->user_id = $row->id;
		$request->token = $token;
		list($return, $result) = $this->_revalidate_session($request);
		if (!$return) return [FALSE, ['error' => $result]];
		
		// UPDATE table user with informatif data
		if (FALSE === $this->db->update($this->table_user, 
			[
				'login_last' => date('Y-m-d H:i:s'), 
				'login_try' => 0,
				'active' => 1,
				'account_locked_until' => null,
			], 
			[$identifier_type => $request->params->username] 
		))
			return [FALSE, ['error' => $this->db->error()]];
		
		return [TRUE, [
			'message' => $this->f->lang('success_login'),
			'result' => [
				'token' => $token,
			], 
		]];
	}
	
	/**
	 * Method for login with google_id , 
	 * where google_id is get from googleSignInAccount
	 *
	 * @param json_object $request
	 * @return void
	 */
	function login_with_google_id($request) 
	{
		if (!$this->f->check_param_required($request, ['username','google_id']))
			return [FALSE, ['error' => $this->f->error()]];

		$identifier_type = 'username';
		if ($this->f->is_this_email($request->params->username)) {
			$identifier_type = 'email';
		} else if ($this->f->is_this_phone($request->params->username)) {
			$identifier_type = 'username';
		}
		
		if (FALSE === $row = $this->db->get_where($this->table_user, [$identifier_type => $request->params->username])->row())
			return [FALSE, ['error' => $this->db->error()]];		

		if (!$row) 
		{
			$data = [
				'google_id'		=> $request->params->google_id,
				'email' 			=> $request->params->username,
				'password'		=> '',
				'phone'				=> '',
				'created_on'	=> time(),
				'last_login'	=> time(),
				'active'			=> 1,
				'fullname'		=> $request->params->fullname ?? '',
			];
			if (FALSE === $this->db->insert($this->table_user, $data))
				return [FALSE, ['error' => $this->db->error()]];
			
			$data['id'] = $this->db->insert_id();
			$row = (object)$data;
		} 
		else 
		{
			if (empty($row->google_id)) {
				$data = [
					'google_id' => $request->params->google_id,
				];
				if (FALSE === $this->db->update($this->table_user, $data, [$identifier_type => $request->params->username]))
					return [FALSE, ['error' => $this->db->error()]];

				$row->google_id = $request->params->google_id;
			}
		}

		if ($row->google_id != $request->params->google_id)
			return [FALSE, ['error' => $this->f->_msg_code('err_login_with_google_id_failed')]];
		
		$token = $this->f->get_token([
			'sub' => $row->id, 
			'iat' => strtotime(date('Y-m-d H:i:s')),
		]);
		// Invalidate old session
		$request->user_id = $row->id;
		$request->token = $token;
		list($return, $result) = $this->_revalidate_session($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (FALSE === $this->db->update($this->table_user, 
			['login_last' => date('Y-m-d H:i:s'), 'login_try' => 0], 
			[$identifier_type => $request->params->username, 'active' => 1] 
		))
			return [FALSE, ['error' => $this->db->error()]];

		// Check data from table Members
		list($return, $result) = $this->_check_member($request->user_id, $request->params->fullname);
		if (!$return) return [FALSE, ['error' => $result]];
		
		return [TRUE, [
			'message' => $this->f->lang('success_login'),
			'result' => [
				'token' => $token,
			], 
		]];
	}

	/**
	 * Method for login with apple_id , 
	 * where apple_id is get from appleSignInAccount
	 *
	 * @param json_object $request
	 * @return void
	 */
	function login_with_apple_id($request) 
	{
		if (!$this->f->check_param_required($request, ['username','apple_id']))
			return [FALSE, ['error' => $this->f->error()]];

		$identifier_type = 'username';
		if ($this->f->is_this_email($request->params->username)) {
			$identifier_type = 'email';
		} else if ($this->f->is_this_phone($request->params->username)) {
			$identifier_type = 'username';
		}
		
		if (FALSE === $row = $this->db->get_where($this->table_user, [$identifier_type => $request->params->username])->row())
			return [FALSE, ['error' => $this->db->error()]];		

		if (!$row) 
		{
			$data = [
				'apple_id'		=> $request->params->apple_id,
				'email' 			=> $request->params->username,
				'password'		=> '',
				'phone'				=> '',
				'created_on'	=> time(),
				'last_login'	=> time(),
				'active'			=> 1,
				'fullname'		=> $request->params->fullname ?? '',
			];
			if (FALSE === $this->db->insert($this->table_user, $data))
				return [FALSE, ['error' => $this->db->error()]];
			
			$data['id'] = $this->db->insert_id();
			$row = (object)$data;
		} 
		else 
		{
			if (empty($row->apple_id)) {
				$data = [
					'apple_id' => $request->params->apple_id,
				];
				if (FALSE === $this->db->update($this->table_user, $data, [$identifier_type => $request->params->username]))
					return [FALSE, ['error' => $this->db->error()]];

				$row->apple_id = $request->params->apple_id;
			}
		}

		if ($row->apple_id != $request->params->apple_id)
			return [FALSE, ['error' => $this->f->_msg_code('err_login_with_apple_id_failed')]];
		
		$token = $this->f->get_token([
			'sub' => $row->id, 
			'iat' => strtotime(date('Y-m-d H:i:s')),
		]);
		// Invalidate old session
		$request->user_id = $row->id;
		$request->token = $token;
		list($return, $result) = $this->_revalidate_session($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (FALSE === $this->db->update($this->table_user, 
			['login_last' => date('Y-m-d H:i:s'), 'login_try' => 0], 
			[$identifier_type => $request->params->username, 'active' => 1] 
		))
			return [FALSE, ['error' => $this->db->error()]];

		// Check data from table Members
		list($return, $result) = $this->_check_member($request->user_id, $request->params->fullname);
		if (!$return) return [FALSE, ['error' => $result]];
		
		return [TRUE, [
			'message' => $this->f->lang('success_login'),
			'result' => [
				'token' => $token,
			], 
		]];
	}

	/**
	 * Method for login, with checking of login attempt and generate session token
	 *
	 * @param json_object $request
	 * @return void
	 */
	function logout($request)
	{
		if (!$this->f->is_valid_token($request->token))
			return [FALSE, ['error' => $this->f->error()]];
		
		if (!$return = $this->db->delete($this->table_session, ['token' => $request->token])) 
			return [FALSE, ['error' => $this->db->error()]];
		else
			return [TRUE, NULL];
	}
	
	/**
	 * Method for change password
	 *
	 * @param json_object $request
	 * @return void
	 */
	function password_change($request)
	{
		list($return, $result) = $this->lsp->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		if (!$this->f->check_param_required($request, ['password', 'new_password']))
			return [FALSE, ['error' => $this->f->error()]];

		if (FALSE === $row = $this->db->get_where($this->table_user, ['id' => $request->user_id])->row())
			return [FALSE, ['error' => $this->db->error()]];		

		if (!$row) 
			return [FALSE, ['error' => $this->f->_msg_code('err_username_or_email_not_found')]];

		if (!$this->is_correct_password($request->params->password, $row->password)) 
			return [FALSE, ['error' => $this->f->_msg_code('err_old_password')]];
		
		list($success, $result) = $this->is_valid_password($request->params->new_password);
		if (!$success) return [FALSE, ['error' => $result]];
		
		$encrypted = $this->encrypt_password($request->params->new_password);

		if (FALSE === $this->db->update($this->table_user, ['password' => $encrypted], ['id' => $request->user_id]))
			return [FALSE, ['error' => $this->db->error()]];		
		
		return [TRUE, ['message' => $this->f->lang('success_chg_password')]];
	}
	
	/**
	 * Method for forgot password
	 *
	 * @param json_object $request
	 * @return void
	 */
	function password_forgot($request)
	{
		if (!$this->f->check_param_required($request, ['email']))
			return [FALSE, ['error' => $this->f->error()]];

		if (FALSE === $row = $this->db->get_where($this->table_user, ['email' => $request->params->email])->row())
			return [FALSE, ['error' => $this->db->error()]];		

		if (!$row) 
			return [FALSE, ['error' => $this->f->_msg_code('err_email_not_found')]];
		
		// generate random otp
		$otp = $this->f->gen_otp(4);
		if (FALSE === $this->db->update($this->table_user, ['forgotten_password_code' => $otp], ['id' => $row->id]))
			return [FALSE, ['error' => $this->db->error()]];		
		
		$email = [
			'config'		=> $this->lsp->get_mail_config(),
			'sender_id' => $this->sender_id,
			'to' 				=> $row->email,
			'subject' 	=> $this->f->lang('email_subject_forgot_password'),
			'body'			=> $this->f->lang('email_body_forgot_password', [
				'name' 					=> $row->fullname,
				'otp' 					=> $otp,
			]),
		];
		list($return, $result) = $this->f->mail_send($email);
		if (!$return) return [FALSE, ['error' => $result]];

		return [TRUE, [
			'message' => $this->f->lang('info_sent_email_otp'),
			'result' => [
				'otp' => $otp,
			], 
		]];
	}
	
	/**
	 * Method for reset password after process 'password_forgot' 
	 *
	 * @param json_object $request
	 * @return void
	 */
	function password_reset($request)
	{
		if (!$this->f->check_param_required($request, ['otp', 'password']))
			return [FALSE, ['error' => $this->f->error()]];

		if (FALSE === $row = $this->db->get_where($this->table_user, ['forgotten_password_code' => $request->params->otp])->row())
			return [FALSE, ['error' => $this->db->error()]];		

		if (!$row) 
			return [FALSE, ['error' => $this->f->_msg_code('err_otp_not_found')]];
		
		list($success, $result) = $this->is_valid_password($request->params->password);
		if (!$success) return [FALSE, ['error' => $result]];
		
		$encrypted = $this->encrypt_password($request->params->password);

		if (FALSE === $this->db->update($this->table_user, ['password' => $encrypted, 'forgotten_password_code' => null], ['id' => $row->id]))
			return [FALSE, ['error' => $this->db->error()]];		
		
		return [TRUE, ['message' => $this->f->lang('success_reset')]];
	}
	
	function _revalidate_session($request)
	{
		$sql = [
			"delete from $this->table_session where user_id = ? and agent = ? and (token <> ? or token is null)", 
			[$request->user_id, $request->agent, $request->token]
		];
		$sql = $this->f->compile_qry($sql[0], $sql[1]);
		if (FALSE === $result = $this->db->query($sql))
			return [FALSE, $this->db->error()];

		$dt_client = date_create($request->params->date_client .' '. $request->params->time_client);
		if (isset($request->params->dt_client))
			$dt_client = date_create($request->params->dt_client);
		$dt_server = new DateTime(date('Y-m-d H:i:s'));
		$diff = $dt_server->diff($dt_client);
		$minuteDiff = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
		$idn_time = $minuteDiff < 55 ? 'WIB' : ($minuteDiff < 110 ? 'WITA' : 'WIT'); 

		$data = [
			'user_id' => $request->user_id, 
			'agent' => $request->agent, 
			'token' => $request->token,
			'created_at' => date('Y-m-d H:i:s'),
			'login_at'	=> $dt_client->format('Y-m-d H:i:s'),
			'idn_time'	=> $idn_time,
		];
		if (FALSE === $this->db->insert($this->table_session, $data))
			return [FALSE, $this->db->error()];

		// Save to cache
		$id = implode('-', ['session', $request->user_id, $request->agent]);
		// $this->f->save_to_cache($id, $request->token, 60*60*24);
		$this->f->save_to_cache($id, $request->token, -1);

		return [TRUE, NULL];
	}

	function _check_single_device_login($request)
	{
		// Get from cache
		$id = implode('-', ['session', $request->user_id, $request->agent]);
		if (FALSE !== $result = $this->f->get_from_cache($id)) 
			if ($result == $request->token)
				return [TRUE, NULL];

		$this->db->where([
			'user_id' => $request->user_id, 
			'agent' => $request->agent, 
			'token' => $request->token,
		]);
		if (FALSE === $row = $this->f->get_db_row($this->table_session))
			return [FALSE, $this->f->error()];
		elseif (!$row)
			return [FALSE, $this->f->_msg_code('err_token_invalid')];

		// Save to cache
		$this->f->save_to_cache($id, $request->token, 60*60*24);
		return [TRUE, NULL];
	}

	/**
	 * Method for checking password validation
	 *
	 * @param string $password
	 * @return bool
	 */
	private function is_valid_password($password)
	{
		if (strlen($password) < $this->min_password_length)
			return [FALSE, $this->f->_msg_code('err_min_password_length', $this->min_password_length)];
		
		if ($this->max_password_length > 0) 
		{
			if (strlen($password) > $this->max_password_length)
				return [FALSE, $this->f->_msg_code('err_max_password_length', $this->max_password_length)];
		}

		return [TRUE, NULL];
	}
	
	private function is_correct_password($password1, $password2)
	{
		$cbnUser = substr($password2, 0, 5) === '$1c3N' ? TRUE : FALSE; 

		if ($cbnUser) 
		{
			$password1 = hash_hmac('sha1', $password1, 'R@z3rl0ck');
			$password2 = substr_replace($password2, '', 0, 5);
		}

		if (strlen($password2) > 35 && strlen($password2) < 65)
		{
			// BCRYPT
			return $this->bcrypt->verify($password1, $password2);
		}
		else if (strlen($password2) >= 32 && strlen($password2) <= 35)
		{
			// MD5				
			return md5($password1) == $password2;
		} 
		else 
		{
			// PLAIN
			return trim($password1) == $password2;
		}
	}
	
	/**
	 * Method for checking is account locked or not
	 *
	 * @param object $request
	 * @param object $user
	 * @return bool
	 */
	private function is_account_locked($request, $user)
	{
		if (!empty($user->account_locked_until)) 
		{
			$locked_time = date_create($user->account_locked_until);
			$now = date_create(date('Y-m-d H:i:s'));
			if ($locked_time < $now) 
			{
				if (FALSE === $this->db->update($this->table_user, ['account_locked_until' => null], ['id' => $user->id]))
					return [FALSE, $this->db->error()];
			} 
			else 
			{
				$this->load->helper('mydate');
				return [FALSE, $this->f->_msg_code('err_login_attempt_reached', nicetime_lang($user->account_locked_until, $request->idiom))];
			}
		}
		return [TRUE, NULL];
	}

	private function encrypt_password($password)
	{
		// bcrypt
		if ($this->hash_method == 'bcrypt')
		{
			$params['rounds'] 		 = 8;
			$params['salt_prefix'] = version_compare(PHP_VERSION, '5.3.7', '<') ? '$2a$' : '$2y$';
			$this->load->library('bcrypt', $params);
	
			return $this->bcrypt->hash($password);
		}

		// md5
		if ($this->hash_method == 'md5')
		{
			return md5($password);
		}

		// sha1
		if ($this->hash_method == 'sha1')
		{
			return sha1($password);
		}
	}

	function _check_member($user_id, $fullname, $phone = null)
	{
		$this->db->where(['user_id' => $user_id]);
		if (FALSE === $row = $this->f->get_db_row('members'))
			return [FALSE, $this->f->error()];
		elseif (!$row) 
		{
			list($return, $result) = $this->_get_member_id();
			if (!$return) return [FALSE, $result];

			$member_id = $result;

			$data = [
				'member_id'		=> $member_id,
				'company_id' 	=> '0',
				'user_id'			=> $user_id,
				'fullname'		=> $fullname ?? '',
				'gender'			=> '-',
				'phone'				=> $phone,
				'author'			=> 0,
				'created_on'	=> time(),
			];
			if (FALSE === $this->db->insert('members', $data))
				return [FALSE, $this->db->error()];
		}

		return [TRUE, NULL];
	}

	function _get_member_id()
	{
		$sql = [
			"(
				select member_id from members where member_id like ? 
				order by member_id desc limit 1
			) g0", 
			[date('ymd').'%']
		];
		if (FALSE === $result = $this->f->get_db_query($sql))
			return [FALSE, $this->f->error()];
		elseif (!$result) {
			return [TRUE, date('ymd').str_pad(1, 3, '0', STR_PAD_LEFT)];
		} else {
			$num = substr($result[0]->member_id, 6) + 1;
			return [TRUE, date('ymd').str_pad($num, 3, '0', STR_PAD_LEFT)];
		}
	}

	/**
	 * Method for un-register, 
	 *
	 * @param json_object $request
	 * @return void
	 */
	function unregister($request) 
	{
		list($return, $result) = $this->lsp->is_valid_token($request);
		if (!$return) return [FALSE, ['error' => $result]];

		// GET user
		list($return, $result) = $this->lsp->get_user($request->user_id);
		if (!$return) return [FALSE, ['error' => $result]];

		$user = $result;

		if (FALSE === $result = $this->db->update(
			'users',
			[
				'active' => 0,
			],
			[
				'id' => $request->user_id,
			]
		))
			return [FALSE, ['error' => $this->db->error()]];

		$email = [
			'config'		=> $this->lsp->get_mail_config(),
			'sender_id' => $this->sender_id,
			'to' 				=> $user->email,
			'subject' 	=> $this->f->lang('email_subject_unregister'),
			'body'			=> $this->f->lang('email_body_unregister', [
				'name' 					=> $user->fullname,
			]),
		];
		list($return, $result) = $this->f->mail_send($email);
		if (!$return) return [FALSE, ['error' => $result]];
	
	
		return [TRUE, ['message' => $this->f->_msg('success_request_responded')]];
	}

}
