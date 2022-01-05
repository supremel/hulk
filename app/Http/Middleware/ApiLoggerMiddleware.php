<?php
/**
 * 日志中间件 for apis
 * User: hexuefei
 * Date: 2019-06-18
 * Time: 13:59
 */

namespace App\Http\Middleware;

use App\Common\Utils;
use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ApiLoggerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $startTime = microtime(true);

        $response = $next($request);
        $user = Utils::resolveUser($request);
        $logData = [];
        $endTime = microtime(true);
        $cost = intval(($endTime - $startTime) * 1000);
        $logData['cost'] = $cost;
        $logData['module'] = 'api';
        $logData['startTime'] = $startTime;
        $logData['endTime'] = $endTime;
        $logData['ip'] = $request->ip();
        $logData['phone'] = $user ? $user['phone'] : '';
        $logData['url'] = $request->fullUrl();
        $logData['method'] = $request->method();
        $logData['input'] = $request->getContent();
        $logData['httpCode'] = $response->getStatusCode();
        $logData['output'] = '';
        $logData['headers'] = @json_encode($request->header());
        if ($logData['httpCode'] == Response::HTTP_OK) {
            if (env('APP_DEBUG')) {
                $logData['output'] = $response->getContent();
            } else {
                $logData['output'] = substr($response->getContent(), 0, 512);
            }
            $ret = json_decode($response->getContent(), true);
            $logData['code'] = @$ret['code'];
            $logData['msg'] = @$ret['msg'];
        }

        $logStr = '';
        foreach ($logData as $key => $value) {
            $logStr .= $key . "=" . $value . "\t";
        }
        Log::info($logStr);
        return $response;
    }

    /**
     * Terminate the application.
     *
     * @param $request
     * @param $response
     */
    public function terminate($request, $response)
    {
//        Log::info('terminate');
    }
}
