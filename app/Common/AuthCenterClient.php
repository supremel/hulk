<?php
/**
 * 认证中心
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-10
 * Time: 14:31
 */

namespace App\Common;

class AuthCenterClient extends HttpClient
{

    private static function handleResponse($response)
    {
        if (!$response) {
            return null;
        }
        $res = json_decode($response, true);
        return $res ?? $response;
    }

    /**
     * @desc   请求风控
     * @action riskOperatorAction
     * @param $data
     * @return array
     * @author liuhao
     * @date   2019/9/11
     */
    public static function riskOperator($data)
    {
        $response = self::_curl(env('RISK_AUTH_SERVICE_URL'),
            self::METHOD_POST,
            $data,
            self::DATA_TYPE_JSON,
            '12'
        );
        if (empty($response)) {
            return [];
        }
        $result = json_decode($response,true);

        return $result;
    }

    /*
     * 调风控授权接口
     */
    public static function authRoute($data)
    {
        $response = self::_curl(env('RISK_AUTH_SERVICE_URL'),
            self::METHOD_POST, $data, self::DATA_TYPE_JSON);
        $res = self::handleResponse($response);
        $result_dict = $res['result_dict'] ?? null;
        if (empty($result_dict)) {
            return null;
        }
        return $result_dict;
    }

    /*
     * 调用接口获取设备信息
     */
    public static function getDeviceData($data)
    {
        $path = '/api/getSnapshotUrl';
        $response = self::_curl(env('DEVICE_SERVICE_URL') . $path,
            self::METHOD_POST, $data);
        return self::handleResponse($response);
    }

    /*
    * 调用接口解密设备信息
    */
    public static function decryptDeviceDataByRequestId($requestId)
    {
        $path = '/api/parseDeviceInfo';
        $response = self::_curl(env('DEVICE_SERVICE_URL') . $path,
            self::METHOD_POST, ['data' => $requestId]);
        return self::handleResponse($response);
    }
}