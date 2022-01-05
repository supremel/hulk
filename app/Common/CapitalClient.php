<?php
/**
 * 资金&支付服务
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-08
 * Time: 14:31
 */

namespace App\Common;

use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Exceptions\CustomException;

class CapitalClient extends HttpClient
{
    const CHANNEL_PRODUCT = 'WATERLOTUS';
    const CHANNEL_NUM = 'WATERLOTUS01';

    const ORDER_SOURCE_APP = 'APP';

    private static function handleResponse($response)
    {
        if (!$response) {
            return null;
        }

        $res = json_decode($response, true);
        if (!isset($res['code'])) {
            return null;
        }

        return $res;
    }

    private static function handleResponseV2($response)
    {
        if (!$response) {
            return null;
        }
        $res = json_decode($response, true);
        if (200 != $res['code'] || 'SUCCESS' != $res['data']['tranState']) {
            if (ErrorCode::USER_BANK_CARD_BINDED == $res['data']['resCode']) { // 已经鉴权
                throw new CustomException(ErrorCode::USER_BANK_CARD_BINDED);
            }
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, @$res['data']['resMsg']);
        }
        return $res;
    }

    /**
     * 查询笑脸合同
     * @param $bizNo
     * @return string
     */
    public static function queryContract($bizNo)
    {
        $path = '/api/v1/order/contractQuery';
        $data = [
            'orderId' => $bizNo,
        ];
        $response = self::_curl(env('CAPITAL_SERVICE_URL') . $path, self::METHOD_GET, $data);
        $res = self::handleResponse($response);
        if ($res && 0 == $res['code'] && isset($res['data'])) {
            foreach ($res['data'] as $item) {
                if ($item['type'] == 1) {
                    return $item['url'];
                }
            }
        }
        return '';
    }

    /**
     * 银行卡签约支付通道-发送短信验证码
     * @param $userData
     * @param string $orderSource
     * @return bool
     * @throws CustomException
     */
    public static function sendSms($userData, $orderSource = self::ORDER_SOURCE_APP)
    {
        $path = '/protocolSign';
        $data = [
            'requestNo' => $userData['sms_biz_no'],
            'userId' => $userData['user_id'],
            'channelProduct' => self::CHANNEL_PRODUCT,
            'channelNum' => self::CHANNEL_NUM,
            'bankCode' => $userData['bank_code'],
            'bankCardNo' => $userData['card_no'],
            'userName' => $userData['name'],
            'certificateNo' => $userData['identity'],
            'phoneNo' => $userData['reserved_phone'],
            'orderSource' => $orderSource,
        ];
        $response = self::_curl(env('PAY_SERVICE_URL') . $path,
            self::METHOD_POST, $data, self::DATA_TYPE_JSON);

        return self::handleResponseV2($response);
    }

    /**
     * 银行卡签约支付通道-签约
     * @param $smsBizNo 短信验证码流水号
     * @param $bizNo 签约流水号
     * @param $code 短信验证码
     * @param string $orderSource
     * @return bool
     * @throws CustomException
     */
    public static function sign($smsBizNo, $bizNo, $code, $orderSource = self::ORDER_SOURCE_APP)
    {
        $path = '/confirmProtocolSign';
        $data = [
            'requestNo' => $bizNo,
            'originalRequestNo' => $smsBizNo,
            'validatecode' => $code,
            'orderSource' => $orderSource,
        ];
        $response = self::_curl(env('PAY_SERVICE_URL') . $path,
            self::METHOD_POST, $data, self::DATA_TYPE_JSON);

        return self::handleResponseV2($response);
    }

    public static function preRoute($bizNo, $source, $userData)
    {
        $path = '/api/v1/order/preRoute';
        $data = [
            'requestNo' => $bizNo,
            'name' => $userData['name'],
            'identity' => $userData['identity'],
            'bankCode' => $userData['bank_code'],
            'bankCardNo' => $userData['card_no'],
            'bankCardPhone' => $userData['reserved_phone'],
            'appVersion' => $userData['app_version'],
            'channel' => Constant::USER_SOURCE_DICT[$source],
            'phone' => $userData['phone'],
            'platform' => $userData['device_type_name'],
        ];
        $response = self::_curl(env('CAPITAL_SERVICE_URL') . $path, self::METHOD_POST, $data, self::DATA_TYPE_JSON, 10);

        return self::handleResponse($response);
    }

    public static function openAccount($bizNo, $userData, $callBackUrl)
    {
        $path = '/api/v1/account/openAccount';
        $data = [
            'requestNo' => $bizNo,
            'channelProduct' => self::CHANNEL_PRODUCT,
            'channelNum' => self::CHANNEL_NUM,
            'userId' => $userData['uid'],
            'sex' => (Utils::getSexByIdentity($userData['identity']) == Constant::GENDER_MEN) ? '男' : '女',
            'accountType' => 1,
            'name' => $userData['name'],
            'identity' => $userData['identity'],
            'bankCode' => $userData['bank_code'],
            'bankCardNo' => $userData['card_no'],
            'bankCardPhone' => $userData['reserved_phone'],
            'registerPhone' => $userData['phone'],
            'region' => $userData['county_name'],
            'city' => $userData['city_name'],
            'province' => $userData['province_name'],
            'identityPhotoFront' => $userData['front_url'],
            'identityPhotoBack' => $userData['back_url'],
            'callBackUrl' => urlencode($callBackUrl),
        ];
        $response = self::_curl(env('CAPITAL_SERVICE_URL') . $path, self::METHOD_POST, $data, self::DATA_TYPE_JSON);

        return self::handleResponse($response);
    }

    public static function openAccountResult($userData, $capitalLabel)
    {
        $path = '/api/v1/account/userOpenAccountResultQuery';
        $data = [
            'requestNo' => Utils::genBizNo(),
            'bankCardNo' => $userData['card_no'],
            'identity' => $userData['identity'],
            'asset' => $capitalLabel,
        ];
        $response = self::_curl(env('CAPITAL_SERVICE_URL') . $path, self::METHOD_POST, $data, self::DATA_TYPE_JSON);

        return self::handleResponse($response);
    }

    public static function orderPush($bizNo, $userData, $orderData)
    {
        $path = '/api/v1/order/loan';
        $data = [
            'requestNo' => $bizNo,
            'channelProduct' => self::CHANNEL_PRODUCT,
            'channelNum' => self::CHANNEL_NUM,
            'vendor' => $orderData['capital_label'],
            'orderId' => $orderData['biz_no'],
            'periods' => $orderData['periods'],
            'borrowUseType' => $orderData['capital_loan_usage'],
            'amount' => sprintf('%.2f', $orderData['amount'] / 100.0),
            'rate' => sprintf('%.2f', $orderData['interest_rate'] / 100.0),
            'platform' => $userData['device_type_name'],
            'name' => $userData['name'],
            'identity' => $userData['identity'],
            'bankCode' => $userData['bank_code'],
            'bankCardNo' => $userData['card_no'],
            'bankCardPhone' => $userData['reserved_phone'],
            'phone' => $userData['phone'],
            'idcardFrontUrl' => $userData['front_url'],
            'idcardBackUrl' => $userData['back_url'],
            'companyAddress' => $userData['company_name'],
        ];
        $response = self::_curl(env('CAPITAL_SERVICE_URL') . $path, self::METHOD_POST, $data, self::DATA_TYPE_JSON, 10);

        return self::handleResponse($response);
    }

    public static function userAuth($bizNo, $orderData, $callBackUrl = '')
    {
        $path = '/api/v1/order/withdraw';
        $data = [
            'requestNo' => $bizNo,
            'orderId' => $orderData['biz_no'],
            'callBackUrl' => $callBackUrl,
        ];
        $response = self::_curl(env('CAPITAL_SERVICE_URL') . $path, self::METHOD_POST, $data, self::DATA_TYPE_JSON);

        return self::handleResponse($response);
    }

    public static function userAuthResult($orderData)
    {
        $path = '/api/v1/order/withdrawauth/result';
        $data = [
            'requestNo' => Utils::genBizNo(),
            'orderId' => $orderData['biz_no'],
        ];
        $response = self::_curl(env('CAPITAL_SERVICE_URL') . $path, self::METHOD_GET, $data);

        return self::handleResponse($response);
    }

    public static function orderPushResult($orderData)
    {
        $path = '/api/v1/order/apply/result';
        $data = [
            'requestNo' => Utils::genBizNo(),
            'orderId' => $orderData['biz_no'],
        ];
        $response = self::_curl(env('CAPITAL_SERVICE_URL') . $path, self::METHOD_GET, $data);

        return self::handleResponse($response);
    }

    public static function loanResult($orderData)
    {
        $path = '/api/v1/order/loan/result';
        $data = [
            'requestNo' => Utils::genBizNo(),
            'orderId' => $orderData['biz_no'],
        ];
        $response = self::_curl(env('CAPITAL_SERVICE_URL') . $path, self::METHOD_GET, $data);

        return self::handleResponse($response);
    }

    public static function withdrawResult($orderData)
    {
        $path = '/api/v1/order/withdraw/result';
        $data = [
            'requestNo' => Utils::genBizNo(),
            'orderId' => $orderData['biz_no'],
        ];
        $response = self::_curl(env('CAPITAL_SERVICE_URL') . $path, self::METHOD_GET, $data);

        return self::handleResponse($response);
    }

    /**
     * 充值接口
     * @param $bizNo 业务流水号
     * @param $user 用户
     * @param $amount 金额，单位：分
     * @param $card 银行卡信息
     * @param $businessType 业务类型
     * @param string $orderSource
     * @return array|null
     * @throws CustomException
     */
    public static function recharge($bizNo, $user, $amount, $card, $businessType,
                                    $orderSource = self::ORDER_SOURCE_APP)
    {
        $path = '/directRecharge';
        $data = [
            'requestNo' => $bizNo,
            'userId' => $user['uid'],
            'businessType' => $businessType,
            'pubPriFlag' => 1,
            'channelProduct' => self::CHANNEL_PRODUCT,
            'channelNum' => self::CHANNEL_NUM,
            'bankCode' => $card['bank_code'],
            'bankCardNo' => $card['card_no'],
            'payAmount' => sprintf('%.2f', $amount / 100.0),
            'userName' => $user['name'],
            'certificateNo' => $user['identity'],
            'phoneNo' => $card['reserved_phone'],
            'orderSource' => $orderSource,
        ];
        $response = self::_curl(env('PAY_SERVICE_URL') . $path,
            self::METHOD_POST, $data, self::DATA_TYPE_JSON);
        if (!$response) { // 无结果，则处理中
            return null;
        }
        $res = json_decode($response, true);
        if (200 != $res['code']) {
            // 非200，则直接重试
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '支付异常，请重试');
        }
        return $res['data'];
    }

    /**
     * 根据流水号获取充值状态
     * @param $bizNo
     * @return array|null
     */
    public static function getRechargeStatus($bizNo)
    {
        $path = '/getPayRecord';
        $data = [
            'requestNo' => $bizNo,
        ];
        $response = self::_curl(env('PAY_SERVICE_URL') . $path,
            self::METHOD_GET, $data);
        if (!$response) { // 无结果，则处理中
            return null;
        }
        $res = json_decode($response, true);
        if (200 != $res['code']) {
            return null;
        }
        return $res['data'];
    }

    /**
     * 分扣充值接口
     * @param $bizNo 业务流水号
     * @param $user 用户
     * @param $amount 金额，单位：分
     * @param $card 银行卡信息
     * @param $businessType 业务类型
     * @param $orderBizNo  订单流水号
     * @param $orderPeriod  订单期次
     * @param string $orderSource
     * @return array|null
     * @throws CustomException
     */
    public static function deductionRecharge($bizNo, $user, $amount, $card, $businessType,
                                    $orderBizNo, $orderPeriod, $orderSource = self::ORDER_SOURCE_APP)
    {
        $path = '/deductionRepaymentRecharge';
        $data = [
            'requestNo' => $bizNo,
            'assetId' => $orderBizNo,
            'termNum' => $orderPeriod,
            'userId' => $user['uid'],
            'businessType' => $businessType,
            'pubPriFlag' => 1,
            'channelProduct' => self::CHANNEL_PRODUCT,
            'channelNum' => self::CHANNEL_NUM,
            'bankCode' => $card['bank_code'],
            'bankCardNo' => $card['card_no'],
            'payAmount' => sprintf('%.2f', $amount / 100.0),
            'userName' => $user['name'],
            'certificateNo' => $user['identity'],
            'phoneNo' => $card['reserved_phone'],
            'orderSource' => $orderSource,
        ];
        $response = self::_curl(env('PAY_DEDUCTION_SERVICE_URL') . $path,
            self::METHOD_POST, $data, self::DATA_TYPE_JSON);
        if (!$response) { // 无结果，则处理中
            return null;
        }
        $res = json_decode($response, true);
        if (200 != $res['code']) {
            // 非200，则直接重试
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '支付异常，请重试');
        }
        return $res['data'];
    }

    /**
     * 根据流水号获取充值状态（分扣充值）
     * @param $bizNo
     * @return array|null
     */
    public static function getDeductionRechargeStatus($bizNo)
    {
        $path = '/deductionRepaymentQuery';
        $data = [
            'requestNo' => $bizNo,
        ];
        $response = self::_curl(env('PAY_DEDUCTION_SERVICE_URL') . $path,
            self::METHOD_GET, $data);
        if (!$response) { // 无结果，则处理中
            return null;
        }
        $res = json_decode($response, true);
        if (200 != $res['code']) {
            return null;
        }
        return $res['data'];
    }

}

