<?php
/**
 * 业务事件统一处理
 * User: hexuefei
 * Date: 2019-08-12
 * Time: 14:44
 */

namespace App\Services;


class HulkEventService
{
    const EVENT_TYPE_ORDER_CREATION             = 0;
    const EVENT_TYPE_RISK_EVALUATION_CREATION   = 1;
    const EVENT_TYPE_CONTRACT                   = 2;

    const EVENT_TYPE_MAP = [
        self::EVENT_TYPE_ORDER_CREATION             => '\App\Services\HulkEvents\OrderCreationEvent',
        self::EVENT_TYPE_RISK_EVALUATION_CREATION   => '\App\Services\HulkEvents\RiskCreationEvent',
        self::EVENT_TYPE_CONTRACT                   => '\App\Services\HulkEvents\ContractEvent',
    ];

    public function handle($eventType, $params)
    {
        $class = self::EVENT_TYPE_MAP[$eventType];
        $obj = new $class();
        return $obj->handle($params);
    }
}
