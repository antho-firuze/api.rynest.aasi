<?php

namespace support;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use support\exception\BusinessException;

class Email
{

    /**
     * @param $from
     * @param $to
     * @param $subject
     * @param $content
     * @return bool false or error
     * @throws Exception|BusinessException
     */
    public static function send($from, $to, $subject, $content)
    {
        try {
            $mailer = static::getMailer();
            if ($from) {
                call_user_func_array([$mailer, 'setFrom'], (array)$from);
            }
            call_user_func_array([$mailer, 'addAddress'], (array)$to);
            $mailer->isHTML(true);
            $mailer->Subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
            $mailer->Body = $content;
            if (!$mailer->send()) {
                throw new Exception($mailer->ErrorInfo);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get Mailer
     * @return PHPMailer
     * @throws BusinessException
     */
    private static function getMailer(): PHPMailer
    {
        if (!class_exists(PHPMailer::class)) {
            throw new BusinessException('Please execute composer require phpmailer/phpmailer and then restart');
        }
        $config = static::getConfig();
        if (!$config) {
            throw new BusinessException('No email configuration');
        }
        $mailer = new PHPMailer();
        $mailer->SMTPDebug = SMTP::DEBUG_SERVER;
        $mailer->isSMTP();
        $mailer->SMTPAuth = true;
        $mailer->SMTPAutoTLS = true;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->Priority = 1;
        $mailer->AllowEmpty = true;
        $mailer->Host = $config['host'];
        $mailer->Port = $config['port'];
        $map = [
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
        ];
        $mailer->SMTPSecure = $map[$config['secure']] ?? '';
        $mailer->Username = $config['username'];
        $mailer->Password = $config['password'];
        call_user_func_array([$mailer, 'setFrom'], [$config['mail_from'], $config['mail_name']]);
        return $mailer;
    }

    /**
     * Obtain configuration
     * @return array|null
     */
    private static function getConfig()
    {
        $config = config('email');
        return $config;
    }
}
