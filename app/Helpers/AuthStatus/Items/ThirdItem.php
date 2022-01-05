<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-30
 * Time: 18:18
 */

namespace App\Helpers\AuthStatus\Items;


use App\Consts\Constant;
use App\Models\AuthInfo;

class ThirdItem implements Item
{
    public function calcStatus($userId)
    {
        if (AuthInfo::where('user_id', $userId)->where('type', Constant::DATA_TYPE_PHONE)
            ->where('status', Constant::AUTH_STATUS_SUCCESS)->exists()
//            && AuthInfo::where('user_id', $userId)->where('type', Constant::DATA_TYPE_TAOBAO)
//                ->where('status', Constant::AUTH_STATUS_SUCCESS)->exists()
        ) {
            return 1;
        }
        return 0;
    }

    public function expireStatus($userId)
    {
        return false;
    }
}