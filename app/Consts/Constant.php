<?php
/**
 * 常量定义
 * User: hexuefei
 * Date: 2019-06-23
 * Time: 18:56
 */

namespace App\Consts;


class Constant
{
    // 客服电话
    const CUSTOMER_SERVICE_TEL = '4000-611-622';

    // 流程节点时间限制
    const QUOTA_ACTIVATE_TIME = 3; // 3天, 额度几日内激活有效
    const QUOTA_APPROVE_TIME = 30; // 30秒， 额度审批时间（秒）
    const BORROW_ORDER_CONFIRM_TIME = 3; // 3天，开户成功后几日内确认借款
    const WITHDRAW_FINISH_TIME = 30; // 借款审核通过，多少分钟内完成放款验证
    const WITHDRAW_REMIND_TIME = 2; // 放款验证提醒，多少小时内完成放款验证

    // 性别
    const GENDER_MEN = 0;
    const GENDER_WOMEN = 1;

    const GENDER_LIST = [
        self::GENDER_MEN => '男',
        self::GENDER_WOMEN => '女',
    ];

    // 注册渠道
    const REGISTER_CHANNEL_APP = 'APPBULLION';
    const REGISTER_CHANNEL_QIHOO360 = 'QIHOO360';
    const REGISTER_CHANNEL_RONGSHU = 'RONGSHU';
    const REGISTER_CHANNEL_RUO360 = 'RUO360';
    const REGISTER_CHANNEL_BLACKFISH = 'BLACKFISH';
    const REGISTER_CHANNEL_YANGQIANGUAN = 'YANGQIANGUAN';
    const REGISTER_CHANNEL_QUNAJIE = 'QUNAJIE';
    const REGISTER_CHANNEL_SINA = 'SINA';
    const API_CHANNELS = [
        self::REGISTER_CHANNEL_QIHOO360,
        self::REGISTER_CHANNEL_RONGSHU,
        self::REGISTER_CHANNEL_RUO360,
        self::REGISTER_CHANNEL_BLACKFISH,
        self::REGISTER_CHANNEL_YANGQIANGUAN,
        self::REGISTER_CHANNEL_QUNAJIE,
        self::REGISTER_CHANNEL_SINA,
    ];
    // 用户来源，0：app
    const USER_SOURCE_APP = 0;
    const USER_SOURCE_QIHOO360 = 1;
    const USER_SOURCE_RONGSHU = 2;
    const USER_SOURCE_RUO360 = 3;
    const USER_SOURCE_BLACKFISH = 4;
    const USER_SOURCE_YANGQIANGUAN = 5;
    const USER_SOURCE_QUNAJIE = 6;
    const USER_SOURCE_SINA = 7;
    const USER_SOURCE_DICT = [
        self::USER_SOURCE_APP => self::REGISTER_CHANNEL_APP,
        self::USER_SOURCE_QIHOO360 => self::REGISTER_CHANNEL_QIHOO360,
        self::USER_SOURCE_RONGSHU => self::REGISTER_CHANNEL_RONGSHU,
        self::USER_SOURCE_RUO360 => self::REGISTER_CHANNEL_RUO360,
        self::USER_SOURCE_BLACKFISH => self::REGISTER_CHANNEL_BLACKFISH,
        self::USER_SOURCE_YANGQIANGUAN => self::REGISTER_CHANNEL_YANGQIANGUAN,
        self::USER_SOURCE_QUNAJIE => self::REGISTER_CHANNEL_QUNAJIE,
        self::USER_SOURCE_SINA => self::REGISTER_CHANNEL_SINA,
    ];

    const USER_RISK_SOURCE_DICT = [
        self::USER_SOURCE_APP => 'APPBULLION',
    ];

    // 用户授信，0：用户触发，1：系统触发
    const RISK_TRIGGER_TYPE_USER = 0;
    const RISK_TRIGGER_TYPE_SYSTEM = 1;

    // 风险评估序号　
    const RISK_EVALUATION_INDEX_ONE = 1;// 第一次
    const RISK_EVALUATION_INDEX_TWO = 2; // 第二次

    // 设备类型
    const DEVICE_TYPE_IOS = 0;
    const DEVICE_TYPE_ANDROID = 1;

