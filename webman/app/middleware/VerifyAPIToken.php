<?php

namespace app\middleware;

use ReflectionClass;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use Firuze\Jwt\JwtToken;

class VerifyAPIToken implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        // Get the methods that do not require token using reflection
        $controller = new ReflectionClass($request->controller);
        $noNeedLogin = $controller->getDefaultProperties()['noNeedLogin'] ?? [];

        // Method requires token
        if (!in_array($request->action, $noNeedLogin)) {
            try {
                JwtToken::verify();
                return $handler($request);
            } catch (\Throwable $th) {
                return jsonr(['message' => $th->getMessage()]);
            }
        }

        // Method does not require token, continue with the request
        return $handler($request);
    }
}
