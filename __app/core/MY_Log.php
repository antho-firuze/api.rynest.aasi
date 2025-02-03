<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Log extends CI_Log {

	/**
	 * Class constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	protected function _format_line($level, $date, $message)
	{
		$env = ENVIRONMENT == 'production' ? 'production' : 'local';
		return $env.'.'.$level.' - '.$date.' --> '.$message.PHP_EOL;
	}

}
