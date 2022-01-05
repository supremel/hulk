<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-09
 * Time: 10:36
 */

namespace App\Helpers;


use App\Common\AlertClient;
use App\Common\AuthCenterClient;
use App\Common\MnsClient;
use App\Common\Utils;
use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Consts\Scheme;
use App\Exceptions\CustomException;
use App\Models\AuthInfo;
use App\Models\DeviceInfo;
use Illuminate\Support\Facades\DB;

class AuthCenter
{
    const AUTH_SCENE_DEFAULT = 0;
    const AUTH_SCENE_FIRST_RISK = 1;
    const AUTH_SCENE_ORDER_CREATION = 2;
    const AUTH_SCENE_SECOND = 3;

    /**
     * 保存授权信息（白骑士&设备）
     * @param $userInfo
     * @param $bqsToken
     * @param $deviceToken
     * @param $scene
     */
    public static function saveAuthInfoOfBqsDevice($userInfo, $bqsToken, $deviceToken, $scene)
    {
        try {
            DB::transaction(function () use ($userInfo, $bqsToken, $deviceToken, $scene) {
                $bizNo = Utils::genBizNo();
                AuthInfo::create(
                    [
                        'biz_no' => $bizNo,
                        'user_id' => $userInfo['id'],
                        'type' => Constant::DATA_TYPE_WHITE_KNIGHT,
                        'status' => Constant::AUTH_STATUS_SUCCESS,
                        'scene' => $scene,
                        'extra' => json_encode(['token' => $bqsToken]),
                    ]
                );
                $bizNo = Utils::genBizNo();
                AuthInfo::create(
                    [
                        'biz_no' => $bizNo,
                        'user_id' => $userInfo['id'],
                        'type' => Constant::DATA_TYPE_DEVICE_INFO,
                        'status' => Constant::AUTH_STATUS_SUCCESS,
                        'scene' => $scene,
                        'extra' => json_encode(['requestId' => $deviceToken]),
                    ]
                );
            });
        } catch (\Exception $e) {
            AlertClient::sendAlertEmail($e);
        }

    }

    public static function getAuthUrl($userInfo, $type)
    {
        #每次请求都需要生成业务编号
        $bizNo = Utils::genBizNo(32);
        $rec = AuthInfo::create(
            [
                'biz_no' => $bizNo,
                'user_id' => $userInfo['id'],
                'type' => $type,
                'status' => Constant::AUTH_STATUS_INIT,
            ]
        );

        $deviceRes = DeviceInfo::where('user_id', $userInfo['id'])->first()->toArray();

        if (!$deviceRes || $deviceRes['status'] != Constant::COMMON_STATUS_SUCCESS) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '获取用户设备信息失败，请重试');
        }
        $path = '/v1/tp_auth/';
        $callbackUrl = env('APP_URL') . $path . 'callback?biz_no=' . $bizNo;
        $asyncUrl = env('APP_URL') . $path . 'async_notify?biz_no=' . $bizNo;
        $args['request_id'] = $bizNo;
        $args['data_type'] = $type;
        $args['uid'] = $userInfo['uid'];
        $args['old_user_id'] = $userInfo['old_user_id'];
        $args['product'] = Constant::PRODUCT_TYPE_LOTUS;
        $args['device_id'] = $deviceRes['device_id'];
        $args['machine_id'] = $deviceRes['imei'];
        $args['sync_url'] = $callbackUrl;
        $args['async_url'] = $asyncUrl;
        $params = [
            'remote_function' => 'auth_route_req',
            'args' => json_encode($args)
        ];

        $authRes = AuthCenterClient::authRoute($params);

        if (!$authRes) {
            AuthInfo::where('id', $rec['id'])->update(['status' => Constant::AUTH_STATUS_REQUEST_FAILED]);
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR,
                '获取授权地址失败，请重试');
        }
        $url = $authRes['auth_url']."&biz_no={$bizNo}&phone={$userInfo['phone']}";
        $tp = $authRes['third'];
        AuthInfo::where('id', $rec['id'])->update([
            'tp' => $tp,
            'status' => Constant::AUTH_STATUS_ONGOING,]);
        return ['url' => $url, 'callback_url' => Scheme::APP_CLOSE_PAGE,];
    }

    public static function handleReport($userId, $type, $data)
    {
        $bizNo = Utils::genBizNo(32);
        AuthInfo::create(
            [
                'biz_no' => $bizNo,
                'user_id' => $userId,
                'type' => $type,
                'extra' => json_encode($data),
                'status' => Constant::AUTH_STATUS_SUCCESS,
            ]
        );
    }

    /*
     * 把回调结果通知给风控
     * @param $userInfo 用户信息
     * @param $type 数据类型 7: 运营商，8: 淘宝类
     * @param $mns_type 消息类型 1:授权类数据，2:设备类数据，3:三方sdk类数据
     * @param $deviceData 设备信息
     * @param $data 三方回调数据
     */
    public static function sendAsyncDataToRisk($userInfo, $type, $mns_type, $deviceData, $data)
    {
        $mnsData = [
            'mns_type' => $mns_type,
            'data_type' => $type,
            'old_user_id' => $userInfo['old_user_id'],
            'uid' => $userInfo['uid'],
            'product' => Constant::PRODUCT_TYPE_LOTUS,
            'device_id' => $deviceData['device_id'],
            'machine_id' => $deviceData['imei'],
            'os_type' => ($deviceData['device_type'] == Constant::DEVICE_TYPE_IOS) ? 'ios' : 'android',
            'content' => $data,
        ];
        $mnsResult = MnsClient::sendMsg2Queue(env('AUTH_CENTER_ACCESS_ID'), env('AUTH_CENTER_ACCESS_KEY'), env('AUTH_CENTER_QUEUE_NAME'), json_encode($mnsData));
        return $mnsResult;

    }

    /*
     * 调风控接口查询用户数据状态
     * @param $userId
     */
    public static function getUserDataStatus($userInfo)
    {
        $deviceInfo = DeviceInfo::where('user_id', $userInfo['id'])->first();
        $args['old_user_id'] = $userInfo['old_user_id'];
        $args['uid'] = $userInfo['uid'];
        $args['product'] = Constant::PRODUCT_TYPE_LOTUS;
        $args['device_id'] = $deviceInfo['device_id'];
        $args['machine_id'] = $deviceInfo['imei'];
        $params = [
            'remote_function' => 'data_expire_req',
            'args' => json_encode($args)
        ];

        $authRes = AuthCenterClient::authRoute($params);

        return $authRes;

    }

    /*
     * 用户数据过期处理
     */
    public static function setExpireData($userId, $userData)
    {
        if (empty($userData) || empty($userData['auth_data'])) {
            return false;
        }
        #风控目前只处理auth_data
        $authData = $userData['auth_data'];
        foreach ($authData as $row) {
            $dataType = $row[0];
            $status = $row[1];
            if ($status == 2) { //有数据且已过期
                AuthInfo::where(['user_id' => $userId, 'type' => $dataType])->update(
                    ['status' => Constant::AUTH_STATUS_EXPIRED,]
                );
            }
        }
        return true;
    }
}