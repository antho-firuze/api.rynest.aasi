<?php

/**
 * This file is config for email.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    Firuze(Antho Firuze)
 * @copyright Firuze(Antho Firuze)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

return [
    'host' => getenv('SMTP_HOST'),
    'port' => getenv('SMTP_PORT'),
    'secure' => getenv('SMTP_SECURE'),
    'username' => getenv('SMTP_USER'),
    'password' => getenv('SMTP_PASS'),
    'mail_from' => getenv('MAIL_FROM'),
    'mail_name' => getenv('MAIL_NAME'),
];
