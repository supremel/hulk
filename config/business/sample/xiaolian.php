<?php

use App\Consts\Procedure;

return [

    // 模式分配
    'mode_rate' => [
        Procedure::MODE_NORMAL => 80,
        Procedure::MODE_SECOND => 20,
    ],

    // 流程节点流
    'state_flow' => [
        Procedure::MODE_NORMAL => [
            Procedure::STATE_OPEN_ACCOUNT,
            Procedure::STATE_ORDER_SUBMIT, 
            Procedure::STATE_SECOND_RISK, 
            Procedure::STATE_ORDER_PUSH,
            Procedure::STATE_USER_AUTH,
            Procedure::STATE_LOAN,
            Procedure::STATE_WITHDRAW,
            Procedure::STATE_SUCCESS,
        ],
        Procedure::MODE_SECOND => [
            Procedure::STATE_ORDER_SUBMIT, 
            Procedure::STATE_OPEN_ACCOUNT,
            Procedure::STATE_SECOND_RISK, 
            Procedure::STATE_ORDER_PUSH,
            Procedure::STATE_USER_AUTH,
            Procedure::STATE_LOAN,
            Procedure::STATE_WITHDRAW,
            Procedure::STATE_SUCCESS,
        ],
    ],

];
