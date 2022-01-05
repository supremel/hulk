<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-30
 * Time: 18:22
 */

namespace App\Helpers\AuthStatus\Items;

interface Item
{
    /**
     * @param $userId
     * @return int  0:已失效， 1:有效
     */
    public function calcStatus($userId);

    /**
     * @param $userId
     * @return boolean
     */
    public function expireStatus($userId);
}