<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-08
 * Time: 16:44
 */

namespace App\Helpers;

use App\Common\OssClient;
use App\Consts\Constant;
use App\Helpers\AuthStatus\AuthStatus;
use App\Models\AddrInfo;
use App\Models\BankCard;
use App\Models\BaseInfo;
use App\Models\DeviceInfo;
use App\Models\IdCard;
use App\Models\OrderInstallments;
use App\Models\Orders;
use App\Models\Users;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserHelper
{
    protected static $_auth_require = [
        Constant::DATA_TYPE_REAL_NAME,
        Constant::DATA_TYPE_BASE,
        Constant::DATA_TYPE_RELATIONSHIP,
        Constant::DATA_TYPE_BANK,
        Constant::DATA_TYPE_THIRD,
    ];

    protected static $_auth_unvalid = [
        Constant::DATA_TYPE_PHONE,
        //Constant::DATA_TYPE_TAOBAO,
        Constant::DATA_TYPE_FACE,
    ];

    public static function checkUserAuth(int $userId)
    {
        $authStatus = new AuthStatus();
        foreach (self::$_auth_require as $auth) {
            if (!$authStatus->getAuthItemStatus($userId, $auth)) {
                return false;
            }
        }
        return true;
    }

    public static function getUserAuth(int $userId)
    {
        $result = [];
        $authStatus = new AuthStatus();
        foreach (self::$_auth_require as $auth) {
            $result[$auth] = $authStatus->getAuthItemStatus($userId, $auth) ? true : false;
        }
        return $result;
    }

    public static function resetUserAuth(int $userId, array $unvalid)
    {
        $unvalid = array_intersect(self::$_auth_unvalid, $unvalid);
        if (!empty($unvalid)) {
            // 开启事务
            try {
                DB::transaction(function () use ($userId, $unvalid) {
                    $authStatus = new AuthStatus();
                    foreach ($unvalid as $auth) {
                        $authStatus->expireAuthItemStatus($userId, $auth);
                    }
                });
            } catch (\Exception $e) {
                Log::warning($e->getMessage());
                return false;
            }
        }

        return true;
    }

    public static function getUserData(int $userId)
    {
        $userData = Users::find($userId)->toArray();
        $backCard = BankCard::where(['user_id' => $userId, 'type' => Constant::BANK_CARD_AUTH_TYPE_AUTH, 'status' => Constant::AUTH_STATUS_SUCCESS])->first();
        $baseInfo = BaseInfo::where(['user_id' => $userId, 'status' => Constant::AUTH_STATUS_SUCCESS])->first();
        $idCard = IdCard::where(['user_id' => $userId, 'status' => Constant::AUTH_STATUS_SUCCESS])->first();
        $deviceInfo = DeviceInfo::where(['user_id' => $userId, 'status' => Constant::COMMON_STATUS_SUCCESS])->first();

        $userData['reserved_phone'] = empty($backCard) ? '' : $backCard->reserved_phone;

        $userData['education'] = empty($baseInfo) ? '' : $baseInfo->education;
        $userData['industry'] = empty($baseInfo) ? '' : $baseInfo->industry;
        $userData['company_name'] = empty($baseInfo) ? '' : $baseInfo->company_name;
        $userData['month_income'] = empty($baseInfo) ? '' : $baseInfo->month_income;
        $userData['addr'] = empty($baseInfo) ? '' : $baseInfo->addr;
        $userData['email'] = empty($baseInfo) ? '' : $baseInfo->email;
        $userData['province'] = empty($baseInfo) ? 0 : $baseInfo->province;
        $userData['city'] = empty($baseInfo) ? 0 : $baseInfo->city;
        $userData['county'] = empty($baseInfo) ? 0 : $baseInfo->county;

        $userData['county_name'] = $userData['city_name'] = $userData['province_name'] = '';
        $addrInfo = AddrInfo::whereIn('code', [$userData['province'], $userData['city'], $userData['county']])->get()->toArray();
        if ($addrInfo && $addrInfo = array_column($addrInfo, 'name', 'code')) {
            $userData['province_name'] = $addrInfo[$userData['province']];
            $userData['city_name'] = $addrInfo[$userData['city']];
            $userData['county_name'] = $addrInfo[$userData['county']];
        }

        $userData['ethnicity'] = empty($idCard) ? '' : $idCard->ethnicity;
        $userData['issued_by'] = empty($idCard) ? '' : $idCard->issued_by;
        $userData['id_card_addr'] = empty($idCard) ? '' : $idCard->addr;
        $userData['id_card_start_time'] = empty($idCard) ? '' : $idCard->start_time;
        $userData['id_card_end_time'] = empty($idCard) ? '' : $idCard->end_time;
        $userData['front_id'] = empty($idCard) ? 0 : $idCard->front_id;
        $userData['back_id'] = empty($idCard) ? 0 : $idCard->back_id;

        $userData['front_url'] = empty($userData['front_id']) ? '' : OssClient::getUrlByFilename(Constant::FILE_TYPE_ID_CARD_FRONT, $userData['front_id']);
        $userData['back_url'] = empty($userData['back_id']) ? '' : OssClient::getUrlByFilename(Constant::FILE_TYPE_ID_CARD_BACK, $userData['back_id']);

        $userData['app_version'] = empty($deviceInfo) ? '' : $deviceInfo['version'];
        $userData['device_type'] = empty($deviceInfo) ? -1 : $deviceInfo['device_type'];
        $userData['device_type_name'] = ($userData['device_type'] == Constant::DEVICE_TYPE_IOS) ? 'IOS' :
            (($userData['device_type'] == Constant::DEVICE_TYPE_ANDROID) ? 'Android' : '');

        return $userData;
    }

    /**
     * 逾期天数
     * @param $userId
     * @return bool
     */
    public static function overdueDays($userId)
    {
        $installs = OrderInstallments::where('user_id', $userId)
            ->where('status', Constant::ORDER_STATUS_ONGOING)->get()->toArray();
        foreach ($installs as $install) {
            if (0 != $install['fee']) {
                return $install['overdue_days'];
            }
        }
        return 0;
    }

    /**
     * 获取用户已使用的授信额度
     * @param $userId
     * @return int
     */
    public static function getUserUsedAuthedAmount($userId)
    {
        $orderList = Orders::where('user_id', $userId)->whereIn('status', Constant::ORDER_STATUS_USED)->get()->toArray();
        return empty($orderList) ? 0 : array_sum(array_column($orderList, 'amount'));
    }
}
