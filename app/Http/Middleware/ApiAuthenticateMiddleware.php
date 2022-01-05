<?php
/**
 * API签名校验
 * User: hexuefei
 * Date: 2019-06-19
 * Time: 10:18
 */

namespace App\Http\Middleware;


use App\Common\Token;
use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Exceptions\CustomException;
use Closure;
use Illuminate\Http\Request;

class ApiAuthenticateMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     * @throws CustomException
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('Token');
        $user = Token::getUserByToken($token, Constant::USER_SOURCE_APP);
        if (!$user) {
            throw new CustomException(ErrorCode::USER_NEED_LOGIN);
        }
        $request->user = $user;

        return $next($request);
    }
}
