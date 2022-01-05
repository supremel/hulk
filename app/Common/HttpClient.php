<?php
/**
 * HTTP CURL
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-08
 * Time: 14:38
 */

namespace App\Common;


use Illuminate\Support\Facades\Log;

class HttpClient
{
    const METHOD_POST = 'POST';
    const METHOD_GET = 'GET';

    const DATA_TYPE_JSON = 'JSON';

    /**
     * @param $url 请求地址
     * @param string $method http方法
     * @param array $data 请求数据
     * @param string $dataType 数据类型
     * @param int $timeout 超时，默认1秒
     * @return bool|string
     */
    protected static function _curl($url, $method = self::METHOD_POST, $data = array(), $dataType = '', $timeout = 10)
    {
        $startTime = microtime(true);
        $curl = curl_init();
        // 针对星号做特殊处理(java不对星号做编码)
        $paramsStr = str_replace('%2A', '*', http_build_query($data));
        if ($data && $method == self::METHOD_GET) {
            $url .= '?' . $paramsStr;
        }
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        if ($method == self::METHOD_POST) {
            curl_setopt($curl, CURLOPT_POST, 1);
            if ($dataType == self::DATA_TYPE_JSON) {
                $dataString = json_encode($data);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($dataString))
                );
                curl_setopt($curl, CURLOPT_POSTFIELDS, $dataString);
            } else {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $paramsStr);
            }
        }

        $result = curl_exec($curl);

        // log
        $logData['cost'] = intval((microtime(true) - $startTime) * 1000);
        $logData['module'] = 'thirdparty';
        $logData['startTime'] = $startTime;
        $logData['endTime'] = microtime(true);
        $logData['url'] = $url;
        $logData['method'] = $method;
        $logData['input'] = $paramsStr;
        $logData['output'] = str_replace("\n", '', $result);
        $logData['code'] = curl_errno($curl);
        $logData['msg'] = curl_error($curl);
        $logData['httpCode'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $logStr = '';
        foreach ($logData as $key => $value) {
            $logStr .= $key . "=" . $value . "\t";
        }
        (0 == $logData['code']
            && $result
            && 200 == $logData['httpCode'])
            ? Log::info($logStr) : Log::warning($logStr);

        curl_close($curl);
        if (empty($result)) {
            $result = false;
        }
        return $result;
    }
}