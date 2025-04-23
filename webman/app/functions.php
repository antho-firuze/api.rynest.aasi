<?php
/**
 * Here is your custom functions.
 */

use support\Request;
use support\Response;

/**
 * Jsonr response
 * @param $data
 * @param int $options
 * @return Response
 */
if (!function_exists('jsonr')) {
    function jsonr($data, int $status = 500, int $options = JSON_UNESCAPED_UNICODE): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data, $options));
    }
}

/**
 * Generate a "Random" Code with definition length
 *
 * @param	int	$len        number of characters
 * @param	string $type	Type of random string.  uppercase|lowercase|numeric
 * @return	string
 */
if (!function_exists('generate_code')) {
    function generate_code(int $len = 6, string $type = 'numeric|uppercase')
    {
        $data = [
            'numeric'   => '123456789',
            'lowercase' => 'abcdefghijklmnopqrstuvwxyz',
            'uppercase' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        ];

        $pool = '';
        $types = explode('|', $type);
        foreach ($types as $v)
            $pool .= isset($data[$v]) ? $data[$v] : '';

        return substr(str_shuffle(str_repeat($pool, ceil($len / strlen($pool)))), 0, $len);
    }
}

/**
 * Return a formatted string
 *
 * @param string $str
 * @param array $vars     Paired value array
 * @param string $prefix  Default '{'
 * @param string $suffix  Default '}'
 * @return void
 */
if (!function_exists('sprintfx')) {
    function sprintfx(string $str, array $vars, string $prefix = '{', string $suffix = '}')
    {
        if (array() === $vars)
            return $str;

        foreach ($vars as $key => $val)
            $arr[$prefix . $key . $suffix] = $val;

        return str_replace(array_keys($arr), array_values($arr), $str);
    }
}

/**
 * Check required params field
 *
 * @param string $str
 * @param array $vars     Paired value array
 * @param string $prefix  Default '{'
 * @param string $suffix  Default '}'
 * @return void
 */
if (!function_exists('require_params')) {
    function require_params(Request $request, $fields = [])
    {
        $params = (object) $request->post();
        $nfields = [];
        foreach ($fields as $k => $v)
            if (!isset($params->{$v}))
                $nfields[] = $v;

        // if (isset($request->params->{$v}) && $request->params->{$v} == '')
        // 	$nfields[] = $v;

        if ($nfields) {
            return ["message" => implode(', ', $nfields)];
        }

        return TRUE;
    }
}