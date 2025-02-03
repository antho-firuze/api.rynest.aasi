<?php defined('BASEPATH') OR exit('No direct script access allowed');
// 
$config['useragent'] 	= 'CI Webservice';	
$config['charset'] 	  = 'utf-8';	  // Character set (utf-8, iso-8859-1, etc.).
$config['protocol'] 	= 'smtp';	    // mail, sendmail, or smtp		
$config['mailtype'] 	= 'html';	    // text or html	
$config['priority'] 	= '1';	      // 1, 2, 3, 4, 5
$config['newline'] 	  = "\r\n";	    // “\r\n” or “\n” or “\r”
$config['crlf'] 	    = "\r\n";	    // “\r\n” or “\n” or “\r”
$config['smtp_host'] 	= SMTP_HOST;	
$config['smtp_port'] 	= SMTP_PORT;		  // ssl=465 or tls=587
$config['smtp_user'] 	= SMTP_USER;		
$config['smtp_pass'] 	= SMTP_PASS;		
$config['smtp_crypto'] 	= SMTP_SECURITY;    // ssl/tls		
$config['smtp_timeout'] = SMTP_TIMEOUT;	
$config['from']       = 'support@aasi.or.id';		// email address for system email sender
$config['from_name']  = 'Support AASI';		

// sample gmail ssl
// ========================================
// $config['useragent'] 	= 'CI Webservice';	
// $config['charset'] 	  = 'utf-8';	  // Character set (utf-8, iso-8859-1, etc.).
// $config['protocol'] 	= 'smtp';	    // mail, sendmail, or smtp		
// $config['mailtype'] 	= 'html';	    // text or html	
// $config['priority'] 	= '1';	      // 1, 2, 3, 4, 5
// $config['newline'] 	  = "\r\n";	    // “\r\n” or “\n” or “\r”
// $config['crlf'] 	    = "\r\n";	    // “\r\n” or “\n” or “\r”
// $config['smtp_host'] 	= 'smtp.gmail.com';	
// $config['smtp_port'] 	= '465';		  // ssl=465 or tls=587
// $config['smtp_user'] 	= 'developerrynest@gmail.com';		
// $config['smtp_pass'] 	= 'Rynestnetline157';		
// $config['smtp_crypto'] 	= 'ssl';    // ssl/tls		
// $config['smtp_timeout'] = 7;	
// $config['from']       = 'developerrynest@gmail.com';		// email address for system email sender

// sample gmail tls
// ========================================
// $config['useragent'] 	= 'CI Webservice';	
// $config['charset'] 	  = 'utf-8';	  // Character set (utf-8, iso-8859-1, etc.).
// $config['protocol'] 	= 'smtp';	    // mail, sendmail, or smtp		
// $config['mailtype'] 	= 'html';	    // text or html	
// $config['priority'] 	= '1';	      // 1, 2, 3, 4, 5
// $config['newline'] 	  = "\r\n";	    // “\r\n” or “\n” or “\r”
// $config['crlf'] 	    = "\r\n";	    // “\r\n” or “\n” or “\r”
// $config['smtp_host'] 	= 'smtp.gmail.com';	
// $config['smtp_port'] 	= '587';		  // ssl=465 or tls=587
// $config['smtp_user'] 	= 'developerrynest@gmail.com';		
// $config['smtp_pass'] 	= 'Rynestnetline157';		
// $config['smtp_crypto'] 	= 'tls';    // ssl/tls		
// $config['smtp_timeout'] 	= 7;	
// $config['from']       = 'developerrynest@gmail.com';		// email address for system email sender
