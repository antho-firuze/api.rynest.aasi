<?php if (!defined('BASEPATH')) {exit('No direct script access allowed');}

/**
 * Jsonrpc Class
 *
 * This Remote Procedure Call was design for Cross Domain Web Apps & for Mobile Apps 
 *
 * 
 */
class Jsonrpc extends CI_Controller 
{
	/*  
	*  JSONRPC Sample Request:
	Request:
		{
			"id": 1,
			"lang": "id",
			"agent": "android",
			"method": "auth.login",
			"params": {"username":"ahmad.firuze@gmail.com","password":"12345"}
		}
	
	Response Success:
		{
			"id": 1,
			"status": true,
			"message": "Login Success",
			"token": "REMUrktGyRvoWUUgxfUDmVOTOcoskMWVlOaiyk",
			"result": {
				"user_id": "3064",
				"email": "ahmad.firuze@gmail.com",
				"name": "NOVIZAR HADI SAPUTRA"
			}
		}	

	Response Failed:
		{
			"id": 1,
			"status": false,
			"error": {
				"message": "Required parameter: licenseKey",
				"code": 7000
			}
		}	
	*  
	*/
	// ==============================================
	/* JSONRPC Config: Default config      				 */
	// ==============================================
	public $r_method_allowed = ['GET','POST'];
	public $agent = ['android','ios','web','windows'];
	public $languages 	= [
		'us' => ['id'	=> 'us', 'name' => 'English', 	'idiom' => 'english', 	'icon' => 'flag-icon-us'],
		'id' => ['id'	=> 'id', 'name' => 'Indonesia', 'idiom' => 'indonesia', 'icon' => 'flag-icon-id'],
	];

	function __construct()
	{
		parent::__construct();
		
		$this->lang->load('jsonrpc','english');
		
		$this->r_method = $_SERVER['REQUEST_METHOD'];
		if (!in_array($this->r_method, $this->r_method_allowed))
			self::response(FALSE, ['error'	=> self::_msg_code('err_request_method_unsupported')], 403);

		// This request params must be place in body and with header: Content-Type: application/json
		// Request can be taken with :
		// $this->requests = file_get_contents('php://input');
		// or
		// $this->requests = $this->input->raw_input_stream;
		if (in_array($this->r_method, ['POST'])) 
		{
			// 1. First check this Non Form-Data Request
			$request = json_decode(file_get_contents('php://input'));

			if (! $request || empty($request)) {
				// 2. Next check Form-Data Request : Using for uploading file/image
				$request = json_decode(json_encode($_POST));
			}
			
			if (!in_array(gettype($request), ['object', 'array']))
				self::response(FALSE, ['error'	=> self::_msg_code('err_request_invalid')], 200);
			
			$result = $this->requery_request($request);
			if ($result) $this->json_out($result);
		} 
		else if (in_array($this->r_method, ['GET','DELETE'])) 
		{
			$this->params = (object) $this->input->get();

			if (isset($this->params->method) && !empty($this->params->method)) 
			{
				$result = $this->requery_request($this->params);
				if ($result) $this->json_out($result);
			}
		}
	}
	
	/**
	 * Default index, for checking OK or NOT
	 *
	 * @return void
	 */
	function index() { echo 'JSON RPC OK !'; }
	
	/**
	 * Method for checking is request valid?
	 *
	 * @param object $request
	 * @return bool
	 */
	private function is_valid_request($request)
	{
		if (!$request || is_string($request))
			return [FALSE, ['error'	=> self::_msg_code('err_request_invalid')]];
		
		if (!is_object($request))
			return [FALSE, ['error'	=> self::_msg_code('err_request_invalid')]];
		
		list($return, $msg) = $this->is_valid_agent($request);
		if (!$return) return [FALSE, $msg];
		
		list($return, $msg) = $this->is_valid_language($request);
		if (!$return) return [FALSE, $msg];
		
		return [TRUE, NULL];
	}
	
	/**
	 * Method for checking request agent
	 *
	 * @param object $request
	 * @return bool
	 */
	private function is_valid_agent($request)
	{
		if (!isset($request->agent))
			return [FALSE, ['error'	=> self::_msg_code('err_agent_required')]];

		if (!in_array($request->agent, $this->agent))
			return [FALSE, ['error'	=> self::_msg_code('err_agent_unsupported', "{agent: $request->agent}")]];
		
		return [TRUE, NULL];
	}
	
	/**
	 * Method for checking request language
	 *
	 * @param object $request
	 * @return bool
	 */
	private function is_valid_language($request)
	{
		if (!isset($request->lang))
			return [FALSE, ['error'	=> self::_msg_code('err_lang_required')]];

		if (!in_array($request->lang, array_keys($this->languages))) 
			return [FALSE, ['error'	=> self::_msg_code('err_param_unsupported', "{lang: $request->lang}")]];

		$request->idiom = $this->languages[$request->lang]['idiom'];
		$this->lang->load('jsonrpc', $request->idiom);
		
		return [TRUE, NULL];
	}
	
	/**
	 * Method for checking request params, is valid JSON Object or not?
	 *
	 * @param object $request
	 * @return void
	 */
	private	function pre_checking_params($request){
		if (isset($request->params) || !empty($request->params))
			if (is_string($request->params))
				$request->params = json_decode($request->params);
		
		return $request;
	}
		