    // 年龄限制
    const USER_AGE_MIN = 22;
    const USER_AGE_MAX = 45;

    // 银行卡类型
    const BANK_CARD_TYPE_DEBIT = 0; // 借记卡
    const BANK_CARD_TYPE_CREDIT = 1; // 贷记卡（信用卡）

    // 银行卡授权类型
    const BANK_CARD_AUTH_TYPE_AUTH = 0; // 认证银行卡
    const BANK_CARD_AUTH_TYPE_REPAY = 1; // 还款银行卡

    // 通用状态
    const COMMON_STATUS_FAILED = -1;
    const COMMON_STATUS_INIT = 0;
    const COMMON_STATUS_SUCCESS = 1;
    const COMMON_STATUS_PART_SUCCESS = 2;

    // 上报数据类型
    const DATA_TYPE_CONTACTS = 1; // 通讯录
    const DATA_TYPE_CALLS = 2; // 本机通话记录
    const DATA_TYPE_SMS = 3; // 短信
    const DATA_TYPE_APPLIST = 4; // APP安装列表
    const DATA_TYPE_DEVICE_INFO = 6; // 设备信息
    const DATA_TYPE_PHONE = 7; // 运营商（手机号）
    const DATA_TYPE_TAOBAO = 8; // 淘宝
    const DATA_TYPE_WHITE_KNIGHT = 101; // 白骑士
    const DATA_TYPE_FACE = 102; // 人脸识别
    const DATA_TYPE_BANK = 1001; // 银行卡
    const DATA_TYPE_BASE = 1002; // 基础信息
    const DATA_TYPE_RELATIONSHIP = 1003; // 紧急联系人
    const DATA_TYPE_IDCARD = 1004; // 身份证
    const DATA_TYPE_JD = 1005;
    const DATA_TYPE_MEITUAN = 1006;
    const DATA_TYPE_DIDI = 1007;
    const DATA_TYPE_REAL_NAME = 20000;
    const DATA_TYPE_THIRD = 30000;

    // 认证状态
    const AUTH_STATUS_REQUEST_FAILED = -3;
    const AUTH_STATUS_EXPIRED = -2;
    const AUTH_STATUS_FAILED = -1;
    const AUTH_STATUS_INIT = 0;
    const AUTH_STATUS_ONGOING = 1;
    const AUTH_STATUS_SUCCESS = 2;

    // 文件类型
    const FILE_TYPE_BANNER = -2;
    const FILE_TYPE_ICON = -1;
    const FILE_TYPE_ID_CARD_FRONT = 0;
    const FILE_TYPE_ID_CARD_BACK = 1;
    const FILE_TYPE_FACE = 2;
    const FILE_TYPE_CONTRACT = 3;
    const IMG_EXTENSION_LIST = ['png', 'jpg', 'jpeg',];

    // 借款金额
    const AMOUNT_MIN = 300000; // 最小可借金额
    const AMOUNT_MAX = 2000000; // 最大可借金额
    const AMOUNT_STEP = 10000; // 可借金额步长

    // 借款利率
    const ORDER_INTEREST_RATE = 299; // 默认的借款利率

    //接待分期状态
    const ORDER_INTEREST_STATUS_WAIT = 4; // 默认的借款利率
    const ORDER_INTEREST_STATUS_DONE = 5; // 默认的借款利率


    // 订单状态
    const ORDER_STATUS_LOAN_FAILED = -1; // 放款失败
    const ORDER_STATUS_INIT = 0; // 初始化
    const ORDER_STATUS_AUDIT = 1; // 审核中
    const ORDER_STATUS_LOAN = 2; // 放款中
    const ORDER_STATUS_WITHDRAW = 3; // 提现中
    const ORDER_STATUS_LOAN_SUCCESS = 4; // 放款成功
    const ORDER_STATUS_ONGOING = self::ORDER_STATUS_LOAN_SUCCESS; // 还款中=放款成功
    const ORDER_STATUS_PAID_OFF = 5; // 已结清
    const ORDER_STATUS_OVERDUE = -3; // 已逾期
    const ORDER_STATUS_NAME_DICT = [
        self::ORDER_STATUS_ONGOING => '待还款',
        self::ORDER_STATUS_OVERDUE => '已逾期',
        self::ORDER_STATUS_PAID_OFF => '已结清',
    ];
    const ORDER_STATUS_USED = [
        self::ORDER_STATUS_INIT,
        self::ORDER_STATUS_AUDIT,
        self::ORDER_STATUS_LOAN,
        self::ORDER_STATUS_WITHDRAW,
        self::ORDER_STATUS_LOAN_SUCCESS,
    ];
    const ORDER_STATUS_ICONS = [
        self::ORDER_STATUS_ONGOING => 'order_status_ongoing.png',
        self::ORDER_STATUS_OVERDUE => 'order_status_overdue.png',
        self::ORDER_STATUS_PAID_OFF => 'order_status_pay_off.png',
    ];

