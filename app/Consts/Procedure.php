<?php
/**
 * 常量定义
 * User: hexuefei
 * Date: 2019-06-23
 * Time: 18:56
 */

namespace App\Consts;


class Procedure
{
    // 风控类型
    const RISK_FIRST    = 1; // 第一次风控
    const RISK_SECOND   = 2; // 第二次风控
    const RISK_QUOTA    = 1; //额度授信
    const RISK_ORDER    = 2; //借款授信

    // 流程运行中锁
    const STATE_RUNING_NO       = 0; // 未运行 
    const STATE_RUNING_LOCK     = 1; // 运行中 

    // 是否进行了关联操作
    const OPERATE_OK = 0; // 已操作
    const OPERATE_NO = 1; // 未操作

    // 是否已经提交
    const SUBMIT_NO = 0; // 未提交
    const SUBMIT_OK = 1; // 已提交

    // 是否需要开户，0：不需要，1：需要
    const NONEED_OPEN_ACCOUNT   = 0;
    const NEED_OPEN_ACCOUNT     = 1;

    // 借款用途
    const LOAN_USAGE_DAILY_CONSUME = '20404'; // 笑脸借款用途，日常消费（日常大项开支周转）

    // 资方放款验证72小时超时失效
    const USER_AUTH_EXPIRE_CODE = 2003;

    // 资方进件风控失败
    const ORDER_PUSH_RISK_FAILED_CODE = 2000;

    // 授权类型
    const AUTH_TYPE_LOAN = 0; // 放款授权

    // 是否支持循环贷
    const RECYCLING_LOAN = false;

    // 流程样板
    const SAMPLE_XIAOLIAN   = 1;
    const SAMPLE_NORMAL     = 2;
    
    // 流程模式
    const MODE_NORMAL     = 1;
    const MODE_SECOND     = 2;

    // 流程节点
    const STATE_FIRST_RISK              = 1; // 第一次风控审核
    const STATE_FIRST_RISK_FAILED       = -1; // 第一次风控审核失败(流程终态)
    const STATE_CAPITAL_ROUTE           = 2; // 资方路由
    const STATE_CAPITAL_ROUTE_FAILED    = -2; // 资金路由失败(流程终态)
    const STATE_OPEN_ACCOUNT            = 3; // 用户开户
    const STATE_OPEN_ACCOUNT_FAILED     = -3; // 开户失败(流程终态)
    const STATE_ORDER_SUBMIT            = 4; // 提交借款订单
    const STATE_SECOND_RISK             = 5; // 第二次风控审核
    const STATE_SECOND_RISK_FAILED      = -5; // 第二次风控审核失败(流程终态)
    const STATE_ORDER_PUSH              = 6; // 资方进件
    const STATE_ORDER_PUSH_FAILED       = -6; // 资方进件失败(流程终态)
    const STATE_USER_AUTH               = 7; // 用户授权
    const STATE_USER_AUTH_FAILED        = -7; // 授权失败(流程终态)
    const STATE_LOAN                    = 8; // 放款
    const STATE_LOAN_FAILED             = -8; // 放款失败(流程终态)
    const STATE_WITHDRAW                = 9; // 提现
    const STATE_WITHDRAW_FAILED         = -9; // 提现失败(流程终态)
    const STATE_SUCCESS                 = 10; // 放款成功

}


