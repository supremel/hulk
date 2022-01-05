<?php
/**
 * 优图ocr服务
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-18
 * Time: 14:31
 */

namespace App\Common;


use Illuminate\Support\Facades\Log;

class YoutuClient
{
    //腾讯优图创建账号（是qq号）
    const USER_ID = '2127322016';
    const APP_ID = '10099615';
    const SECRET_ID = 'AKIDoYCqVGHBBOBpZUA7oUzWsIrQQUP9xDax';
    const SECRET_KEY = 'a2c5iCB3nMjvGiFgzmikx17KdERXRBjB';
    const URL = 'https://api.youtu.qq.com/youtu/ocrapi/idcardocr';

    /**
     * 生成签名
     * @param $expired
     * @param string $userId
     * @return string
     */
    public static function genSign($expired, $userId = self::USER_ID)
    {
        $secretId = self::SECRET_ID;
        $secretKey = self::SECRET_KEY;
        $appId = self::APP_ID;

        $now = time();
        $rdm = rand();
        $plainText = 'a=' . $appId . '&k=' . $secretId . '&e=' . $expired . '&t=' . $now . '&r=' . $rdm . '&u=' . $userId;
        $bin = hash_hmac("SHA1", $plainText, $secretKey, true);
        $bin = $bin . $plainText;
        $sign = base64_encode($bin);

        return $sign;
    }

    /**
     * @param $url
     * @param $data
     * @param $headers
     * @return bool|string
     */
    protected static function _curl($url, $data, $headers)
    {
        $startTime = microtime(true);
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($curl);

        // log
        $logData['cost'] = intval((microtime(true) - $startTime) * 1000);
        $logData['module'] = 'thirdparty';
        $logData['startTime'] = $startTime;
        $logData['endTime'] = microtime(true);
        $logData['url'] = $url;
        $logData['method'] = 'POST';
        $logData['input'] = $data;
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

        return $result;
    }

    /**
     * 身份证ocr识别
     * @param $imgUrl
     * @param $isBack
     * @return bool|mixed|null
     */
    public static function ocrIdCard($imgUrl, $isBack)
    {
        // 失效时间30天
        $expiredSeconds = 30 * 24 * 3600;
        $sign = self::genSign(time() + $expiredSeconds);
        $headers = [
            'Authorization:' . $sign,
            'Content-Type:text/json',
            'Method:POST',
        ];
        $data = [
            'url' => $imgUrl,
            'card_type' => ($isBack) ? 1 : 0,
            'app_id' => self::APP_ID,
            'ret_image' => false,
        ];
        $data = json_encode($data);
        $response = self::_curl(self::URL, $data, $headers);
        if ($response) {
            $ret = json_decode($response, true);
            if (0 != $ret['errorcode']) {
                Log::warning("module=ocr\tret=" . $response);
                return false;
            } else {
                return $ret;
            }
        }

        return null;
    }


}