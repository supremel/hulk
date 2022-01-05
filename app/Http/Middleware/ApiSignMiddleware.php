<?php
/**
 * API签名校验
 * User: hexuefei
 * Date: 2019-06-19
 * Time: 10:18
 */

namespace App\Http\Middleware;


use App\Consts\ErrorCode;
use App\Exceptions\CustomException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiSignMiddleware
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
        if (!env('SIGN_VERIFY_SWITCH_ON', true)) {
            return $next($request);
        }

        // 公共参数校验
        $request->validate(
            [
                'ts' => 'required|regex:/^[0-9]+$/',
            ]
        );

        $arrParams = $request->input();
        $sign = $request->header('C', '');
        if (!$sign) {
            throw new CustomException(ErrorCode::COMMON_SIGN_ERROR);
        }

        ksort($arrParams);

        $strParam = '';
        foreach ($arrParams as $key => $val) {
            $strParam .= $key . '=' . rawurlencode(strval($val)) . '&';
        }
        $strSign = sprintf('%s%s', $strParam, $request->header('Token'));
        // 针对星号做特殊处理(java不对星号做编码)
        $strSign = str_replace('%2A', '*', $strSign);
        $resultSign = md5($strSign);
        Log::debug("module=api_sign\tstr=" . $strSign . "\tresultSign=" . $resultSign . "\tsign=" . $sign);

        if ($resultSign != $sign) {
            throw new CustomException(ErrorCode::COMMON_SIGN_ERROR);
        }
        return $next($request);
    }
}