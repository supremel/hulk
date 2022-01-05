<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-30
 * Time: 18:18
 */

namespace App\Helpers\AuthStatus\Items;


use App\Consts\Constant;
use App\Models\Relationship;

class RelationShipItem implements Item
{
    public function calcStatus($userId)
    {
        if (Relationship::where('user_id', $userId)->where('status', Constant::AUTH_STATUS_SUCCESS)->exists()) {
            return 1;
        }
        return 0;
    }

    public function expireStatus($userId)
    {
        if (Relationship::where('user_id', $userId)->where('status', Constant::AUTH_STATUS_SUCCESS)->update(['status' => Constant::AUTH_STATUS_EXPIRED])) {
            return true;
        }
        return false;
    }
}