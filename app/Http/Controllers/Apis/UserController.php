<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-06-13
 * Time: 14:24
 */

namespace App\Http\Controllers\Apis;

use App\Common\CapitalClient;
use App\Common\DefenseClient;
use App\Common\Formatter;
use App\Common\LegacyClient;
use App\Common\MnsClient;
use App\Common\OssClient;
use App\Common\RedisClient;
use App\Common\SmsClient;
use App\Common\Token;
use App\Common\Utils;
use App\Consts\Banks;
use App\Consts\Constant;
use App\Consts\Contract;
use App\Consts\ErrorCode;
use App\Consts\Profile;
use App\Consts\Scheme;
use App\Exceptions\CustomException;
use App\Helpers\AuthStatus\AuthStatus;
use App\Helpers\RepayCenter;
use App\Helpers\UserHelper;
use App\Http\Controllers\Controller;
use App\Models\BankCard;
use App\Models\OrderInstallments;
use App\Models\Orders;
use App\Models\Procedures;
use App\Models\RepaymentRecords;
use App\Models\Users;
use App\Services\HulkEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function verifyCode(Request $request)
    {
        $validatedData = $request->validate(
            [
                'phone' => 'required|regex:/^1[3-9][0-9]{9}$/',
            ]
        );
        $phone = $validatedData['phone'];
        $ticket = $request->input('ticket', '');
        $randStr = $request->input('rand', '');
        if ($ticket) {
            $legalFlag = DefenseClient::checkTicket($ticket, $randStr, $request->ip(), Constant::TENCENT_SCENE_ID_VERIFY_CODE);
            if (!$legalFlag) {
                throw  new CustomException(ErrorCode::COMMON_ILLEGAL_REQUEST);
            }
        } else {
            $riskFlag = DefenseClient::hasRisk($phone, $request->ip());
            if ($riskFlag) {
                throw new CustomException(ErrorCode::COMMON_CAPTCHA_TENCENT, Constant::TENCENT_SCENE_ID_VERIFY_CODE);
            }
        }
        if (!SmsClient::sendVerifyCode($phone)) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '发送验证码失败，请稍后重试');
        }
        return $this->render([]);
    }

    /**
     * 检测是否有来自api的在贷订单
     * @param $phone
     * @throws CustomException
     */
    private function _detectInLoanFromApi($phone)
    {
        $sign = LegacyClient::hasOrderInLoan($phone);
        if (!empty($sign)) {
            $msg = sprintf(Scheme::APP_WEBVIEW_NONEED_LOGIN_FORMAT,
                urlencode(env('H5_BASE_URL') . '/sl/h5/account/?mobile=' . strtoupper(md5($phone))
                    . '&sign=' . urlencode($sign)),
                urlencode('我的账单'));
            throw new CustomException(ErrorCode::USER_FROM_API, $msg);
        }
    }

    public function login(Request $request)
    {
        $validatedData = $request->validate(
            [
                'phone' => 'required|regex:/^1[3-9][0-9]{9}$/',
                'code' => 'required|regex:/^[0-9]{4,12}$/',
            ]
        );
        $phone = $validatedData['phone'];
        $code = $validatedData['code'];

        $ticket = $request->input('ticket', '');
        $randStr = $request->input('rand', '');
        if ($ticket) {
            $legalFlag = DefenseClient::checkTicket($ticket, $randStr, $request->ip(), Constant::TENCENT_SCENE_ID_LOGIN);
            if (!$legalFlag) {
                throw  new CustomException(ErrorCode::COMMON_ILLEGAL_REQUEST);
            }
        } else {
            $riskFlag = DefenseClient::hasRisk($phone, $request->ip(), 'LoginProtection');
            if ($riskFlag) {
                throw new CustomException(ErrorCode::COMMON_CAPTCHA_TENCENT, Constant::TENCENT_SCENE_ID_LOGIN);
            }
        }

        if (!SmsClient::checkVerifyCode($phone, $code)) {
            throw new CustomException(ErrorCode::COMMON_CUSTOM_ERROR, '验证码错误');
        }
        // 是否是api在贷用户, todo: 系统完成切换后下掉此判断逻辑
        $this->_detectInLoanFromApi($phone);
        $user = Users::where('phone', $phone)->first();
        if (!$user) {
            try {
                $uid = Utils::genBizNo(32);
                $user = Users::create(
                    [
                        'phone' => $phone,
                        'uid' => $uid,
                        'reg_channel' => Utils::genChannelInfo($request, $request->header('Channel', 'default')),
                        'old_user_id' => date('YmdHis') . strval(rand(100, 999)),
                        'active_time' => date('Y-m-d H:i:s'),
                    ]
                );
            } catch (\Exception $e) {
                Log::warning("module=login\terror=" . $e->getMessage());
                throw new CustomException(ErrorCode::COMMON_CUSTOM_ERROR, '登录失败，请重试');
            }
        }
        // device info
        Utils::saveDeviceInfo($user['id'], $request);

        $user = json_decode(json_encode($user), true);
        $token = Token::create($user);
        $data = [
            'token' => $token,
            'uid' => $user['uid'],
        ];
        return $this->render($data);
    }

    public function index(Request $request)
    {
        $user = Utils::resolveUser($request);

        $link = Scheme::APP_USER_LOGIN;
        $title = '登录/注册';
        $statisticsId = 'H01002';
        if ($user) {
            $name = $user['name'];
            $phone = $user['phone'];
            $identity = $user['identity'];
            $title = Utils::maskPhone($phone);
            $link = Scheme::APP_SETTINGS . '?phone=' . Utils::maskPhone($phone)
                . '&name=' . Utils::maskChineseName($name) . '&identity=' . Utils::maskIdentity($identity);
            $statisticsId = 'H01003';
        }
        $header = Utils::genNavigationItem(
            OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, 'img_head.png'),
            $link, $title, '', '', '', $statisticsId);

        $entrances = [];
        $entrances[] = Utils::genNavigationItem(
            OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, 'ic_borrow.png'),
            Scheme::APP_ORDER_LIST, '借款记录', '', '', '', 'H01004');
        $entrances[] = Utils::genNavigationItem(
            OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, 'ic_bill.png'),
            Scheme::APP_USER_LATEST_BILL, '我的账单', '', '', '', 'H01005');

        $navigations = [];
        $procedureRecord = Procedures::where('user_id', $user['id'])->first();
        $order = Orders::where('user_id', $user['id'])->first();
        if (($procedureRecord && $procedureRecord['status'] != Constant::COMMON_STATUS_INIT)
            || (!$procedureRecord && $order)) { // 针对迁移数据，做特殊处理（即有订单无流程，则展示认证中心）
            $tip = '';
            $color = '';
            $auth = new AuthStatus();
            if ($auth->hasExpiredAuthItem($user['id'])) {
                $tip = '有失效项,请更新';
                $color = '#F1614A';
            }
            $navigations[] = Utils::genNavigationItem(
                OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, 'ic_attestation.png'),
                Scheme::APP_AUTH_CENTER, '认证中心', $tip, '', $color, 'H01006');
        }
        $overdueDays = UserHelper::overdueDays($user['id']);
        if ($overdueDays != 0) {
            $overdueInfo = Utils::getDescByOverdueDays($overdueDays);
            $overdueUrl = env('H5_BASE_URL') . sprintf(Scheme::H5_OVERDUE_NOTIFY_FORMAT,
                    rawurlencode(Utils::maskChineseName($user['name'])),
                    rawurlencode($overdueInfo['desc']),
                    rawurlencode('立即还款'),
                    urlencode(Scheme::APP_USER_LATEST_BILL),
                    ($overdueInfo['level'] > 0 ? '100002' : '100001')
                );
            $navigations[] = Utils::genNavigationItem(
                OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, 'ic_overdue.png'),
                sprintf(Scheme::APP_WEBVIEW_FORMAT, rawurlencode($overdueUrl), rawurlencode('逾期告知')),
                '逾期告知', $overdueInfo['tip'], '', '#F1614A', 'H01007');
        }

        $helpCenterUrl = env('H5_BASE_URL') . Scheme::H5_HELP_CENTER;
        $navigations[] = Utils::genNavigationItem(
            OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, 'ic_help.png'),
            sprintf(Scheme::APP_WEBVIEW_NONEED_LOGIN_FORMAT, rawurlencode($helpCenterUrl), rawurlencode('帮助中心')), '帮助中心',
            '', '', '', 'H01008');
        $navigations[] = Utils::genNavigationItem(
            OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, 'ic_about.png'),
            Scheme::APP_ABOUT_US, '关于我们', '', '', '', 'H01009');

        $data = [
            'header' => $header,
            'entrances' => $entrances,
            'navigations' => $navigations,
        ];
        return $this->render($data);
    }

    public function logout(Request $request)
    {
        $user = $request->user;
        Token::clearByUserId($user['id']);
        return $this->render([]);
    }

    public function profile(Request $request)
    {
        $user = $request->user;
        $base = [];
        $baseItems = Profile::AUTH_LIST[Profile::AUTH_TYPE_REQUIRED];
        $authStatus = new AuthStatus();
        foreach ($baseItems as $dataType => $item) {
            $tip = '去认证';
            $link = $item['link'];
            $color = '#4167FF';
            $statisticsId = $item['statistics_id'];
            if ($authStatus->getAuthItemStatus($user['id'], $dataType)) {
                $tip = '已认证';
                $link = '';
                $color = '#B9C7FF';
                $statisticsId = '';
            }
            $base[] = Utils::genNavigationItem(OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, $item['icon']),
                $link, $item['title'], $tip, '', $color, $statisticsId);
        }


        $optional = [];
        $baseItems = Profile::AUTH_LIST[Profile::AUTH_TYPE_OPTIONAL];
        foreach ($baseItems as $dataType => $item) {
            $tip = '去认证';
            $link = $item['link'];
            $color = '#4167FF';
            $statisticsId = $item['statistics_id'];
            if ($authStatus->getAuthItemStatus($user['id'], $dataType)) {
                $tip = '已认证';
                $link = '';
                $color = '#B9C7FF';
                $statisticsId = '';
            }
            $optional[] = Utils::genNavigationItem(OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, $item['icon']),
                $link, $item['title'], $tip, '', $color, $statisticsId);
        }


        $data = [
            'base' => $base,
            'optional' => $optional,
            'contracts' => Utils::getContractDataByAuth($user),
        ];
        return $this->render($data);
    }

    public function bankVerifyCode(Request $request)
    {
        $user = $request->user;
        $validatedData = $request->validate(
            [
                'reserved_phone' => 'required|regex:/^1[3-9][0-9]{9}$/',
                'card_no' => 'required',
                'bank_code' => 'required',
            ]
        );
        $validatedData['user_id'] = $user['id'];
        $bizNo = Utils::genBizNo();
        $validatedData['sms_biz_no'] = $bizNo;
        $validatedData['status'] = Constant::AUTH_STATUS_ONGOING;
        try {
            if (!CapitalClient::sendSms(array_merge($user, $validatedData))) {
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '发送验证码失败，请重试');
            }
        } catch (CustomException $e) {
            if ($e->getCode() == ErrorCode::USER_BANK_CARD_BINDED) {
                $cardRecord = BankCard::where('user_id', $user['id'])->where('card_no', $validatedData['card_no'])
                    ->where('status', Constant::AUTH_STATUS_SUCCESS)->first();
                if ($cardRecord) {
                    $validatedData['status'] = Constant::AUTH_STATUS_EXPIRED;
                } else {
                    $validatedData['status'] = Constant::AUTH_STATUS_SUCCESS;
                }
                BankCard::create($validatedData);
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, "该银行卡已认证过");
            }
            throw $e;
        }


        BankCard::create($validatedData);

        return $this->render(['biz_no' => $bizNo]);
    }

    public function bankBindCard(Request $request)
    {
        $user = $request->user;
        $validatedData = $request->validate(
            [
                'biz_no' => 'required|size:32',
                'type' => 'required|in:0,1',
                'code' => 'required|regex:/^[0-9]{4,6}$/',
            ]
        );

        $bizNo = $validatedData['biz_no'];
        $rec = BankCard::where('sms_biz_no', $bizNo)->first();
        if (!$rec) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '流水号错误');
        }
        if (Constant::AUTH_STATUS_ONGOING != $rec['status']) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '验证码已失效，请重新获取');
        }
        $type = $validatedData['type'];