	/**
	 * Function for requering request
	 *
	 * @param object $request
	 * @return void
	 */
	private function requery_request($request)
	{
		if (is_object($request))
		{
			list($success, $result) = $this->is_valid_request($request);
			if (!$success) 
			{
				if (isset($request->id)) 
					$result['id'] = $request->id;
				
				return self::response(FALSE, $result, 200, FALSE);
			} 
			else 
			{
				$request = $this->pre_checking_params($request);
			
				return $this->exec_method($request);
			}
		} 
		elseif (is_array($request)) 
		{
			if (count((array)$request) < 1)
				self::response(FALSE, ['error' => self::_msg_code('err_request_invalid'), 'id' => null], 200);
			
			$result = [];
			foreach($request as $k => $r) 
			{
				$result[$k] = $this->requery_request($r);
			}

			return $result;
		} 
	}
	
	/**
	 * Shortcut for get language line key and with additional parameters
	 *
	 */
	private function _msg($key, $params = NULL)
	{
		get_instance()->load->helper('mylanguage');
		return mylang($key, $params);
	}

	private function _code($key)
	{
		$code = 0;
		
		$alt_path = APPPATH.'config'.SEPARATOR.'jsonrpc.php';
		if(file_exists($alt_path)) 
		{
			$codes = include $alt_path;
			$code = $codes[$key] ?? 0;
		}

		return $code;
	}
	
	private function _msg_code($key, $params = NULL)
	{
		$result['message'] = self::_msg($key, $params);
		$result['code'] = self::_code($key);
		return $result;
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

		if (is_array($payload))
		{
			if (count($payload) > 0)
				die(json_encode($payload));
		} 
		else 
		{
			if ($payload || !empty($payload))
				die(json_encode($payload));
		}
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

		if (defined('DEPRECATED_DATE')) 
		{
			if (date('Y-m-d') >= DEPRECATED_DATE) 
			{
				$output['status']  = FALSE;
				$output['message'] = self::_msg('info_api_has_deprecated');
				if (!$exit)
					return $output;

				self::json_out($output, $status_code);

			} 
			else 
			{
				$output['deprecated'] = self::_msg('info_api_deprecated_on', DEPRECATED_DATE);
			}

		}
		// 	$output['deprecated'] = DEPRECATED_MSG;
		// if (defined('DEPRECATED_MSG'))
		// 	$output['deprecated'] = DEPRECATED_MSG;
		
		if (!$exit)
			return array_merge($output, $payload);

		self::json_out(array_merge($output, $payload), $status_code);
	}

	/**
	 * Execute Request Method
	 *
	 * 1. If result error then request->id == null
	 * 2. If request->id <> null
	 * 3. Except No. 1 & 2 => no output (that's notification)
	 * 
	 * @param object $request
	 * @return void
	 */
	private function exec_method($request)
	{
		if (!isset($request->method))
			return self::response(FALSE, [
				'error' => self::_msg_code('err_method_required'),
				'id' => (isset($request->id) ? $request->id : null),
			], 200, FALSE);

		if (empty($request->method))
			return self::response(FALSE, [
				'error' => self::_msg_code('err_method_unknown', 'null'),
				'id' => (isset($request->id) ? $request->id : null),
			], 200, FALSE);

		$version = 1;
		if (isset($request->version) && !empty($request->version))
			$version = $request->version;

		// =================== Check is valid method ======================
		$parseMethod = explode('.', $request->method);
		if (count($parseMethod) < 2)
			return self::response(FALSE, [
				'error' => self::_msg_code('err_method_unknown', $request->method),
				'id' => (isset($request->id) ? $request->id : null),
			], 200, FALSE);
		
		$model_path = APPPATH.'models'.DIRECTORY_SEPARATOR.(PREFIX_FOLDER ? PREFIX_FOLDER.DIRECTORY_SEPARATOR : '');
		$dir = strtolower(PREFIX_FOLDER ? PREFIX_FOLDER.SEPARATOR : '');
		if ($tmp_dir = array_slice($parseMethod, 0, count($parseMethod)-2)) 
		{
			$model_path = $model_path . implode(DIRECTORY_SEPARATOR, $tmp_dir) . DIRECTORY_SEPARATOR;
			$dir = strtolower(PREFIX_FOLDER ? PREFIX_FOLDER.SEPARATOR : '') . implode(SEPARATOR, $tmp_dir) . SEPARATOR;
		} 

		list($class, $method) = explode('.', implode('.', array_slice($parseMethod, -2, 2)));
	
		$model = ucfirst($class).'_model';
		if(!file_exists($model_path.$model.'.php'))
			return self::response(FALSE, [
				'error' => self::_msg_code('err_method_unknown', $request->method),
				'id' => (isset($request->id) ? $request->id : null),
			], 200, FALSE);
		
		$this->load->model($dir.$model);
		if (!method_exists($this->{$model}, $method))
			return self::response(FALSE, [
				'error' => self::_msg_code('err_method_unknown', $request->method),
				'id' => (isset($request->id) ? $request->id : null),
			], 200, FALSE);
		// =================== Check is valid method ======================

		// Clear any cache before execute any method
		// $this->db->reset_query();
		
		// Load the language
		$alt_path = APPPATH.'models'.SEPARATOR.PREFIX_FOLDER.SEPARATOR;
		$lang_path = 'language'.SEPARATOR.$request->idiom.SEPARATOR.strtolower($class).'_lang.php';
		if(file_exists($alt_path.$lang_path)) 
			$this->lang->load(strtolower($class), $request->idiom, FALSE, TRUE, $alt_path);

		// Execute the process
		list($success, $result) = $this->{$model}->{$method}($request);
		if (!$success) 
		{
			$result = is_array($result) 
									? $result  
									: (array) $result;
	
			$result['id'] = $request->id;
			return self::response(FALSE, $result, 200, FALSE);
		}
		
		if (isset($request->id) && !empty($request->id)) 
		{
			$result['id'] = $request->id;
			return self::response(TRUE, $result, 200, FALSE);
		}
	}
	
}