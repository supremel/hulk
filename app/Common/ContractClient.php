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

class ContractClient extends HttpClient
{
    private static function handleResponse($response)
    {
        if (!$response) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '法大大接口异常');
        }
        $res = json_decode($response, true);
        if (1000 != $res['code'] && 'success' != $res['result']) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, @$res['msg']);
        }
        return $res;
    }

    private static function handleResponse2($response)
    {
        if (!$response) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '法大大接口异常');
        }
        $res = json_decode($response, true);
        if (1000 != $res['code'] && 'success' != $res['result'] && 2002 != $res['code']) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, @$res['msg']);
        }
        return $res;
    }

    /**
     * 3des加密
     * @param $str
     * @param $key
     * @return string
     */
    protected static function encrypt($str,$key){
        $str = self::pkcs5Pad($str, 8);
        if (strlen($str) % 8) {
            $str = str_pad($str,strlen($str) + 8 - strlen($str) % 8, "\0");
        }
        $sign =openssl_encrypt($str,'DES-EDE3', $key,OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);
        return strtoupper(bin2hex($sign));
    }
    /**
     * 计算加密填充
     * @param $text
     * @param $blocksize
     * @return string
     */
    protected static function pkcs5Pad($text, $blocksize) {
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }

    /**
     * 法大大-个人CA申请接口
     * @param $name
     * @param $idCard
     * @param $mobile
     * @return string
     * @throws CustomException
     */
    public static function genUserCa($name, $idCard, $mobile)
    {
        $path = '/api/syncPerson_auto.api';

        #调用法大大接口参数
        $timestamp = date("YmdHis");
        $appId = env('FDD_APP_ID');
        $appSecret = env('FDD_APP_SECRET');
        $idMobile = $idCard . "|" . $mobile;

        $params = [
            'app_id' => $appId,
            'timestamp' => $timestamp,
            'customer_name' => $name,
            'id_mobile' => self::encrypt($idMobile, $appSecret),
            'msg_digest' => base64_encode(strtoupper(sha1($appId . strtoupper(md5($timestamp)) . strtoupper(sha1($appSecret))))),
        ];

        $response = self::_curl(env('FDD_SERVICE_URL') . $path, self::METHOD_POST, $params);

        return self::handleResponse($response);
    }

    /**
     * 法大大-上传合同
     * @param $contractId
     * @param $docTitle
     * @param $docUrl
     * @return string
     * @throws CustomException
     */
    public static function uploadDoc($contractId, $docTitle, $docUrl)
    {
        $path = '/api/uploaddocs.api';

        #调用法大大接口参数
        $timestamp = date("YmdHis");
        $appId = env('FDD_APP_ID');
        $appSecret = env('FDD_APP_SECRET');

        $params = [
            'app_id' => $appId,
            'timestamp' => $timestamp,
            'contract_id' => $contractId,
            'doc_title' => $docTitle,
            'doc_url' => $docUrl,
            'doc_type' => '.pdf',
            'msg_digest' => base64_encode(strtoupper(sha1($appId . strtoupper(md5($timestamp)) . strtoupper(sha1($appSecret . $contractId))))),
        ];

        $response = self::_curl(env('FDD_SERVICE_URL') . $path, self::METHOD_POST, $params);

        return self::handleResponse2($response);
    }

    /**
     * 法大大-签署合同
     * @param $contractId
     * @param $customerId
     * @param $clientRole
     * @param $docTitle
     * @param $signKeyword
     * @return string
     * @throws CustomException
     */
    public static function signDoc($contractId, $customerId, $clientRole, $docTitle, $signKeyword)
    {
        $path = '/api/extsign_auto.api';

        #调用法大大接口参数
        $timestamp = date("YmdHis");
        $appId = env('FDD_APP_ID');
        $appSecret = env('FDD_APP_SECRET');
        $transactionId = Utils::genBizNo(20);

        $params = [
            'app_id' => $appId,
            'timestamp' => $timestamp,
            'transaction_id' => $transactionId,
            'contract_id' => $contractId,
            'customer_id' => $customerId,
            'client_role' => $clientRole,
            'doc_title' => $docTitle,
            'sign_keyword' => $signKeyword,
            'msg_digest' => base64_encode(strtoupper(sha1($appId . strtoupper(md5($transactionId . $timestamp)) . strtoupper(sha1($appSecret . $customerId))))),
        ];

        $response = self::_curl(env('FDD_SERVICE_URL') . $path, self::METHOD_POST, $params);

        return self::handleResponse($response);
    }

    /**
     * 法大大-归档合同
     * @param $contractId
     * @return string
     * @throws CustomException
     */
    public static function filingDoc($contractId)
    {
        $path = '/api/contractFiling.api';

        #调用法大大接口参数
        $timestamp = date("YmdHis");
        $appId = env('FDD_APP_ID');
        $appSecret = env('FDD_APP_SECRET');

        $params = [
            'app_id' => $appId,
            'timestamp' => $timestamp,
            'contract_id' => $contractId,
            'msg_digest' => base64_encode(strtoupper(sha1($appId . strtoupper(md5($timestamp)) . strtoupper(sha1($appSecret . $contractId))))),
        ];

        $response = self::_curl(env('FDD_SERVICE_URL') . $path, self::METHOD_POST, $params);

        return self::handleResponse($response);
    }

}

