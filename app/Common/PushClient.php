<?php
/**
 * 推送服务
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-08
 * Time: 14:31
 */

namespace App\Common;


use App\Consts\Constant;

class PushClient extends HttpClient
{
    const PRODUCT = 'SHUILIAN';

    private static function handleResponse($response)
    {
        if (!$response) {
            return false;
        }
        $res = json_decode($response, true);
        if (empty($res['code']) || 0 != $res['code']) {
            return false;
        }
        return true;
    }

    public static function tokenReport($userId, $deviceType, $pushToken)
    {
        $path = '/v10/push/collect';
        $data = [
            'deviceId' => $pushToken,
            'device' => ($deviceType == Constant::DEVICE_TYPE_IOS) ? 'IOS' : 'ANDROID',
            'business' => $userId,
            'product' => self::PRODUCT,
            'pushProvider' => 'YOUMENG',
        ];
        $response = self::_curl(env('PUSH_SERVICE_URL') . $path, self::METHOD_POST, $data, self::DATA_TYPE_JSON);
        return self::handleResponse($response);
    }

    public static function pushByUserId($userId, $title, $content, $action = '', $statisticId = '')
    {
        $path = '/v10/push/';
        $data = [
            'type' => 'NORMAL',
            'product' => self::PRODUCT,
            'business' => $userId,
            'body' => [
                'title' => $title,
                'content' => $content,
                'statistic' => $statisticId,
                'type' => 'notify', // 通知类型
                'method' => [
                    'action' => $action,
                    'method' => 'CUSTOM',
                ],
            ],
        ];
        $response = self::_curl(env('PUSH_SERVICE_URL') . $path, self::METHOD_POST, $data, self::DATA_TYPE_JSON);
        return self::handleResponse($response);
    }


}