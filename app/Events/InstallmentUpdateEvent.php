<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-08-22
 * Time: 13:36
 */

namespace App\Events;


class InstallmentUpdateEvent
{

    public $orderId;

    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }
}