    const ORDER_STATUS_COLORS = [
        self::ORDER_STATUS_ONGOING => '#4EAD48',
        self::ORDER_STATUS_OVERDUE => '#F1614A',
        self::ORDER_STATUS_PAID_OFF => '#666666',
    ];

    // 支持的借款期次
    const BORROW_PERIODS_3 = 3;
    const BORROW_PERIODS_6 = 6;
    const BORROW_PERIODS_9 = 9;
    const BORROW_PERIODS_12 = 12;
    const BORROW_PERIODS_LIST = [
//        self::BORROW_PERIODS_3,
        self::BORROW_PERIODS_6,
        self::BORROW_PERIODS_9,
        self::BORROW_PERIODS_12
    ];

    // 充值业务类型
    const RECHARGE_BUSINESS_TYPE_RECHARGE = 1; // 会员充值
    const RECHARGE_BUSINESS_TYPE_SYYTEM_TIMING = 2;// 系统定时划扣
    const RECHARGE_BUSINESS_TYPE_COLLECTION = 3; // 催收划扣
    const RECHARGE_BUSINESS_TYPE_CUSTOMER_SERVICE = 4; // 客服划扣
    const RECHARGE_BUSINESS_TYPE_OFFLINE = 5; // 线下还款冲账
    const RECHARGE_BUSINESS_TYPE_CHOP_OFF = 6; // 砍头
    const RECHARGE_BUSINESS_TYPE_SYNC_FROM_LEGACY = 7; // 从老系统同步过来

    const RECHARGE_BUSINESS_TYPE_DICT = [
        self::RECHARGE_BUSINESS_TYPE_RECHARGE => '用户主动还款', // 会员充值
        self::RECHARGE_BUSINESS_TYPE_SYYTEM_TIMING => '系统定时划扣',// 系统定时划扣
    ];

    // 充值API类型
    const RECHARGE_API_NORMAL = 0; // 通用支付接口
    const RECHARGE_API_DEDUCTION = 1; // 分扣支付接口

    // 授权类数据mns消息类型
    const SEND_MSN_TYPE_AUTH = 1; //授权类数据
    const SEND_MSN_TYPE_DEVICE = 2; //设备类数据
    const SEND_MSN_TYPE_TPSDK = 3; //三方sdk类数据

    // 上报数据类型与数据回传mns_type对照表
    const MSN_TYPE_WITH_DATA_TYPE_MAP_AUTH = [
        self::DATA_TYPE_PHONE,
        self::DATA_TYPE_TAOBAO,
    ];
    const MSN_TYPE_WITH_DATA_TYPE_MAP_DEVICE = [
        self::DATA_TYPE_CONTACTS,
        self::DATA_TYPE_CALLS,
        self::DATA_TYPE_SMS,
        self::DATA_TYPE_APPLIST,
        self::DATA_TYPE_DEVICE_INFO
    ];
    const MSN_TYPE_WITH_DATA_TYPE_MAP_TPSDK = [
        self::DATA_TYPE_WHITE_KNIGHT,
        self::DATA_TYPE_FACE,
    ];

    // 产品类型
    const PRODUCT_TYPE_LOTUS = 2000; // 水莲

    // 还款类型
    const REPAY_TYPE_MONTHLY = 1; // 按月还款

    // 计费类型
    const FEE_TYPE_EQUAL_CAPITAL_EQUAL_INTEREST = 1; // 等本等息

    // 腾讯验证码场景id
    const TENCENT_SCENE_ID_LOGIN = 2026592183; // 登录
    const TENCENT_SCENE_ID_VERIFY_CODE = 2088796972; // 短信验证码

}
