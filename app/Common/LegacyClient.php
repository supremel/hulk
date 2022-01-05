<?php
/**
 * 遗留系统(原java业务系统)相关api
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-22
 * Time: 16:08
 */

namespace App\Common;


use App\Consts\ErrorCode;
use App\Exceptions\CustomException;

class LegacyClient extends HttpClient
{
    private static function _hasApiOrder($phone)
    {
        $mobile = strtoupper(md5($phone));
        $path = '/v2/order/h5/hasApiOrder/' . $mobile;
        $response = self::_curl(env('LEGACY_URL') . $path, self::METHOD_GET);
        if (false == $response) {
            AlertClient::sendAlertEmail(new \Exception("请求老系统（用户是否有在贷）异常:" . $response));
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '系统异常，请稍后重试');
        }
        $data = json_decode($response, true);
        if (!$data || empty($data['code']) || 200 != $data['code']) {
            AlertClient::sendAlertEmail(new \Exception("请求老系统（用户是否有在贷）异常:" . $response));
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '系统异常，请稍后重试');
        }
        return $data['data'];
    }

    /**
     * 是否有api在贷订单
     * @param $phone
     * @return string
     * @throws CustomException
     */
    public static function hasOrderInLoan($phone)
    {
        $data = self::_hasApiOrder($phone);
        if ($data['hasAPIOrder']) {
            return $data['sign'];
        }
        return '';
    }

    /**
     * api订单信息
     * @param $phone
     * @return bool
     * @throws CustomException
     */
    public static function apiOrderInfo($phone)
    {
        $data = self::_hasApiOrder($phone);
        return $data;
    }
}