<?php

return [
    'enable' => true,
    'jwt' => [
        /** Algorithm HS256、HS384、HS512、RS256、RS384、RS512、ES256、ES384、Ed25519 */
        'algorithms' => 'HS256',

        /** accessTokens */
        'access_secret_key' => '2022d3d3LmJq',

        /** ACCESS tokens expire, unit: second.2 hours by default */
        'access_exp' => 7200,

        /** Refresh token secret key */
        'refresh_secret_key' => '2022KTxigxc9o50c',

        /** Refresh tokens expire, unit: second.Default 7 days */
        'refresh_exp' => 604800,

        /** Whether the refresh token is disabled, can not help but use false by default */
        'refresh_disable' => false,

        /** Token issuer */
        'iss' => 'webman.tinywan.cn',

        /** It can only be accessed at a certain point in time, and the unit seconds.(For example: 30 means that the current time can not be used) */
        'nbf' => 0,

        /** Clock deviation redundancy time, unit seconds.It is recommended that this room should be less than a few minutes */
        'leeway' => 60,

        /** Whether the single device is allowed to log in, the default is not allowed to */
        'is_single_device' => false,

        /** Caches token time, unit: second.Default 7 days */
        'cache_token_ttl' => 604800,

        /** Caches to prefix, default JWT: token: */
        'cache_token_pre' => 'JWT:TOKEN:',

        /** Refresh the token prefix, default JWT: Refresh_token: */
        'cache_refresh_token_pre' => 'JWT:REFRESH_TOKEN:',

        /** User information model */
        'user_model' => function ($uid) {
            return [];
        },

        /** Do you support Get requests to get token? */
        'is_support_get_token' => false,
        /** Get request to get token request key */
        'is_support_get_token_key' => 'authorization',

        /** Access token private key */
        'access_private_key' => <<<EOD
-----BEGIN RSA PRIVATE KEY-----
...
-----END RSA PRIVATE KEY-----
EOD,

        /** Access token public key */
        'access_public_key' => <<<EOD
-----BEGIN PUBLIC KEY-----
...
-----END PUBLIC KEY-----
EOD,

        /** Refresh token private key */
        'refresh_private_key' => <<<EOD
-----BEGIN RSA PRIVATE KEY-----
...
-----END RSA PRIVATE KEY-----
EOD,

        /** Refresh token public key */
        'refresh_public_key' => <<<EOD
-----BEGIN PUBLIC KEY-----
...
-----END PUBLIC KEY-----
EOD,
    ],
];
