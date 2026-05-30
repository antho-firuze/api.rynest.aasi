<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

return [
    // Default database
    'default' => 'mysql',

    // Various database configurations
    'connections' => [
        'mysql' => [
            'driver'      => 'mysql',
            'host'        => getenv('DB_HOST'),
            'port'        => getenv('DB_PORT'),
            'database'    => getenv('DB_NAME'),
            'username'    => getenv('DB_USER'),
            'password'    => getenv('DB_PASSWORD'),
            'unix_socket' => '',
            'charset'     => 'utf8',
            'collation'   => 'utf8_unicode_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => false, // It is necessary when using swoole or swow as the driver
            ],
            'pool' => [ // Connection pool configuration
                'max_connections' => 5, // Maximum number of connections
                'min_connections' => 1, // Minimum number of connections
                'wait_timeout' => 3,    // Get the maximum time for the connection to wait from the connection pool, and an exception will be thrown after the timeout.Only valid in coroutine environments
                'idle_timeout' => 60,   // The maximum idle time for connections in the connection pool, and the recycling will be closed after the timeout until the number of connections is min_connections
                'heartbeat_interval' => 50, // Connection pool heartbeat detection time, unit seconds, recommended to be less than 60 seconds
            ],
        ],
    ],
];