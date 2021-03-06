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
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '???????????????????????????????????????');
        }
        return $this->render([]);
    }

    /**
     * ?????????????????????api???????????????
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
                urlencode('????????????'));
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
            throw new CustomException(ErrorCode::COMMON_CUSTOM_ERROR, '???????????????');
        }
        // ?????????api????????????, todo: ??????????????????????????????????????????
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
                throw new CustomException(ErrorCode::COMMON_CUSTOM_ERROR, '????????????????????????');
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
        $title = '??????/??????';
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
            Scheme::APP_ORDER_LIST, '????????????', '', '', '', 'H01004');
        $entrances[] = Utils::genNavigationItem(
            OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, 'ic_bill.png'),
            Scheme::APP_USER_LATEST_BILL, '????????????', '', '', '', 'H01005');

        $navigations = [];
        $procedureRecord = Procedures::where('user_id', $user['id'])->first();
        $order = Orders::where('user_id', $user['id'])->first();
        if (($procedureRecord && $procedureRecord['status'] != Constant::COMMON_STATUS_INIT)
            || (!$procedureRecord && $order)) { // ???????????????????????????????????????????????????????????????????????????????????????
            $tip = '';
            $color = '';
            $auth = new AuthStatus();
            if ($auth->hasExpiredAuthItem($user['id'])) {
                $tip = '????????????,?????????';
                $color = '#F1614A';
            }
            $navigations[] = Utils::genNavigationItem(
                OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, 'ic_attestation.png'),
                Scheme::APP_AUTH_CENTER, '????????????', $tip, '', $color, 'H01006');
        }
        $overdueDays = UserHelper::overdueDays($user['id']);
        if ($overdueDays != 0) {
            $overdueInfo = Utils::getDescByOverdueDays($overdueDays);
            $overdueUrl = env('H5_BASE_URL') . sprintf(Scheme::H5_OVERDUE_NOTIFY_FORMAT,
                    rawurlencode(Utils::maskChineseName($user['name'])),
                    rawurlencode($overdueInfo['desc']),
                    rawurlencode('????????????'),
                    urlencode(Scheme::APP_USER_LATEST_BILL),
                    ($overdueInfo['level'] > 0 ? '100002' : '100001')
                );
            $navigations[] = Utils::genNavigationItem(
                OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, 'ic_overdue.png'),
                sprintf(Scheme::APP_WEBVIEW_FORMAT, rawurlencode($overdueUrl), rawurlencode('????????????')),
                '????????????', $overdueInfo['tip'], '', '#F1614A', 'H01007');
        }

        $helpCenterUrl = env('H5_BASE_URL') . Scheme::H5_HELP_CENTER;
        $navigations[] = Utils::genNavigationItem(
            OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, 'ic_help.png'),
            sprintf(Scheme::APP_WEBVIEW_NONEED_LOGIN_FORMAT, rawurlencode($helpCenterUrl), rawurlencode('????????????')), '????????????',
            '', '', '', 'H01008');
        $navigations[] = Utils::genNavigationItem(
            OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, 'ic_about.png'),
            Scheme::APP_ABOUT_US, '????????????', '', '', '', 'H01009');

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
            $tip = '?????????';
            $link = $item['link'];
            $color = '#4167FF';
            $statisticsId = $item['statistics_id'];
            if ($authStatus->getAuthItemStatus($user['id'], $dataType)) {
                $tip = '?????????';
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
            $tip = '?????????';
            $link = $item['link'];
            $color = '#4167FF';
            $statisticsId = $item['statistics_id'];
            if ($authStatus->getAuthItemStatus($user['id'], $dataType)) {
                $tip = '?????????';
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
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '?????????????????????????????????');
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
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, "????????????????????????");
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
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '???????????????');
        }
        if (Constant::AUTH_STATUS_ONGOING != $rec['status']) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '????????????????????????????????????');
        }
        $type = $validatedData['type'];
//        if ($type == Constant::BANK_CARD_AUTH_TYPE_AUTH && BankCard::where('user_id', $user['id'])->where('type', Constant::BANK_CARD_AUTH_TYPE_AUTH)
//                ->where('status', Constant::AUTH_STATUS_SUCCESS)->exists()) {
//            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '?????????????????????');
//        }

        $signBizNo = Utils::genBizNo();
        BankCard::where('id', $rec['id'])->update(['sign_biz_no' => $signBizNo, 'type' => $type,]);

        $ret = null;
        try {
            $ret = CapitalClient::sign($bizNo, $signBizNo, $validatedData['code']);
            if (!$ret) {
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '????????????????????????');
            }
        } catch (CustomException $e) {
            BankCard::where('id', $rec['id'])->where('status', Constant::AUTH_STATUS_ONGOING)
                ->update(['status' => Constant::AUTH_STATUS_FAILED, 'extra' => ($ret ? json_encode($ret['data']) : '')]);
            throw $e;
        }
        if ($ret) {
            DB::transaction(function () use ($type, $user, $rec, $ret) {
                // ????????????????????????????????????????????????
                BankCard::where('user_id', $user['id'])->where('type', $type)->where('status', Constant::AUTH_STATUS_SUCCESS)
                    ->where('card_no', $rec['card_no'])->update(['status' => Constant::AUTH_STATUS_EXPIRED]);
                // ???????????????????????????????????????
                if ($type == Constant::BANK_CARD_AUTH_TYPE_AUTH) {
                    BankCard::where('user_id', $user['id'])->where('type', Constant::BANK_CARD_AUTH_TYPE_AUTH)
                        ->where('status', Constant::AUTH_STATUS_SUCCESS)->update(['status' => Constant::AUTH_STATUS_EXPIRED]);
                }
                // ????????????????????????????????????????????????
                BankCard::where('id', $rec['id'])->where('status', Constant::AUTH_STATUS_ONGOING)
                    ->update(['status' => Constant::AUTH_STATUS_SUCCESS, 'extra' => ($ret ? json_encode($ret['data']) : '')]);
                // ??????????????????
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
            $day = date('m???d???', $ts);
            $month = date('m???', $ts);
            $overdueInfo = '';
            if ($installment['overdue_days'] > 0) {
                $overdueInfo = sprintf('??????%d???', $installment['overdue_days']);
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
                'period_str' => sprintf('???%d???', $installment['period']),
                'overdue_info' => $overdueInfo,
                'detail' => sprintf('??????%.2f+??????%.2f+??????%.2f', $capital / 100.0, $interest / 100.0, $fee / 100.0)
            ];
        }

        // ????????????
        $bill = [
            'day' => $day,
            'month' => $month,
            'amount' => sprintf('%.2f', $total / 100.0),
            'order_id' => $bills[0]['order_id'],
            'period' => $bills[0]['period'],
            'period_str' => $bills[0]['period_str'],
            'overdue_info' => $bills[0]['overdue_info'],
            'detail' => sprintf('??????%.2f+??????%.2f+??????%.2f', $capitalTotal / 100.0, $interestTotal / 100.0, $feeTotal / 100.0),
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
                    throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '???????????????');
                }
            } else {
                // ??????????????????????????????
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
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '??????&???????????????????????????');
            }
            $data = $this->haveBill($orderInfo, $installments);
        } while (false);

        //??????????????????
        $corporateAccount = [
            "company_name" => "????????????????????????????????????",
            "bank_name"    => "?????????????????????????????????",
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
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '???????????????');
        }
        $installments = OrderInstallments::where('order_id', $order['id'])
            ->orderBy('period')->get()->toArray();
        $periods = $order['periods'];
        if (count($installments) != $periods) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '??????????????????');
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
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '???????????????');
        }
        $procedureFinishDate = $order['procedure_finish_date'];
        $day = substr($procedureFinishDate, 8, 2);
        $max = OrderInstallments::where('order_id', $order['id'])->max('date');
        $min = OrderInstallments::where('order_id', $order['id'])->min('date');
        $data = [
            'order_id' => $bizNo,
            'periods_str' => sprintf('%d??????', $order['periods']),
            'periods_detail' => sprintf('%s???%s', $min, $max),
            'repay_method' => '????????????',
            'repay_date' => sprintf('???????????????%d???', $day),
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
                'name' => sprintf('%s?????????(%s)', $bankName, $postfix),
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
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '???????????????');
        }
        if (Constant::ORDER_STATUS_ONGOING != $order['status']) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '??????????????????');
        }
        $cardBizNo = $validatedData['biz_no'];
        $card = BankCard::where('sms_biz_no', $cardBizNo)->where('user_id', $user['id'])->first();
        if (!$card) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '???????????????');
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
            'card_name' => Banks::CODE_NAME_MAPPINGS[$card['bank_code']] . '???',
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
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '???????????????');
        }
        if (Constant::ORDER_STATUS_ONGOING != $order['status']) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '??????????????????');
        }
        $cardBizNo = $validatedData['biz_no'];
        $card = BankCard::where('sms_biz_no', $cardBizNo)->where('user_id', $user['id'])->first();
        if (!$card) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '???????????????');
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
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '???????????????');
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
        if (!$user) { // ?????????
            $text = '?????????????????????????????????????????????????????????????????????????????????';
        } else {
            $orders = Orders::where('user_id', $user['id'])->where('status', Constant::ORDER_STATUS_ONGOING)->get()->toArray();
            if (!$orders) { // ???????????????
                $text = '?????????????????????????????????????????????????????????????????????????????????';
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
                if ($isOverDue) { // ??????
                    if ($overdueDays <= 3) { // 1-3???
                        $text = '??????????????????????????????????????????????????????????????????????????????~~';
                    } else if ($overdueDays <= 7) { // 4-7???
                        $text = '???????????????????????????????????????????????????????????????????????????????????????4000-606-707???';
                    } else if ($overdueDays <= 15) { // 8-15???
                        $text = '?????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????4000-606-707???';
                    } else { // 16????????????
                        $text = '?????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????4000-606-707???';
                    }

                } else { // ?????????
                    $text = '??????????????????????????????????????????????????????????????????????????????~~';
                }
            }
        }
        return $this->render(['text' => $text]);
    }
}
