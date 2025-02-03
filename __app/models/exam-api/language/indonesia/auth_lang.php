<?php

/*
 * Indonesia language
 * 
 * Sample: 
 * =======
 * $lang['err_sample'] = 'Incorrect Email or Password'; 
 * $lang['sucess_sample'] = 'Login Success'; 
 * $lang['conf_sample'] = 'Are you sure want to delete this record ?'; 
 * $lang['info_sample'] = 'Your password has been sent to your email'; 
 * $lang['notif_sample'] = 'You have unread email'; 
 */

$lang['err_field_required'] = 'Field [%s] is required';
$lang['err_min_password_length'] = 'Panjang Password minimum %s karakter';
$lang['err_max_password_length'] = 'Panjang Password maksimum %s karakter';
$lang['err_login_attempt_reached'] = 'Akun anda akan terkunci sementara. Silahkan coba lagi nanti, setelah %s';
$lang['err_login_failed'] = 'Email atau Password salah, silahkan coba kembali';
$lang['err_unlocked_failed'] = 'Password anda salah';
$lang['err_old_password'] = 'Password lama anda salah';
$lang['err_email_not_found'] = 'Email belum terdaftar';
$lang['err_otp_not_found'] = 'OTP yang anda masukkan salah';
$lang['err_username_not_found'] = 'UserName belum terdaftar';
$lang['err_username_or_email_not_found'] = 'UserName atau Email atau No Handphone belum terdaftar';
$lang['err_not_registered_user'] = 'You has not been registered, please register first';
$lang['err_email_has_register'] = 'Your email have registered, please login with your email & password !';
$lang['err_email_has_register_not_active'] = 'Your email have registered but not activate yet, please check your email to activate !';
$lang['err_old_client_lost_email'] = 'Your email is not recognized, please replace with your another email !' . "\r\n" . ' Or you can ask our CS admin.';
$lang['err_activate_account'] = 'Token not found, or maybe your account has already activate';
$lang['err_activate_account_email_password'] = 'Email atau Password salah, silahkan coba kembali';

$lang['success_login'] = 'Login Success';
$lang['success_unlocked'] = 'This account has been unlocked';
$lang['success_reset'] = 'Password anda telah berhasil di reset';
$lang['success_chg_password'] = 'Password anda telah berhasil di rubah';
$lang['success_register'] = 'Registrasi berhasil, silahkan cek email anda';
$lang['success_activation'] = 'Thank you, your account has been active.<br>Now you can login in our Web/Android/IOS Apps !';

$lang['info_sent_email_otp'] = 'OTP telah di kirim ke email anda';
$lang['info_sent_email_password'] = 'Password baru telah di kirim ke email anda';
$lang['info_sent_email_reset_password_link'] = 'Link address for reset password has been sent to your email';
$lang['info_sent_email_rst_password'] = 'Password has been reset successfully, & new password has been sent to user email';
$lang['info_copyright'] = 'Copyright by %s';
$lang['info_poweredby'] = 'Powered by %s';

$lang['email_subject_forgot_password'] = 'LSP PS - OTP !';
$lang['email_body_forgot_password'] = 'Dear {name}, <br><br>' .
	'Kode OTP anda adalah : <b>{otp}</b><br><br><br>' .
	'Note: Jangan beritahukan kode OTP anda pada siapa pun.<br><br>';

$lang['email_subject_unregister'] = 'LSP PS - Unregister Account !';
$lang['email_body_unregister'] =
	"<p>Dear {name},</p> " .
	"<p>Kami mendapati Anda melakukan permohonan penghapusan Akun Anda.</p> " .
	"<p>Berikut beberapa hal yang perlu anda perhatikan :</p> " .
	"<ul> " .
	"<li>Kami akan menunda beberapa saat untuk penghapusan Akun Anda.</li> " .
	"<li>Apabila Anda login ulang atau masuk kembali ke Aplikasi, selama dalam masa tenggang. Secara otomatis membatalkan penghapusan Akun.</li> " .
	"</ul> " .
	"<p>Note:</p> " .
	"<p>Jika Anda tidak merasa melakukan permohonan yang dimaksud, silahkan Anda login atau masuk kembali ke Aplikasi.</p> " .
	"<p>Untuk keterangan lebih lanjut silahkan kontak CS kami yang tertera pada Aplikasi.</p> " .
	"<p>Salam,</p> " .
	"<p>LSP Perasuransian Syariah</p> ";
