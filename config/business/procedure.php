<?php

use App\Consts\Procedure;
use App\Consts\Constant;

return [

    // 流程终态的用户冻结时间
    'user_frozen' => [
        Procedure::STATE_CAPITAL_ROUTE => 30,
        Procedure::STATE_OPEN_ACCOUNT => 30,
        Procedure::STATE_SECOND_RISK => 30,
        Procedure::STATE_ORDER_PUSH => 30,
        Procedure::STATE_USER_AUTH => 30,
        Procedure::STATE_LOAN => 30,
        Procedure::STATE_WITHDRAW => 30,
    ],

    // 订单状态映射
    'order_status' => [
        Procedure::STATE_SECOND_RISK => Constant::ORDER_STATUS_AUDIT,
        Procedure::STATE_LOAN => Constant::ORDER_STATUS_LOAN,
        Procedure::STATE_WITHDRAW => Constant::ORDER_STATUS_WITHDRAW,
        Procedure::STATE_SUCCESS => Constant::ORDER_STATUS_LOAN_SUCCESS,
    ],

    // 根据资方，借款用途
    'loan_usage' => [
        'xiaolian' => Procedure::LOAN_USAGE_DAILY_CONSUME,
    ],

    // 流程节点流
    'state_flow' => [
        Procedure::STATE_FIRST_RISK,
        Procedure::STATE_CAPITAL_ROUTE, 
    ],

    // 根据资方，流程分流
    'capital_shunt' => [
        'xiaolian' => Procedure::SAMPLE_XIAOLIAN,
    ],

    // 流程样板
    'sample' => [
        Procedure::SAMPLE_XIAOLIAN => 'business.sample.xiaolian',
        Procedure::SAMPLE_NORMAL => 'business.sample.normal',
    ],
    
    // 节点方法映射
    'class_map' => [
        Procedure::STATE_FIRST_RISK => '\App\Services\States\FirstRiskState',
        Procedure::STATE_CAPITAL_ROUTE => '\App\Services\States\CapitalRouteState',
        Procedure::STATE_OPEN_ACCOUNT => '\App\Services\States\OpenAccountState',
        Procedure::STATE_ORDER_SUBMIT => '\App\Services\States\OrderSubmitState',
        Procedure::STATE_SECOND_RISK => '\App\Services\States\SecondRiskState',
        Procedure::STATE_ORDER_PUSH => '\App\Services\States\OrderPushState',
        Procedure::STATE_USER_AUTH => '\App\Services\States\UserAuthState',
        Procedure::STATE_LOAN => '\App\Services\States\LoanState',
        Procedure::STATE_WITHDRAW => '\App\Services\States\WithdrawState',
    ],

];
