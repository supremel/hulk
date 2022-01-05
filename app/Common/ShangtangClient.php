<?php
/**
 * 商汤api
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-08-29
 * Time: 14:31
 */

namespace App\Common;


use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShangtangClient
{
    const API_KEY = '1ff4d8e71c0f45b6b609c28fb098c891';
    const API_SECRET = '22b7bc3ed7e54ac896f7c7125fb85beb';
    const URL = 'https://v2-auth-api.visioncloudapi.com';

    /**
     * 计算签名
     * @param $nonce
     * @param $timestamp
     * @return string
     */
    private static function _genSign($nonce, $timestamp)
    {
        $payload = array(
            'API_KEY' => self::API_KEY,
            'nonce' => $nonce,
            'timestamp' => $timestamp
        );
        sort($payload);
        $signature = join($payload);
        $sign = hash_hmac("sha256", $signature, self::API_SECRET);
        return $sign;
    }

    /**
     * 计算授权信息
     * @return string
     */
    private static function _genAuthorization()
    {
        $nonce = Str::random(16);
        $timestamp = (string)time();
        $sign = self::_genSign($nonce, $timestamp, self::API_KEY);
        return "key=" . self::API_KEY . ",timestamp=" . $timestamp . ",nonce=" . $nonce . ",signature=" . $sign;
    }

    /**
     * @param $url
     * @param $auth
     * @param $data
     * @return mixed
     */
    private static function _curl($url, $auth, $data)
    {
        $ch = curl_init();
        $headers = [
            'Authorization: ' . $auth,
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $output = curl_exec($ch);
        Log::info("module=shangtang_client\turl=" . $url . "\tresponse=" . $output . "\tcurl_error=" . curl_error($ch));
        $ret = json_decode($output, true);
        curl_close($ch);
        return $ret;
    }

    /**
     * OCR
     * @param $fileContentBase64
     * @return null
     */
    public static function ocr($fileContentBase64)
    {
        $path = '/ocr/idcard/stateless';
        $auth = self::_genAuthorization();
        $data = [
            'image_base64' => $fileContentBase64,
        ];
        $ret = self::_curl(self::URL . $path, $auth, $data);
        if ($ret && 1000 == $ret['code']) {
            return $ret;
        }
        Log::warning("module=shangtang_client\terror=ocr error\tret=" . @json_encode($ret));
        return null;
    }
}