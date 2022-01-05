<?php
/**
 * 短信服务
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-08
 * Time: 14:31
 */

namespace App\Common;


use App\Consts\ErrorCode;
use App\Exceptions\CustomException;

class SmsClient extends HttpClient
{
    const PRODUCT = 'SHUILIAN';

    private static function handleResponse($response)
    {
        if (!$response) {
            return false;
        }
        $res = json_decode($response, true);
        if (!isset($res['code']) || 0 != $res['code']) {
            // 针对部分case做特殊处理
            $code = $res['code'] ?? 0;
            if ($code == 410207) {
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '获取短验超当日上限');
            }
            return false;
        }
        return true;
    }

    public static function sendSms($phone, $content)
    {
        if (!env('SMS_SWITCH_ON', false)) {
            return true;
        }
        $path = '/v10/sms';
        $data = [
            'phone' => $phone,
            'sendBody' => $content,
            'product' => self::PRODUCT,
        ];
        $response = self::_curl(env('SMS_SERVICE_URL') . $path, self::METHOD_POST, $data, self::DATA_TYPE_JSON);
        return self::handleResponse($response);
    }

    public static function sendVerifyCode($phone)
    {
        if (!env('SMS_SWITCH_ON', false)) {
            return true;
        }
        $path = '/v10/verifyCode/send';
        $data = [
            'phone' => $phone,
            'product' => self::PRODUCT,
        ];
        $response = self::_curl(env('SMS_SERVICE_URL') . $path, self::METHOD_POST, $data, self::DATA_TYPE_JSON);
        return self::handleResponse($response);
    }

    public static function checkVerifyCode($phone, $code)
    {
        if (!env('SMS_SWITCH_ON', false)) {
            return true;
        }
        $path = '/v10/verifyCode/check';
        $data = [
            'phone' => $phone,
            'verifyCode' => $code,
            'product' => self::PRODUCT,
        ];
        $response = self::_curl(env('SMS_SERVICE_URL') . $path, self::METHOD_POST, $data, self::DATA_TYPE_JSON);
        return self::handleResponse($response);
    }
}
