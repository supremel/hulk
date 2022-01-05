<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-30
 * Time: 18:10
 */

namespace App\Helpers\AuthStatus;


use App\Consts\Constant;
use App\Consts\Profile;

class AuthStatus
{

    private $_classMap = [
        Constant::DATA_TYPE_REAL_NAME => 'App\Helpers\AuthStatus\Items\RealNameItem',
        Constant::DATA_TYPE_IDCARD => 'App\Helpers\AuthStatus\Items\IdcardItem',
        Constant::DATA_TYPE_BASE => 'App\Helpers\AuthStatus\Items\BaseInfoItem',
        Constant::DATA_TYPE_RELATIONSHIP => 'App\Helpers\AuthStatus\Items\RelationShipItem',
        Constant::DATA_TYPE_BANK => 'App\Helpers\AuthStatus\Items\BankCardItem',
        Constant::DATA_TYPE_FACE => 'App\Helpers\AuthStatus\Items\FaceItem',
        Constant::DATA_TYPE_PHONE => 'App\Helpers\AuthStatus\Items\PhoneItem',
        Constant::DATA_TYPE_TAOBAO => 'App\Helpers\AuthStatus\Items\TaobaoItem',
        Constant::DATA_TYPE_THIRD => 'App\Helpers\AuthStatus\Items\ThirdItem',

        Constant::DATA_TYPE_JD => 'App\Helpers\AuthStatus\Items\JdItem',
        Constant::DATA_TYPE_MEITUAN => 'App\Helpers\AuthStatus\Items\MeituanItem',
        Constant::DATA_TYPE_DIDI => 'App\Helpers\AuthStatus\Items\DidiItem',
    ];

    /**
     * @param $userId
     * @param $dataType
     * @return mixed
     */
    public function getAuthItemStatus($userId, $dataType)
    {
        $className = $this->_classMap[$dataType];
        $authItem = new $className();
        return $authItem->calcStatus($userId);
    }

    /**
     * @param $userId
     * @param $dataType
     * @return mixed
     */
    public function expireAuthItemStatus($userId, $dataType)
    {
        $className = $this->_classMap[$dataType];
        $authItem = new $className();
        return $authItem->expireStatus($userId);
    }

    /**
     * 是否有过期认证项
     * @param $userId
     * @return bool
     */
    public function hasExpiredAuthItem($userId)
    {
        foreach (Profile::AUTH_LIST[Profile::AUTH_TYPE_REQUIRED] as $datatype => $_) {
            $authItem = new $this->_classMap[$datatype];
            if ($authItem->calcStatus($userId) == 0) {
                return true;
            }
        }
        return false;
    }
}