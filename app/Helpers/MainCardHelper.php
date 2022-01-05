<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-08
 * Time: 16:44
 */

namespace App\Helpers;

use App\Common\OssClient;
use App\Common\Utils;
use App\Consts\Constant;
use App\Consts\Procedure;
use App\Consts\Scheme;
use App\Consts\Text;
use App\Models\AuthRecords;
use App\Models\OpenAccountRecords;
use App\Models\Orders;
use App\Models\Procedures;
use App\Models\RepaymentRecords;
use App\Services\ProcedureService;

class MainCardHelper
{
    const CARD_MODE_FIRST = 1;

    const CARD_MODE_SECOND = 2;

    const CARD_MODE_THIRD = 3;

    protected static $_cardShowType = [
        self::CARD_MODE_FIRST => [
            'style' => self::CARD_MODE_FIRST,
            'left_top_txt' => '',
            'left_center_txt' => '',
            'left_bottom_txt' => '',
            'right_top_txt' => '',
            'right_top_draw' => '',
            'right_center_btn' => '',
            'right_center_btn_link' => '',
            'right_center_btn_id' => '',
        ],
        self::CARD_MODE_SECOND => [
            'style' => self::CARD_MODE_SECOND,
            'center_top_img' => '',
            'center_bottom_txt' => '',
        ],
        self::CARD_MODE_THIRD => [
            'style' => self::CARD_MODE_THIRD,
            'center_top_txt' => '',
            'center_top_draw' => '',
            'center_center_txt' => '',
            'center_bottom_btn' => '',
            'center_bottom_btn_link' => '',
            'center_bottom_btn_id' => '',
            'help_txt' => '',
        ],
    ];

    protected static $_cardShow;

