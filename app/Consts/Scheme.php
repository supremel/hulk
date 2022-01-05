<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-10
 * Time: 10:35
 */

namespace App\Consts;


class Scheme
{
    const APP_BASE_SCHEME = 'golden://app.shuilian.com';

    const APP_INDEX = self::APP_BASE_SCHEME . '/home/index';  // 首页

    const APP_ORDER_SUBMIT = self::APP_BASE_SCHEME . '/order/submit';  // 订单

    const APP_TRANSIT_LOADING = self::APP_BASE_SCHEME . '/transit/loading';  // 过渡页面，目前用于请求 开户h5、放款验证h5

    const APP_USER_AUTHENTICATION = self::APP_BASE_SCHEME . '/user/authentication';  // 用户认证引导页

    const APP_USER_LATEST_BILL = self::APP_BASE_SCHEME . '/account/bill';  // 用户账单

    const APP_USER_LOGIN = self::APP_BASE_SCHEME . '/user/login'; // 用户登录

    const APP_SETTINGS = self::APP_BASE_SCHEME . '/user/setting'; // 设置页

    const APP_ORDER_LIST = self::APP_BASE_SCHEME . '/user/orders'; // 借款记录（订单）

    const APP_AUTH_CENTER = self::APP_BASE_SCHEME . '/user/profile'; // 认证中心

    const APP_ABOUT_US = self::APP_BASE_SCHEME . '/user/about_us'; // 关于我们

    const APP_BILL_QUICK_REPAY = self::APP_BASE_SCHEME . '/method/quick_repay'; // 快捷还款

    const APP_BILL_BIND_CARD = self::APP_BASE_SCHEME . '/method/bind_card'; // 绑定新银行卡

    const APP_BILL_OFFLINE_REPAY = self::APP_BASE_SCHEME . '/method/offline_repay'; // 线下还款

    const APP_WEBVIEW_FORMAT = self::APP_BASE_SCHEME . '/h5/safe_webview?url=%s&title=%s';  // 需要登录的h5页面

    const APP_WEBVIEW_NONEED_LOGIN_FORMAT = self::APP_BASE_SCHEME . '/h5/webview?url=%s&title=%s'; // 未需要登录的h5页面

    const H5_HELP_CENTER = '/sl/helpcenter/'; // 帮助中心

    const H5_COMING_SOON = '/sl/app/coming_soon/'; // 敬请期待

    const H5_OVERDUE_NOTIFY_FORMAT = '/sl/zhengxin2?userName=%s&headDesc=%s&buttonTxt=%s&buttonLink=%s&code=%s'; // 逾期告知

    const H5_LOAN_AGREEMENT = '/sl/doc/5d3ec5acdc163a2acd8159d4';  // 借款协议

    const H5_DEDUCTION_AGREEMENT = '/sl/doc/5d4a8428886a640078ec2ff2?mobile=1&__view=1';  // 委托代扣还款协议

    const H5_BORROWER_SERVICE_AGREEMENT = '/sl/doc/5d41029244a8300071c04c67?__view=1';  // 借款人服务协议

    const H5_SHUILIAN_DANBAO_AGREEMENT = '/sl/doc/5d4a89bc886a640078ec2ff3';  // 委托担保申请

    const APP_CLOSE_PAGE = self::APP_BASE_SCHEME . '/close/page';  // 关闭页面

    const APP_USER_AUTH_REAL_NAME = self::APP_USER_AUTHENTICATION . '?type=1';  // 实名认证页

    const APP_USER_AUTH_BASE = self::APP_USER_AUTHENTICATION . '?type=2';  // 基础信息认证页

    const APP_USER_AUTH_RELATIONSHIP = self::APP_USER_AUTHENTICATION . '?type=3';  // 紧急联系人认证页

    const APP_USER_AUTH_BANK = self::APP_USER_AUTHENTICATION . '?type=4';  // 银行卡认证页

    const APP_USER_AUTH_THIRD = self::APP_USER_AUTHENTICATION . '?type=5';  // 第三方认证页

    const APP_USER_TP_AUTH_MOXIE = self::APP_BASE_SCHEME . '/credit/moxie'; // 魔蝎（淘宝认证）

    const APP_USER_TP_AUTH_PHONE = self::APP_BASE_SCHEME . '/credit/cellular'; // 运营商认证

    // 认证过程中返回/退出的跳转地址
    const AUTH_BACK_LINK = '';
}
