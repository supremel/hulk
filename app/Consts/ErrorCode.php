<?php
/**
 * 自定义错误码
 * User: hexuefei
 * Date: 2019-06-22
 * Time: 20:58
 */

namespace App\Consts;


class ErrorCode
{
    const SUCCESS = 0;

    const RISK_RESPONSE_CODE_RESEND = 205; //需要验证码

    /**
     * 1xxx for common error
     */
    const COMMON_SYSTEM_ERROR = 1000;
    const COMMON_PARAM_ERROR = 1001;
    const COMMON_ILLEGAL_REQUEST = 1002;
    const COMMON_SIGN_ERROR = 1003;
    const COMMON_CAPTCHA_TENCENT = 1004;
    const COMMON_CUSTOM_ERROR = 1005;

    /**
     * 2xxx for user error
     */
    const USER_NEED_LOGIN = 2000;
    const USER_NOT_EXISTED = 2001;
    const USER_FROM_API = 2002;
    const USER_BANK_CARD_BINDED = 2003;

    const CODE_MSG_DICT = [
        self::SUCCESS => '成功',
        self::COMMON_SYSTEM_ERROR => '系统错误',
        self::COMMON_PARAM_ERROR => '参数错误',
        self::COMMON_ILLEGAL_REQUEST => '非法请求',
        self::COMMON_SIGN_ERROR => '签名错误',
        self::COMMON_CAPTCHA_TENCENT => '007',

        self::USER_NEED_LOGIN => '用户未登录',
        self::USER_NOT_EXISTED => '用户不存在',
    ];
}