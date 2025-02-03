<?php defined('BASEPATH') OR exit('No direct script access allowed');

$active_group = 'default';
$query_builder = TRUE;

$db['default'] = array(
	'dsn'	=> DB_DSN,
	'hostname' => DB_HOSTNAME,
	'username' => DB_USERNAME,
	'password' => DB_PASSWORD,
	'database' => DB_DATABASE,
	'dbdriver' => DB_DRIVER,
	'port' 	   => DB_PORT,
	'dbprefix' => '',
	'pconnect' => FALSE,
	'db_debug' => DB_DEBUG,
	'cache_on' => FALSE,
	'cachedir' => '',
	'char_set' => 'utf8',
	'dbcollat' => 'utf8_general_ci',
	'swap_pre' => '',
	'encrypt' => FALSE,
	'compress' => FALSE,
	'stricton' => FALSE,
	'failover' => array(),
	'save_queries' => TRUE
);