    protected static function genCardShow()
    {
        self::$_cardShow = array_merge(self::$_cardShowType[self::$_cardShow['style']], self::$_cardShow);
        if (!empty(self::$_cardShow['right_top_draw'])) {
            self::$_cardShow['right_top_draw'] = OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, self::$_cardShow['right_top_draw']) . '.png';
        }
        if (!empty(self::$_cardShow['center_top_img'])) {
            self::$_cardShow['center_top_img'] = OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, self::$_cardShow['center_top_img']) . '.png';
        }
        if (!empty(self::$_cardShow['center_top_draw'])) {
            self::$_cardShow['center_top_draw'] = OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, self::$_cardShow['center_top_draw']) . '.png';
        }

        return self::$_cardShow;
    }

    public static function genMainCard($user)
    {
        if (!$user) {
            // 未登录
            self::$_cardShow = [
                'style' => self::CARD_MODE_FIRST,
                'left_top_txt' => '最高可借额度（元）',
                'left_center_txt' => number_format(Constant::AMOUNT_MAX / 100),
                'right_center_btn' => '立即借款',
                'right_center_btn_link' => Scheme::APP_USER_LOGIN,
                'statistics_id' => 'B01002',
            ];
        } else {
            // 已登录
            self::genMainCardLogin($user);
        }

        return self::genCardShow();

    }

    protected static $_forzenReason = [
        Procedure::STATE_FIRST_RISK_FAILED => '未获得授信额度',
        Procedure::STATE_CAPITAL_ROUTE_FAILED => '存管账户开户失败',
        Procedure::STATE_OPEN_ACCOUNT_FAILED => '存管账户开户失败',
        Procedure::STATE_SECOND_RISK_FAILED => '放款失败',
        Procedure::STATE_ORDER_PUSH_FAILED => '放款失败',
        Procedure::STATE_USER_AUTH_FAILED => '放款失败',
        Procedure::STATE_LOAN_FAILED => '放款失败',
        Procedure::STATE_WITHDRAW_FAILED => '放款失败',
    ];

    protected static function genMainCardLogin($user)
    {
        if (strtotime($user['frozen_end_time']) > time()) {
            // 用户冻结
            self::$_cardShow = [
                'style' => self::CARD_MODE_THIRD,
                'center_top_draw' => 'ic_fail',
                'center_top_txt' => self::$_forzenReason[$user['frozen_status']],
                'center_center_txt' => Utils::getGapTime(date('Y-m-d H:i:s'), $user['frozen_end_time']) . '天后请重新申请',
                'center_bottom_btn' => '重新申请',
            ];
        } else {
            // 未冻结
            self::genMainCardAgile($user);
        }

        return true;
    }

    protected static $_stateSubmitMap = [
        Procedure::STATE_OPEN_ACCOUNT => "calcOpenAccountStatus",
        Procedure::STATE_USER_AUTH => "calcUserAuthStatus",
    ];

    protected static $_stateSubmitShow = [
        Procedure::STATE_OPEN_ACCOUNT => [
            'style' => self::CARD_MODE_SECOND,
            'center_top_img' => 'kafei',
            'center_bottom_txt' => '存管账户开户中',
        ],
        Procedure::STATE_USER_AUTH => [
            'style' => self::CARD_MODE_SECOND,
            'center_top_img' => 'jishishalou',
            'center_bottom_txt' => '您有一笔{orderAmount}元的借款正在募集中',
        ],
    ];

    protected static $_stateCardShow = [
        Procedure::STATE_FIRST_RISK => [
            'style' => self::CARD_MODE_THIRD,
            'center_top_draw' => 'ic_check',
            'center_top_txt' => '额度正在审批中',
            'center_center_txt' => '正在进行额度审批，请留意消息通知',
        ],
        Procedure::STATE_CAPITAL_ROUTE => [
            'style' => self::CARD_MODE_THIRD,
            'center_top_draw' => 'ic_check',
            'center_top_txt' => '额度正在审批中',
            'center_center_txt' => '正在进行额度审批，请留意消息通知',
        ],
        Procedure::STATE_OPEN_ACCOUNT => [
            'style' => self::CARD_MODE_FIRST,
            'left_top_txt' => '我的可借额度（元）',
            'left_center_txt' => '{authedAmount}',
            'left_bottom_txt' => '额度一次借款有效，建议全部借出',
            'right_center_btn' => '立即开户',
            'right_center_btn_link' => Scheme::APP_TRANSIT_LOADING . "?type=1&procedure_no={procedureNo}",
            'statistics_id' => 'B01003',
        ],
        Procedure::STATE_ORDER_SUBMIT => [
            'style' => self::CARD_MODE_FIRST,
            'left_top_txt' => '我的可借额度（元）',
            'left_center_txt' => '{authedAmount}',
            'left_bottom_txt' => '额度一次借款有效，建议全部借出',
            'right_center_btn' => '立即借款',
            'right_center_btn_link' => Scheme::APP_ORDER_SUBMIT . "?procedure_no={procedureNo}",
            'statistics_id' => 'B01002',
        ],
        Procedure::STATE_SECOND_RISK => [
            'style' => self::CARD_MODE_THIRD,
            'center_top_draw' => 'ic_check',
            'center_top_txt' => '借款审批中',
            'center_center_txt' => '正在进行借款审批，请留意消息通知',
        ],
        Procedure::STATE_ORDER_PUSH => [
            'style' => self::CARD_MODE_THIRD,
            'center_top_draw' => 'ic_check',
            'center_top_txt' => '借款审批中',
            'center_center_txt' => '正在进行借款审批，请留意消息通知',
        ],
        Procedure::STATE_USER_AUTH => [
            'style' => self::CARD_MODE_THIRD,
            'center_top_draw' => 'ic_credit',
            'center_top_txt' => '待放款验证',
            'help_txt' => Text::USER_AUTH_TIP,
            'center_center_txt' => '请在30分钟内进行放款验证，以免影响借款',
            'center_bottom_btn' => '放款验证',
            'center_bottom_btn_link' => Scheme::APP_TRANSIT_LOADING . "?type=2&procedure_no={procedureNo}",
            'statistics_id' => 'B01004',
        ],
        Procedure::STATE_LOAN => [
            'style' => self::CARD_MODE_SECOND,
            'center_top_img' => 'jishishalou',
            'center_bottom_txt' => '您有一笔{orderAmount}元的借款正在募集中',
        ],
        Procedure::STATE_WITHDRAW => [
            'style' => self::CARD_MODE_SECOND,
            'center_top_img' => 'jishishalou',
            'center_bottom_txt' => '您有一笔{orderAmount}元的借款正在募集中',
        ],
    ];


    protected static function genMainCardAgile($user)
    {
        if ($procedureInfo = Procedures::where(['user_id' => $user['id'], 'status' => Constant::COMMON_STATUS_INIT])->first()) {
            // 存在在贷流程单
            $procedureService = new ProcedureService ($procedureInfo->id);
            $procedureState = $procedureService->getState();

            $isSubmit = false;

            if (isset(self::$_stateSubmitMap[$procedureState])) {
                $method = self::$_stateSubmitMap[$procedureState];
                if (self::$method($user['id'], $procedureInfo->id)) {
                    // 开户中、放款验证中
                    $isSubmit = true;
                    self::$_cardShow = self::$_stateSubmitShow[$procedureState];
                }
            }

            if (!$isSubmit) {
                self::$_cardShow = self::$_stateCardShow[$procedureState];
            }

            if (!empty(self::$_cardShow['left_center_txt'])) {
                $usedAuthedAmount = UserHelper::getUserUsedAuthedAmount($user['id']);
                self::$_cardShow['left_center_txt'] = str_replace('{authedAmount}', number_format(($user['authed_amount'] - $usedAuthedAmount) / 100), self::$_cardShow['left_center_txt']);
            }
            if (!empty(self::$_cardShow['right_center_btn_link'])) {
                self::$_cardShow['right_center_btn_link'] = str_replace('{procedureNo}', $procedureInfo->biz_no, self::$_cardShow['right_center_btn_link']);
            }
            if (!empty(self::$_cardShow['center_bottom_txt'])) {
                self::$_cardShow['center_bottom_txt'] = str_replace('{orderAmount}', number_format($procedureInfo->order_amount / 100), self::$_cardShow['center_bottom_txt']);
            }
            if (!empty(self::$_cardShow['center_bottom_btn_link'])) {
                self::$_cardShow['center_bottom_btn_link'] = str_replace('{procedureNo}', $procedureInfo->biz_no, self::$_cardShow['center_bottom_btn_link']);
            }
        } else {
            // 不存在在贷流程单
            self::genMainCardNoProcedure($user);
        }

        return true;
    }

    protected static function genMainCardNoProcedure($user)
    {
        $repayInfo = RepaymentRecords::where(['user_id' => $user['id']])->orderBy('id', 'desc')->first();
        if ($repayInfo && in_array($repayInfo->status, [Constant::COMMON_STATUS_FAILED, Constant::COMMON_STATUS_INIT])) {
            self::genMainCardRepay($repayInfo);
        } else {
            self::genMainCardNoRepay($user);
        }

        return true;
    }

    protected static function genMainCardRepay($repayInfo)
    {
        if ($repayInfo->status == Constant::COMMON_STATUS_FAILED) {
            $retData = json_decode($repayInfo->extra, true);
            // 还款失败
            self::$_cardShow = [
                'style' => self::CARD_MODE_THIRD,
                'center_top_draw' => 'ic_fail',
                'center_top_txt' => '还款失败',
                'center_center_txt' => empty($retData['resMsg']) ? '' : $retData['resMsg'],
                'center_bottom_btn' => '去还款',
                'center_bottom_btn_link' => Scheme::APP_USER_LATEST_BILL,
                'statistics_id' => 'B01005',
            ];
        } elseif ($repayInfo->status == Constant::COMMON_STATUS_INIT) {
            // 还款中
            self::$_cardShow = [
                'style' => self::CARD_MODE_SECOND,
                'center_top_img' => 'jishishalou',
                'center_bottom_txt' => '银行还款处理中',
            ];
        }

        return true;
    }

    protected static function genMainCardNoRepay($user)
    {
        if ($orderInfo = Orders::where(['user_id' => $user['id'], 'status' => Constant::ORDER_STATUS_ONGOING])->first()) {
            $orderBill = RepayCenter::getBills($orderInfo->id);
            $overdueBillNum = 0;
            $totalAmount = 0;
            $repayDate = '';
            $orderBillPeriod = 0;
            foreach ($orderBill as $bill) {
                $repayDate = $bill['date'];
                if ($bill['overdue_days'] > 0) {
                    $overdueBillNum++;
                }
                $totalAmount += $bill['capital'] - $bill['paid_capital'];
                $totalAmount += $bill['interest'] - $bill['paid_interest'];
                $totalAmount += $bill['fee'];
                $orderBillPeriod = $bill['period'];
            }

            self::$_cardShow = [
                'style' => self::CARD_MODE_FIRST,
                'left_top_txt' => '本期待还金额（元）',
                'right_top_txt' => '还款日' . date('Y/m/d', strtotime($repayDate)),
                //'right_top_draw' => 'ic_rili',
                'left_center_txt' => number_format($totalAmount / 100, 2),
                'left_bottom_txt' => '借款结清后可再次申请',
                'right_center_btn' => '去还款',
                'right_center_btn_link' => Scheme::APP_USER_LATEST_BILL,
                'statistics_id' => 'B01005',
            ];

            if ($overdueBillNum) {
                // 逾期
                self::$_cardShow['left_top_txt'] = "逾期" . $overdueBillNum . "期，待还金额（元）";
                self::$_cardShow['left_bottom_txt'] = "逾期会上报征信，请及时还款";
                self::$_cardShow['right_top_txt'] = '';
                self::$_cardShow['right_top_draw'] = '';
            } elseif ((strtotime($repayDate) > strtotime(Utils::getLastDayOfMonth(date('Y-m-d')))) && ($orderBillPeriod != 1)) {
                // 本期已结清
                self::$_cardShow['left_top_txt'] = "本期待还金额（已结清）";
                self::$_cardShow['right_top_txt'] = "下个" . self::$_cardShow['right_top_txt'];
            }
        } else {
            // 没有还款中订单
            self::genMainCardNoOrder($user);
        }

        return true;
    }

    protected static $_authShow = [
        Constant::DATA_TYPE_REAL_NAME => Scheme::APP_USER_AUTH_REAL_NAME . '&show_bar=1',
        Constant::DATA_TYPE_BASE => Scheme::APP_USER_AUTH_BASE . '&show_bar=1',
        Constant::DATA_TYPE_RELATIONSHIP => Scheme::APP_USER_AUTH_RELATIONSHIP . '&show_bar=1',
        Constant::DATA_TYPE_BANK => Scheme::APP_USER_AUTH_BANK . '&show_bar=1&card_type=0',
        Constant::DATA_TYPE_THIRD => Scheme::APP_USER_AUTH_THIRD . '&show_bar=1',
    ];

    protected static function genMainCardNoOrder($user)
    {
        if (!Procedures::where(['user_id' => $user['id']])->first() && !Orders::where(['user_id' => $user['id']])->first()) {

            $authResult = UserHelper::getUserAuth($user['id']);
            $authType = Constant::DATA_TYPE_THIRD;
            foreach ($authResult as $authItem => $authStatus) {
                if (!$authStatus) {
                    $authType = $authItem;
                    break;
                }
            }

            self::$_cardShow = [
                'style' => self::CARD_MODE_FIRST,
                'left_top_txt' => '最高可借额度（元）',
                'left_center_txt' => number_format(Constant::AMOUNT_MAX / 100),
                'right_center_btn' => '立即借款',
                'right_center_btn_link' => $authType ? self::$_authShow[$authType] : Scheme::APP_AUTH_CENTER,
                'statistics_id' => 'B01002',
            ];
        } else {
            // 非首次认证
            self::genMainCardToOrder($user);
        }

        return true;
    }

    protected static function genMainCardToOrder($user)
    {
        self::$_cardShow = [
            'style' => self::CARD_MODE_FIRST,
            'left_top_txt' => '最高可借额度（元）',
            'left_center_txt' => number_format(Constant::AMOUNT_MAX / 100),
            'right_center_btn' => '立即借款',
            'right_center_btn_link' => Scheme::APP_AUTH_CENTER,
            'statistics_id' => 'B01002',
        ];

//        if ($user['authed_amount']) {
//            self::$_cardShow['left_top_txt'] = "我的可借额度（元）";
//            $usedAuthedAmount = UserHelper::getUserUsedAuthedAmount($user['id']);
//            self::$_cardShow['left_center_txt'] = number_format(($user['authed_amount'] - $usedAuthedAmount) / 100);
//        }

        $orderInfo = Orders::where('user_id', $user['id'])->orderBy('id', 'desc')->first();
        if ($orderInfo && ($orderInfo->status == Constant::ORDER_STATUS_PAID_OFF)) {
            self::$_cardShow['right_center_btn'] = "重新申请";
            self::$_cardShow['left_bottom_txt'] = "您的借款已结清，可再次申请借款";
            self::$_cardShow['statistics_id'] = 'B01006';
        }

        return true;
    }

    protected static function calcOpenAccountStatus($userId, $procedureId)
    {
        $openAccountInfo = OpenAccountRecords::where('user_id', $userId)
            ->where('procedure_id', $procedureId)
            ->where('status', Constant::COMMON_STATUS_INIT)
            ->where('is_submit', Procedure::SUBMIT_OK)
            ->first();
        if ($openAccountInfo) {
            return true;
        }

        return false;
    }

    protected static function calcUserAuthStatus($userId, $procedureId)
    {
        $userAuthInfo = AuthRecords::where('user_id', $userId)
            ->where('procedure_id', $procedureId)
            ->where('status', Constant::COMMON_STATUS_INIT)
            ->where('is_submit', Procedure::SUBMIT_OK)
            ->where('type', Procedure::AUTH_TYPE_LOAN)
            ->first();
        if ($userAuthInfo) {
            return true;
        }

        return false;
    }

}
