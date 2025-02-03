<?php

$config = include 'env.config.php';

define('SEPARATOR', '/');

/*
 *---------------------------------------------------------------
 * APPLICATION ENVIRONMENT
 *---------------------------------------------------------------
 *
 * You can load different configurations depending on your
 * current environment. Setting the environment also influences
 * things like logging and error reporting.
 *
 * This can be set to anything, but default usage is:
 *
 *     development
 *     testing
 *     production
 *
 * NOTE: If you change these, this impact to error_reporting() too
 */
define('ENVIR', 'production');

/**
 * -------------------------------------------------------------------
 * Time Zone
 * -------------------------------------------------------------------
 * 
 */
define('TIME_ZONE', 'Asia/Jakarta');
@date_default_timezone_set(TIME_ZONE);

/**
 * -------------------------------------------------------------------
 * Override php.ini config
 * -------------------------------------------------------------------
 * 
 */
if (function_exists('ini_set')) {
	@ini_set('max_execution_time', 300);
	@ini_set('date.timezone', TIME_ZONE);
	@ini_set('post_max_size', '8M');
	@ini_set('upload_max_filesize', '2M');
}

/**
 * -------------------------------------------------------------------
 * DEFINE HOST & BASE URL
 * -------------------------------------------------------------------
 * 
 */
define('HTTP_HOST', $_SERVER['HTTP_HOST'] ?? 'localhost');
define('PROTOCOL', isset($_SERVER["HTTPS"]) && $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://');
define('BASE_URL', PROTOCOL . HTTP_HOST . SEPARATOR);

if (!in_array(HTTP_HOST, array_merge($config['http_host']))) {
	header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
	exit(json_encode([
		'status' => FALSE,
		'message' => "This Domain Name or IP Address is not available."
	]));
}

/*
 * ---------------------------------------------------------------
 *  CLI mode compatibility
 * ---------------------------------------------------------------
 * 
 * Because in CLI mode parameter $_SERVER['REQUEST_METHOD'] is not exist.
 * 
 */
define('HTTP_METHOD', $_SERVER['REQUEST_METHOD'] ?? 'cli');

/*
 * ---------------------------------------------------------------
 *  Define TMP Directory for caching, session and logging 
 * ---------------------------------------------------------------
 */
define('DIR_TMP', __DIR__ . DIRECTORY_SEPARATOR . 'tmp');
is_dir(DIR_TMP) or mkdir(DIR_TMP, 0777, true);
define('CACHE_DIR', DIR_TMP . DIRECTORY_SEPARATOR . 'cache');
is_dir(CACHE_DIR) or mkdir(CACHE_DIR, 0777, true);
define('SESS_DIR', DIR_TMP . DIRECTORY_SEPARATOR . 'session');
is_dir(SESS_DIR) or mkdir(SESS_DIR, 0777, true);
define('LOGS_DIR', DIR_TMP . DIRECTORY_SEPARATOR . 'logs');
is_dir(LOGS_DIR) or mkdir(LOGS_DIR, 0777, true);
define('SESS_DRIVER', 'files');
define('SESS_COOKIE_NAME', 'ci_session');
define('SESS_SAVE_PATH', SESS_DIR . DIRECTORY_SEPARATOR);

/*
 * ---------------------------------------------------------------
 *  Define default route to controller for each domain 
 * ---------------------------------------------------------------
 */
if (!isset($config['route'][HTTP_HOST])) {
	header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
	exit(json_encode([
		'status' => FALSE,
		'message' => "No route to default controller, for this Domain or IP Address."
	]));
} else {
	define('DEFAULT_ROUTE', $config['route'][HTTP_HOST]);
}

/**
 * --------------------------------------------------------------------
 * Define default Prefix folder for each domain in application/model
 * --------------------------------------------------------------------
 * 
 * This prefix folder is for separate script in application/model for each domain
 * Because this API only uses model to put all the scripts
 * 
 */
if (!isset($config['prefix'][HTTP_HOST])) {
	header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
	exit(json_encode([
		'status' => FALSE,
		'message' => "Prefix Folder is not set."
	]));
} else {
	define('PREFIX_FOLDER', $config['prefix'][HTTP_HOST]);
}


/**
 * --------------------------------------------------------------------
 * Define default database connection for each domain
 * --------------------------------------------------------------------
 * 
 */
if (!isset($config['database_conn'][HTTP_HOST])) {
	header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
	exit(json_encode([
		'status' => FALSE,
		'message' => "No default database for this Domain or IP Address."
	]));
}

define('DB_CONN', $config['database_conn']);
define('DB_DSN', $config['database_conn'][HTTP_HOST]['dsn']);
define('DB_DRIVER', $config['database_conn'][HTTP_HOST]['dbdriver']);
define('DB_PORT', $config['database_conn'][HTTP_HOST]['port']);
define('DB_HOSTNAME', $config['database_conn'][HTTP_HOST]['hostname']);
define('DB_USERNAME', $config['database_conn'][HTTP_HOST]['username']);
define('DB_PASSWORD', $config['database_conn'][HTTP_HOST]['password']);
define('DB_DATABASE', $config['database_conn'][HTTP_HOST]['database']);
define('DB_DEBUG', $config['database_conn'][HTTP_HOST]['debug']);

/**
 * -------------------------------------------------------------------
 * Define Cache Server status, is redis/memcached server is available on this server?
 * -------------------------------------------------------------------
 * 
 */
if (!isset($config['cache_server'][HTTP_HOST])) {
	define('CACHE_SERVER_ENABLE', FALSE);
} else {
	define('CACHE_SERVER_ENABLE', $config['cache_server'][HTTP_HOST]);
}

/**
 * -------------------------------------------------------------------
 * Define SMTP Host setting
 * -------------------------------------------------------------------
 * 
 */
define('SMTP_HOST', $config['smtp']['host']);
define('SMTP_PORT', $config['smtp']['port']);
define('SMTP_USER', $config['smtp']['user']);
define('SMTP_PASS', $config['smtp']['pass']);
define('SMTP_SECURITY', $config['smtp']['security']);
define('SMTP_TIMEOUT', $config['smtp']['timeout']);
