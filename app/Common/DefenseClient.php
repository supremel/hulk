<?php
/**
 * 安全&防御服务
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-08
 * Time: 14:31
 */

namespace App\Common;


class DefenseClient extends HttpClient
{
    const APP_ID = 3;
    const CLIENT_TYPE_H5 = 1;
    const CLIENT_TYPE_PC = 2;
    const CLIENT_TYPE_APP = 4;

    /**
     * 是否有风险
     * @param $phone
     * @param $userIp
     * @param $action
     * @return bool
     */
    public static function hasRisk($phone, $userIp, $action = 'SMSCodeProtection')
    {
        $data = [
            'accountType' => 4,
            'sendIp' => $userIp,
            'sendTime' => time(),
            'phoneNumber' => $phone,
            'loginSource' => '3',
            'loginType' => '1',
            'appId' => self::APP_ID,
            'Action' => $action,
        ];
        $ret = self::_curl(env('DEFENSE_SERVICE_URL') . '/protection', self::METHOD_POST, $data);
        $result = false;
        if ($ret) {
            $retJson = json_decode($ret, true);
            if ($retJson) {
                if (isset($retJson['riskType'])) {
                    $result = true;
                }
            }
        }
        return $result;
    }

    /**
     * 检查ticket是否合法
     * @param $ticket
     * @param $userIp
     * @return bool true for 合法
     */
    public static function checkTicket($ticket, $randStr, $userIp, $sceneId)
    {
        $data = [
            'userIp' => $userIp,
            'sceneId' => $sceneId,
            'rand' => $randStr,
            'ticket' => $ticket,
            'appId' => self::APP_ID,
        ];
        $result = false;
        $ret = self::_curl(env('DEFENSE_SERVICE_URL') . '/captcha007', self::METHOD_POST, $data);
        if (!$ret) {
            return $result;
        }
        $retJson = json_decode($ret, true);
        if (isset($retJson['code']) && $retJson['code'] == 0) {
            $result = true;
        }
        return $result;
    }
}