//        if ($type == Constant::BANK_CARD_AUTH_TYPE_AUTH && BankCard::where('user_id', $user['id'])->where('type', Constant::BANK_CARD_AUTH_TYPE_AUTH)
//                ->where('status', Constant::AUTH_STATUS_SUCCESS)->exists()) {
//            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '只允许一次认证');
//        }

        $signBizNo = Utils::genBizNo();
        BankCard::where('id', $rec['id'])->update(['sign_biz_no' => $signBizNo, 'type' => $type,]);

        $ret = null;
        try {
            $ret = CapitalClient::sign($bizNo, $signBizNo, $validatedData['code']);
            if (!$ret) {
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '请重新获取验证码');
            }
        } catch (CustomException $e) {
            BankCard::where('id', $rec['id'])->where('status', Constant::AUTH_STATUS_ONGOING)
                ->update(['status' => Constant::AUTH_STATUS_FAILED, 'extra' => ($ret ? json_encode($ret['data']) : '')]);
            throw $e;
        }
        if ($ret) {
            DB::transaction(function () use ($type, $user, $rec, $ret) {
                // 将已成功的同卡号同类型，置为失效
                BankCard::where('user_id', $user['id'])->where('type', $type)->where('status', Constant::AUTH_STATUS_SUCCESS)
                    ->where('card_no', $rec['card_no'])->update(['status' => Constant::AUTH_STATUS_EXPIRED]);
                // 将已成功的认证卡，置为失效
                if ($type == Constant::BANK_CARD_AUTH_TYPE_AUTH) {
                    BankCard::where('user_id', $user['id'])->where('type', Constant::BANK_CARD_AUTH_TYPE_AUTH)
                        ->where('status', Constant::AUTH_STATUS_SUCCESS)->update(['status' => Constant::AUTH_STATUS_EXPIRED]);
                }
                // 将进行中的同卡号同类型，置为有效
                BankCard::where('id', $rec['id'])->where('status', Constant::AUTH_STATUS_ONGOING)
                    ->update(['status' => Constant::AUTH_STATUS_SUCCESS, 'extra' => ($ret ? json_encode($ret['data']) : '')]);
                // 同步用户数据
                if ($type == Constant::BANK_CARD_AUTH_TYPE_AUTH) {
                    Users::where('id', $user['id'])->update(
                        [
                            'bank_code' => $rec['bank_code'],
                            'card_no' => $rec['card_no'],
                        ]
                    );
                }
            });
        }

        try {
            $data['contractType']    = Contract::TYPE_BANK;
            $data['contractStep']    = Contract::STEP_GEN_DISPENSE;
            $data['relationType']    = Contract::RELATION_TYPE_USER;
            $data['relationId']      = $user['id'];
            $data['bindSuccessTime'] = date('Y-m-d H:i:s');
            $msg                     = [
                'event'  => HulkEventService::EVENT_TYPE_CONTRACT,
                'params' => $data,
            ];
            MnsClient::sendMsg2Queue(
                env('HULK_EVENT_ACCESS_ID'),
                env('HULK_EVENT_ACCESS_KEY'),
                env('HULK_EVENT_QUEUE_NAME'),
                json_encode($msg)
            );
        } catch (\Exception $e) {
            Log::warning($e->getMessage());
        }

        return $this->render([]);
    }

    private function noBill()
    {
        return [
            'has_bill' => false,
        ];
    }

    private function haveBill($orderInfo, $installments)
    {
        $orderId = $orderInfo['biz_no'];
        $bills = [];
        $total = 0;
        $capitalTotal = 0;
        $interestTotal = 0;
        $feeTotal = 0;
        $day = '';
        $month = '';
        foreach ($installments as $installment) {
            RepayCenter::checkOverDueData($installment);
            $d = $installment['date'];
            $ts = strtotime($d);
            $day = date('m月d日', $ts);
            $month = date('m月', $ts);
            $overdueInfo = '';
            if ($installment['overdue_days'] > 0) {
                $overdueInfo = sprintf('逾期%d天', $installment['overdue_days']);
            }
            $capital = $installment['capital'];
            $interest = $installment['interest'];
            $fee = $installment['fee'];
            $total += $capital - $installment['paid_capital'];
            $capitalTotal += $capital - $installment['paid_capital'];
            $total += $interest - $installment['paid_interest'];
            $interestTotal += $interest - $installment['paid_interest'];
            $total += $fee - $installment['paid_fee'];
            $feeTotal += $fee - $installment['paid_fee'];
            $bills[] = [
                'order_id' => $orderId,
                'period' => $installment['period'],
                'period_str' => sprintf('第%d期', $installment['period']),
                'overdue_info' => $overdueInfo,
                'detail' => sprintf('本金%.2f+利息%.2f+罚息%.2f', $capital / 100.0, $interest / 100.0, $fee / 100.0)
            ];
        }

        // 账单累计
        $bill = [
            'day' => $day,
            'month' => $month,
            'amount' => sprintf('%.2f', $total / 100.0),
            'order_id' => $bills[0]['order_id'],
            'period' => $bills[0]['period'],
            'period_str' => $bills[0]['period_str'],
            'overdue_info' => $bills[0]['overdue_info'],
            'detail' => sprintf('本金%.2f+利息%.2f+罚息%.2f', $capitalTotal / 100.0, $interestTotal / 100.0, $feeTotal / 100.0),
        ];
        $data = [
            'has_bill' => true,
            'bill' => $bill,
            'repay_ways' => RepayCenter::getRepayWays(),
        ];

        return $data;
    }

    public function latestBills(Request $request)
    {
        $user = $request->user;
        $orderId = $request->input('order_id');

        do {
            if ($orderId) {
                $orderInfo = Orders::where('biz_no', $orderId)->where('user_id', $user['id'])->first();
                if (!$orderInfo) {
                    throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '订单号错误');
                }
            } else {
                // 查找一个还款中的订单
                $orderInfo = Orders::where('user_id', $user['id'])->where('status', Constant::ORDER_STATUS_ONGOING)->first();
                if (!$orderInfo) {
                    $data = $this->noBill();
                    break;
                }
            }

            if ($orderInfo['status'] != Constant::ORDER_STATUS_ONGOING) {
                $data = $this->noBill();
                break;
            }
            $installments = RepayCenter::getBills($orderInfo['id']);
            if (!$installments) {
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '订单&还款计划状态不匹配');
            }
            $data = $this->haveBill($orderInfo, $installments);
        } while (false);

        //对公账户信息
        $corporateAccount = [
            "company_name" => "智莲（北京）科技有限公司",
            "bank_name"    => "招行银行北京陶然亭支行",
            "bank_card"    => "110932389510302",
        ];

        $data['corporate_account'] = $corporateAccount;


        return $this->render($data);
    }

    public function orderList(Request $request)
    {
        $user = $request->user;
        $orders = Orders::where('user_id', $user['id'])->whereIn('status', [Constant::ORDER_STATUS_ONGOING, Constant::ORDER_STATUS_PAID_OFF])
            ->orderBy('id', 'desc')->get()->toArray();
        if (!$orders) {
            $data = ['has_order' => false,];
        } else {
            $os = [];
            foreach ($orders as $order) {
                $os[] = Formatter::formatOrderInfo($order);
            }
            $data = [
                'has_order' => true,
                'orders' => $os,
            ];
        }

        return $this->render($data);
    }

    public function orderInfo(Request $request)
    {
        $user = $request->user;
        $validatedData = $request->validate(
            [
                'order_id' => 'required',
            ]
        );

        $bizNo = $validatedData['order_id'];
        $order = Orders::where('biz_no', $bizNo)->where('user_id', $user['id'])->first();
        if (!$order) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '订单号错误');
        }
        $installments = OrderInstallments::where('order_id', $order['id'])
            ->orderBy('period')->get()->toArray();
        $periods = $order['periods'];
        if (count($installments) != $periods) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '还款计划异常');
        }
        $data = [
            'order_info' => Formatter::formatOrderInfo($order),
            'installments' => Formatter::formatInstallments($order, $installments),
        ];
        return $this->render($data);
    }

    public function orderDetail(Request $request)
    {
        $user = $request->user;
        $validatedData = $request->validate(
            [
                'order_id' => 'required',
            ]
        );
        $bizNo = $validatedData['order_id'];
        $order = Orders::where('biz_no', $bizNo)->where('user_id', $user['id'])->first();
        if (!$order) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '订单号错误');
        }
        $procedureFinishDate = $order['procedure_finish_date'];
        $day = substr($procedureFinishDate, 8, 2);
        $max = OrderInstallments::where('order_id', $order['id'])->max('date');
        $min = OrderInstallments::where('order_id', $order['id'])->min('date');
        $data = [
            'order_id' => $bizNo,
            'periods_str' => sprintf('%d个月', $order['periods']),
            'periods_detail' => sprintf('%s至%s', $min, $max),
            'repay_method' => '按月还款',
            'repay_date' => sprintf('还款日每月%d号', $day),
            'amount' => sprintf('%.2f', $order['amount'] / 100.0),
            'receive_account' => sprintf("%s(%s)", Banks::CODE_NAME_MAPPINGS[$user['bank_code']], substr($user['card_no'], -4, 4)),
            'contracts' => Utils::getContractData($order),
        ];
        return $this->render($data);
    }

    public function cardList(Request $request)
    {
        $user = $request->user;
        $bankCards = BankCard::where('user_id', $user['id'])->where('status', Constant::AUTH_STATUS_SUCCESS)->get()->toArray();
        $cards = [];

        foreach ($bankCards as $bankCard) {
            $postfix = substr($bankCard['card_no'], -4, 4);
            $bankName = Banks::CODE_NAME_MAPPINGS[$bankCard['bank_code']];

            $cards[$bankCard['card_no']] = [
                'icon' => OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, Banks::getIconByCode($bankCard['bank_code'])),
                'name' => sprintf('%s储蓄卡(%s)', $bankName, $postfix),
                'biz_no' => $bankCard['sms_biz_no'],
                'is_available' => (empty(RedisClient::get('bank_card_locker_' . $bankCard['card_no'])) ? true : false),
                'desc' => '',
            ];
        }
        $data = [
            'cards' => array_values($cards),
        ];

        return $this->render($data);
    }

    public function repayTrial(Request $request)
    {
        $user = $request->user;
        $validatedData = $request->validate(
            [
                'order_id' => 'required',
                'is_pay_off' => 'required|in:0,1',
                'biz_no' => 'required',
            ]
        );
        $bizNo = $validatedData['order_id'];
        $order = Orders::where('biz_no', $bizNo)->where('user_id', $user['id'])->first();
        if (!$order) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '订单号错误');
        }
        if (Constant::ORDER_STATUS_ONGOING != $order['status']) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '订单状态错误');
        }
        $cardBizNo = $validatedData['biz_no'];
        $card = BankCard::where('sms_biz_no', $cardBizNo)->where('user_id', $user['id'])->first();
        if (!$card) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '流水号错误');
        }
        $isPayOff = $validatedData['is_pay_off'];
        $repayData = RepayCenter::repayTrial($order, $isPayOff);
        $data = [
            'biz_no' => $cardBizNo,
            'order_id' => $bizNo,
            'is_pay_off' => $isPayOff,
            'amount' => sprintf('%.2f', $repayData['amount'] / 100.0),
            'orig_amount' => $repayData['amount'],
            'card_postfix' => substr($card['card_no'], -4, 4),
            'card_name' => Banks::CODE_NAME_MAPPINGS[$card['bank_code']] . '卡',
        ];
        return $this->render($data);
    }

    public function repay(Request $request)
    {
        $user = $request->user;
        $validatedData = $request->validate(
            [
                'order_id' => 'required',
                'is_pay_off' => 'required|in:0,1',
                'biz_no' => 'required',
                'amount' => 'required|numeric',
            ]
        );
        $bizNo = $validatedData['order_id'];
        $order = Orders::where('biz_no', $bizNo)->where('user_id', $user['id'])->first();
        if (!$order) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '订单号错误');
        }
        if (Constant::ORDER_STATUS_ONGOING != $order['status']) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '订单状态错误');
        }
        $cardBizNo = $validatedData['biz_no'];
        $card = BankCard::where('sms_biz_no', $cardBizNo)->where('user_id', $user['id'])->first();
        if (!$card) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '流水号错误');
        }
        $isPayOff = $validatedData['is_pay_off'];
        $amount = intval($validatedData['amount']);

        $bizNo = RepayCenter::doRepay($user, $order, $card, $amount, $isPayOff);
        return $this->render(['biz_no' => $bizNo,]);
    }

    public function repayDetect(Request $request)
    {
        $user = $request->user;
        $validatedData = $request->validate(
            [
                'biz_no' => 'required',
            ]
        );
        $bizNo = $validatedData['biz_no'];
        $repayRecord = RepaymentRecords::where('biz_no', $bizNo)->where('user_id', $user['id'])->first();
        if (!$repayRecord) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '流水号错误');
        }
        $msg = '';
        if (Constant::COMMON_STATUS_FAILED == $repayRecord['status']) {
            $payRet = json_decode($repayRecord['extra'], true);
            $msg = $payRet['resMsg'] ?? '';
        }
        return $this->render(['status' => $repayRecord['status'], 'msg' => $msg,]);
    }

    public function overdueNotify(Request $request)
    {
        $user = Utils::resolveUser($request);
        if (!$user) { // 未登录
            $text = '您当前无待还款项，马上去申请借款，快速审核，快速到账。';
        } else {
            $orders = Orders::where('user_id', $user['id'])->where('status', Constant::ORDER_STATUS_ONGOING)->get()->toArray();
            if (!$orders) { // 无在还订单
                $text = '您当前无待还款项，马上去申请借款，快速审核，快速到账。';
            } else {
                $isOverDue = false;
                $overdueDays = 0;
                foreach ($orders as $order) {
                    $installments = OrderInstallments::where('order_id', $order['id'])
                        ->where('status', Constant::ORDER_STATUS_ONGOING)
                        ->where('overdue_days', '>', 0)->get()->toArray();
                    if ($installments) {
                        $isOverDue = true;
                        foreach ($installments as $installment) {
                            if ($installment['overdue_days'] > $overdueDays) {
                                $overdueDays = $installment['overdue_days'];
                            }
                        }
                    }
                }
                if ($isOverDue) { // 逾期
                    if ($overdueDays <= 3) { // 1-3天
                        $text = '您的借款正在使用中，培养良好的信用习惯，请按时还款哟~~';
                    } else if ($overdueDays <= 7) { // 4-7天
                        $text = '您的借款已严重逾期。请尽快处理你的欠款，如有疑问请联系客服4000-606-707。';
                    } else if ($overdueDays <= 15) { // 8-15天
                        $text = '您的借款已严重逾期，我司正在准备上报征信的材料。请尽快处理你的欠款，如有疑问请联系客服4000-606-707。';
                    } else { // 16天及以上
                        $text = '您的借款已严重逾期，我司正在准备上报征信的材料。请尽快处理你的欠款，如有疑问请联系客服4000-606-707。';
                    }

                } else { // 未逾期
                    $text = '您的借款正在使用中，培养良好的信用习惯，请按时还款哟~~';
                }
            }
        }
        return $this->render(['text' => $text]);
    }
}
