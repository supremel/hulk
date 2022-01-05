<?php
/**
 * 还款中心，订单放款后状态和还款计划状态的统一出入口
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-12
 * Time: 15:06
 */

namespace App\Helpers;


use App\Common\AlertClient;
use App\Common\CapitalClient;
use App\Common\PushClient;
use App\Common\RedisClient;
use App\Common\SmsClient;
use App\Common\Utils;
use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Consts\Scheme;
use App\Consts\SmsContent;
use App\Events\InstallmentUpdateEvent;
use App\Exceptions\CustomException;
use App\Models\BankCard;
use App\Models\OrderInstallments;
use App\Models\Orders;
use App\Models\RepayInstallmentRef;
use App\Models\RepaymentRecords;
use App\Models\Users;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class  RepayCenter
{
    // 还款类型
    const REPAY_TYPE_SINGLE_PERIOD = 0; // 还单期
    const REPAY_TYPE_LEFT_ALL = 1; // 剩余所有
    const REPAY_TYPE_PERIODS_N = 2; // 还前n期
    const REPAY_TYPE_OTHER = 3; // 其他

    /**
     * 获取还款方式
     * @return array
     */
    public static function getRepayWays()
    {
        $ways = [];
        $ways[] = Utils::genNavigationItem('',
            Scheme::APP_BILL_QUICK_REPAY, '快捷还款', '从已绑定的银行卡中扣款', '推荐', '', 'G01003');
        $ways[] = Utils::genNavigationItem('',
            Scheme::APP_USER_AUTH_BANK . '&show_bar=0&card_type=1', '使用新的银行卡支付',
            '仅支持储蓄卡', '', '', 'G01004');
        $ways[] = Utils::genNavigationItem('',
            Scheme::APP_BILL_OFFLINE_REPAY, '线下转账还款', '转入指定账户', '', '', 'G01005');
        return $ways;
    }

    /**
     * 更新还款计划-逾期信息
     * @param $installmentId
     * @param $overdueData
     * @return bool
     */
    public static function updateDueInfo($installmentId, $overdueData)
    {
        $fee = $overdueData['fee'];
        $days = $overdueData['days'];
        $rows = OrderInstallments::where('id', $installmentId)->update(['fee' => $fee, 'overdue_days' => $days,]);
        $msg = "module=installment\tinstallment_id=" . $installmentId . "\t"
            . "overdue_days=" . $days . "\tfee=" . $fee;
        if ($rows) {
            Log::info($msg . "\tmsg=overdue");
        } else {
            Log::warning($msg . "\tmsg=overdue, but update installment fail");
            return false;
        }
        return true;
    }

    /**
     * 获取当期账单(含往期逾期)
     * @param $orderId
     * @return array
     */
    public static function getBills($orderId)
    {
        // 获取当月最后一天
        $lastDay = Utils::getLastDayOfMonth(date('Y-m-d'));
        $installments = OrderInstallments::where('order_id', $orderId)
            ->where('date', '<=', $lastDay)
            ->orderBy('period')
            ->get()->toArray();
        $ret = [];
        foreach ($installments as $installment) {
            if (Constant::ORDER_STATUS_PAID_OFF == $installment['status']) {
                continue;
            }
            $ret[] = $installment;
        }
        if (empty($ret)) {
            // 无过期&&当月已还
            $installments = OrderInstallments::where('order_id', $orderId)
                ->where('status', '=', Constant::ORDER_STATUS_ONGOING)
                ->orderBy('period')
                ->get()->toArray();
            if ($installments) {
                $ret[] = $installments[0];
            }
        }
        return $ret;
    }


    /**
     * 生成还款计划
     *
     * @param $amount
     * @param $periods
     * @param $interestRate
     * @param null $date 2019-01-20 放款成功日
     * @return array
     */
    public static function genInstallments($amount, $periods, $interestRate, $date = null)
    {
        $installments = [];
        if (!$date) {
            $date = date('Y-m-d');
        }
        $day = substr($date, -2, 2);
        $dayInt = intval($day);
        $leftCapital = $amount;
        for ($period = 1; $period <= $periods; $period++) {
            $cf = ComputeCenter::calcCapitalInterest($amount, $periods, $interestRate, $period, $leftCapital);
            $leftCapital -= $cf['capital'];
            $cf['period'] = $period;
            // 计算还款日
            $date = Utils::getNextMonth($date);
            $lastDay = Utils::getLastDayOfMonth($date);
            if ($dayInt > intval(substr($lastDay, -2, 2))) {
                $cf['date'] = $lastDay;
            } else {
                $cf['date'] = substr($date, 0, 8) . $day;
            }
            $installments[] = $cf;
        }
        return $installments;
    }

    /**
     * 校验逾期信息
     * @param $installment
     * @throws CustomException
     */
    public static function checkOverDueData($installment)
    {
        // 逾期信息由离线任务负责更新，再次计算是为了安全兜底
        $overdueData = ComputeCenter::getOverdueInfo($installment);
        $fee = $overdueData['fee'];
        if ($fee != $installment['fee']) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '还款计划更新中，请稍后重试');
        }
    }

    /**
     * 计算账单当前的应还信息
     * @param $bill
     * @param bool $isLegacy 是否来自老系统（老系统提前还款利息照收）
     * @return mixed
     * @throws CustomException
     */
    private static function calcDeservedByBill($bill, $isLegacy = false)
    {
        self::checkOverDueData($bill);
        $today = date('Y-m-d');
        $hasInterest = false; // 是否收取利息
        if ($today >= $bill['date']) {
            $hasInterest = true;
        } else {
            // 上一期还款计划
            $preInstallment = OrderInstallments::where('order_id', $bill['order_id'])->where('period', '<', $bill['period'])
                ->orderBy('period', 'desc')->first();
            if (!$preInstallment) {  // 当前为第一期，不免息
                $hasInterest = true;
            } else {
                $preDate = $preInstallment['date'];
                if ($today > $preDate) { // 在账期内，不免息
                    $hasInterest = true;
                } else { // 未到账期，免息
                    $hasInterest = false;
                }
            }
        }
        $amount = $bill['capital'] + $bill['fee'] + $bill['other_fee'];
        $amount -= ($bill['paid_capital'] + $bill['paid_fee'] + $bill['paid_other_fee']);
        if ($hasInterest || $isLegacy) {
            $amount += ($bill['interest'] - $bill['paid_interest']);
        } else {
            $bill['interest'] = 0;
        }
        $bill['amount'] = $amount;
        return $bill;
    }

    /**
     * 还款试算
     *
     * @param $orderInfo
     * @param int $isPayOff
     * @param int $installmentId 指定还哪一期
     * @param bool $isLegacy 是否来自老系统（老系统提前还款利息照收）
     * @return array
     * @throws CustomException
     */
    public static function repayTrial($orderInfo, $isPayOff = 0, $installmentId = 0, $isLegacy = false)
    {
        $amount = 0;
        $installments = [];
        if ($isPayOff) {
            if ($installmentId) { // 指定期次，针对系统划扣
                $bills = OrderInstallments::where('id', $installmentId)
                    ->where('status', '=', Constant::ORDER_STATUS_ONGOING)
                    ->get()->toArray();
            } else {
                $bills = OrderInstallments::where('order_id', $orderInfo['id'])
                    ->where('status', '=', Constant::ORDER_STATUS_ONGOING)
                    ->orderBy('period')
                    ->get()->toArray();
            }
            foreach ($bills as $bill) {
                $bill = self::calcDeservedByBill($bill, $isLegacy);
                $amount += $bill['amount'];
                $installments[] = $bill;
            }
        } else {
            if ($installmentId) { // 指定期次，针对历史系统同步还款状态
                $bill = OrderInstallments::where('id', $installmentId)
                    ->where('status', '=', Constant::ORDER_STATUS_ONGOING)
                    ->first();
            } else {
                $bills = self::getBills($orderInfo['id']);
                $bill = $bills[0];
            }
            $bill = self::calcDeservedByBill($bill, $isLegacy);
            $amount = $bill['amount'];
            $installments[] = $bill;
        }
        $data = [
            'installments' => $installments,
            'amount' => $amount,
        ];
        return $data;
    }

    /**
     * 插入还款记录数据
     * @param $bizNo
     * @param $userId 用户id
     * @param $cardId 银行卡id
     * @param $repayType 还款类型
     * @param $amount 还款金额
     * @param $repayData  试算的还款数据
     * @param $businessType 业务类型
     * @param $couponBizNo
     * @param $couponAmount
     * @param $isDeduction 是否分扣
     * @param $orderId
     */
    private static function _insertRepayRecord($bizNo, $userId, $cardId, $repayType, $amount, $repayData,
                                               $businessType, $couponBizNo = '', $couponAmount = 0, $orderId = 0, $isDeduction = false)
    {
        DB::transaction(function () use ($bizNo, $userId, $cardId, $repayType, $amount, $repayData, $businessType, $couponBizNo, $couponAmount, $orderId, $isDeduction) {
            $repayRecord = RepaymentRecords::create(
                [
                    'biz_no' => $bizNo,
                    'order_id' => $orderId,
                    'user_id' => $userId,
                    'type' => $repayType,
                    'bank_card_id' => $cardId,
                    'amount' => $amount,
                    'pay_amount' => $amount - $couponAmount,
                    'coupon_biz_no' => $couponBizNo,
                    'coupon_amount' => $couponAmount,
                    'business_type' => $businessType,
                    'capital' => 0,
                    'interest' => 0,
                    'fee' => 0,
                    'repay_api' => $isDeduction ? Constant::RECHARGE_API_DEDUCTION : Constant::RECHARGE_API_NORMAL,
                ]
            );
            $oldAmount = $amount;
            $capital = 0;
            $other = 0;
            $interest = 0;
            $fee = 0;
            foreach ($repayData['installments'] as $installment) {
                if ($amount == 0) {
                    break;
                }
                $c = $installment['capital'] - $installment['paid_capital'];
                $o = $installment['other_fee'] - $installment['paid_other_fee'];
                $i = $installment['interest'] - $installment['paid_interest'];
                $f = $installment['fee'] - $installment['paid_fee'];
                // 冲账顺序： 本金->其他费用->利息->逾期费
                if ($amount >= $c) {
                    $amount -= $c;
                } else {
                    $c = $amount;
                    $amount = 0;
                    $o = 0;
                    $i = 0;
                    $f = 0;
                }
                if ($amount >= $o) {
                    $amount -= $o;
                } else {
                    $o = $amount;
                    $amount = 0;
                    $i = 0;
                    $f = 0;
                }
                if ($amount >= $i) {
                    $amount -= $i;
                } else {
                    $i = $amount;
                    $amount = 0;
                    $f = 0;
                }
                if ($amount >= $f) {
                    $amount -= $f;
                } else {
                    $f = $amount;
                    $amount = 0;
                }
                $capital += $c;
                $other += $o;
                $interest += $i;
                $fee += $f;

                RepayInstallmentRef::create([
                    'order_id' => $orderId,
                    'repayment_id' => $repayRecord['id'],
                    'installment_id' => $installment['id'],
                    'capital' => $c,
                    'other_fee' => $o,
                    'interest' => $i,
                    'fee' => $f,
                ]);
            }
            // double check
            if ($oldAmount != ($capital + $other + $interest + $fee + $amount)) {
                throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '金额异常，请重试');
            }
            if (!RepaymentRecords::where('id', $repayRecord['id'])->update([
                'capital' => $capital,
                'other_fee' => $other,
                'interest' => $interest,
                'fee' => $fee,
                'overfulfil_amount' => $amount,
            ])) {
                throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '金额异常，请重试');
            }
        });
    }

    /**
     * 发起还款操作
     * @param $user 用户信息
     * @param $order 订单信息
     * @param $card 银行卡信息
     * @param $amount 还款金额
     * @param $isPayOff 是否结清
     * @param int $businessType 业务类型
     * @param int $installmentId 还款计划id
     * @param string $couponBizNo 优惠券业务号
     * @param int $couponAmount 优惠券金额
     * @param boolean $isDeduction 是否分扣
     * @return bool|string
     * @throws CustomException
     */
    public static function doRepay($user, $order, $card, $amount, $isPayOff,
                                   $businessType = Constant::RECHARGE_BUSINESS_TYPE_RECHARGE,
                                   $installmentId = 0, $couponBizNo = '', $couponAmount = 0, $isDeduction = false)
    {
        if ($repayList = RepaymentRecords::where('user_id', $user['id'])->where('order_id', $order['id'])
                ->where('status', Constant::COMMON_STATUS_INIT)->get()->toArray()) {
            AlertClient::sendAlertEmail(new \Exception("线上扣款-" . Constant::RECHARGE_BUSINESS_TYPE_DICT[$businessType] . "-失败（有处理中还款记录:{$repayList[0]['biz_no']}）"));
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '有一笔还款进行中，请稍后重试');
        }
        // 以用户纬度锁定还款状态
        // 解锁： 1. 还款操作异常时； 2. 还款结果回调后
        $bizNo = Utils::genBizNo();
        $lockerKey = 'repay_' . $user['id'];
        $locker = new Locker();
        if (!$locker->lock($lockerKey, 2 * 60 * 60, $bizNo)) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '有一笔还款进行中，请稍后重试');
        }

        $repayType = self::REPAY_TYPE_SINGLE_PERIOD;
        if ($isPayOff) {
            $repayType = self::REPAY_TYPE_LEFT_ALL;
        }
        try {
            $repayData = RepayCenter::repayTrial($order, $isPayOff, $installmentId);
            if ($amount <= 0) {
                throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '还款金额，必须大于0');
            }
            if ($repayData['amount'] != $amount) {
                throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '还款金额与应还金额不匹配');
            }
            self::_insertRepayRecord($bizNo, $user['id'], $card['id'], $repayType,
                $amount, $repayData, $businessType, $couponBizNo, $couponAmount, $order['id'], $isDeduction);
            // 发起支付充值请求
            if($isDeduction && $installmentId) {
                // 分扣充值，只支持单期还款
                $installmentInfo = OrderInstallments::find($installmentId)->toArray();
                $retData = CapitalClient::deductionRecharge($bizNo, $user, $repayData['amount'] - $couponAmount,
                    $card, $businessType, $order['biz_no'], $installmentInfo['period']);
            } else {
                $retData = CapitalClient::recharge($bizNo, $user, $repayData['amount'] - $couponAmount, $card, $businessType);
            }
            $data = [
                'request_time' => date('Y-m-d H:i:s'),
            ];
            RepaymentRecords::where('biz_no', $bizNo)->update($data);

            if ($retData && 'FAIL' == $retData['tranState']) {
                self::repayFail($bizNo, $retData);
            } else if ($retData && 'SUCCESS' == $retData['tranState']) {
                self::repaySuccess($bizNo, $retData);
            } else if ($retData && 'PARTSUCCESS' == $retData['tranState']) {
                self::repayPartSuccess($bizNo, $retData);
            }

            return $bizNo;
        } catch (\Exception $e) {
            $repayRecord = RepaymentRecords::where('biz_no', $bizNo)->first();
            if ($repayRecord) {
                RepaymentRecords::where('id', $repayRecord['id'])
                    ->update(['status' => Constant::COMMON_STATUS_FAILED]);
                RepayInstallmentRef::where('repayment_id', $repayRecord['id'])
                    ->update(['status' => Constant::COMMON_STATUS_FAILED]);
            }

            $locker->restoreLock($lockerKey, $bizNo);
            throw $e;
        }
    }

    /**
     * 还款成功
     * @param $bizNo
     * @param $retData
     * @param bool $isLegacy 是否来自老系统（不免息）
     * @return bool
     */
    public static function repaySuccess($bizNo, $retData, $isLegacy = false)
    {
        $ret = true;
        $repayRecord = null;
        try {
            $repayRecord = RepaymentRecords::where('biz_no', $bizNo)->first();
            if (!$repayRecord) {
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '流水号不存在');
            }
            DB::transaction(function () use ($repayRecord, $retData, $isLegacy) {
                if (0 == RepaymentRecords::where('id', $repayRecord['id'])->where('status', Constant::COMMON_STATUS_INIT)->update(
                        [
                            'finish_time' => date('Y-m-d H:i:s'),
                            'status' => Constant::COMMON_STATUS_SUCCESS,
                            'extra' => json_encode($retData),
                        ]
                    )) {
                    throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '更新还款记录失败');
                };
                if (0 == RepayInstallmentRef::where('repayment_id', $repayRecord['id'])->where('status', Constant::COMMON_STATUS_INIT)->update(
                        [
                            'status' => Constant::COMMON_STATUS_SUCCESS,
                        ]
                    )) {
                    throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '更新还款计划映射失败');
                };
                // 是否还清所有账单
                $isPayOff = 0;
                if ($repayRecord['type'] == self::REPAY_TYPE_LEFT_ALL) {
                    $isPayOff = 1;
                }
                // 更新还款计划
                $refs = RepayInstallmentRef::where('repayment_id', $repayRecord['id'])->get()->toArray();
                foreach ($refs as $ref) {
                    $installment = OrderInstallments::where('id', $ref['installment_id'])->lockForUpdate()->first();
                    $orderId = $installment['order_id'];
                    // 计算最新的还款计划
                    $orderInfo = Orders::where('id', $orderId)->first();
                    $repayData = self::repayTrial($orderInfo, $isPayOff, $ref['installment_id'], $isLegacy);
                    $newInstallment = [];
                    foreach ($repayData['installments'] as $install) {
                        if ($install['id'] == $installment['id']) {
                            $newInstallment = $install;
                            break;
                        }
                    }
                    $paidCapital = $installment['paid_capital'] + $ref['capital'];
                    $paidInterest = $installment['paid_interest'] + $ref['interest'];
                    $paidFee = $installment['paid_fee'] + $ref['fee'];
                    $paidOtherFee = $installment['paid_other_fee'] + $ref['other_fee'];
                    $data = [
                        'paid_capital' => $paidCapital,
                        'paid_interest' => $paidInterest,
                        'paid_fee' => $paidFee,
                        'paid_other_fee' => $paidOtherFee,
                        'capital' => $newInstallment['capital'],
                        'fee' => $newInstallment['fee'],
                    ];
                    $isPaidOff = false;
                    if ($newInstallment['capital'] == $paidCapital && $newInstallment['interest'] == $paidInterest
                        && $newInstallment['fee'] == $paidFee && $newInstallment['other_fee'] == $paidOtherFee) {
                        // 还清，则重置利息(针对提前还款免息)
                        $data['interest'] = $newInstallment['interest'];
                        $data['status'] = Constant::ORDER_STATUS_PAID_OFF;
                        $data['pay_off_time'] = date('Y-m-d H:i:s');
                        $isPaidOff = true;
                    }
                    if (!OrderInstallments::where('id', $installment['id'])->where('status', Constant::ORDER_STATUS_ONGOING)
                        ->update($data)) {
                        throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR,
                            '还款计划更新失败=>' . $installment['id'] . '=>' . json_encode($data));
                    }
                    // 更新订单
                    if ($isPaidOff && !OrderInstallments::where('order_id', $installment['order_id'])
                            ->where('status', Constant::ORDER_STATUS_ONGOING)->exists()) {
                        $data = [
                            'status' => Constant::ORDER_STATUS_PAID_OFF,
                            'pay_off_date' => date('Y-m-d H:i:s'),
                        ];
                        if (!Orders::where('id', $installment['order_id'])->where('status', Constant::ORDER_STATUS_ONGOING)
                            ->update($data)) {
                            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR,
                                '订单状态更新失败=>' . $installment['order_id'] . '=>' . json_encode($data));
                        }
                    }
                }

            });
            // 生成还款计划更新事件
            event(new InstallmentUpdateEvent($repayRecord['order_id']));
            // 释放用户还款锁
            $lockerKey = 'repay_' . $repayRecord['user_id'];
            $locker = new Locker();
            $locker->restoreLock($lockerKey, $bizNo);
        } catch (\Exception $e) { // CT补偿
            $ret = false;
            // todo: 添加短信报警
            $msg = "module=repay\tbiz_no=$bizNo\tmsg=repay success, but update data error\terror=" . $e->getMessage();
            AlertClient::sendAlertEmail(new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, $msg));
            Log::warning($msg);
        }

        self::_doNotifyToUserOfRepaySuccess($repayRecord);

        return $ret;
    }

    /**
     * 通知用户（还款成功）
     * @param $repayRecord
     */
    public static function _doNotifyToUserOfRepaySuccess($repayRecord)
    {
        if ($repayRecord) {
            try {
                $user = Users::where('id', $repayRecord['user_id'])->first();
                $isPaidOff = false;
                $order = Orders::where('id', $repayRecord['order_id'])->first();
                if (Constant::ORDER_STATUS_PAID_OFF == $order['status']) {
                    $isPaidOff = true;
                }
                $ref = RepayInstallmentRef::where('repayment_id', $repayRecord['id'])->first();
                $installment = OrderInstallments::where('id', $ref['installment_id'])->first();
                if ($isPaidOff) {
                    $content = sprintf(SmsContent::REPAY_SUCCESS_PAID_OFF_FORMAT,
                        date('m'), date('d'), $repayRecord['amount'] / 100.0);
                    SmsClient::sendSms($user['phone'], $content);
                } else {
                    $content = sprintf(SmsContent::REPAY_SUCCESS_SINGLE_PERIOD_FORMAT,
                        date('m'), date('d'), $repayRecord['amount'] / 100.0,
                        $installment['period'],
                        $order['periods']);
                    SmsClient::sendSms($user['phone'], $content);
                }
                if ($repayRecord['business_type'] == Constant::RECHARGE_BUSINESS_TYPE_SYYTEM_TIMING) {
                    $bankCard = BankCard::where('id', $repayRecord['bank_card_id'])->first();
                    if ($installment['fee'] != 0) {
                        $content = sprintf('亲，已从您尾号%s的银行号自动还款%.2f元，您在水莲金条第%d期的借款已还清!',
                            substr($bankCard['card_no'], -4), $repayRecord['amount'] / 100.0,
                            $installment['period']);
                    } else {
                        $content = sprintf('亲，已从您尾号%s的银行卡自动还款%.2f元，您本月在水莲金条的借款已还清，继续保持哦！',
                            substr($bankCard['card_no'], -4), $repayRecord['amount'] / 100.0);
                    }
                    PushClient::pushByUserId($user['id'], '还款成功', $content);
                }
            } catch (\Exception $exception) {
                Log::warning("module=repay\tbiz_no=" . $repayRecord['biz_no'] . "\tmsg=repay success, but notify error\terror" . $exception->getMessage());
            }
        }
    }

    /**
     * 还款失败
     * @param $bizNo
     * @param $retData
     * @return bool
     */
    public static function repayFail($bizNo, $retData)
    {
        $ret = true;
        $repayRecord = null;
        try {
            $repayRecord = RepaymentRecords::where('biz_no', $bizNo)->first();
            if (!$repayRecord) {
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '流水号不存在');
            }
            DB::transaction(function () use ($repayRecord, $retData) {
                if (0 == RepaymentRecords::where('id', $repayRecord['id'])->where('status', Constant::COMMON_STATUS_INIT)->update(
                        [
                            'finish_time' => date('Y-m-d H:i:s'),
                            'status' => Constant::COMMON_STATUS_FAILED,
                            'extra' => json_encode($retData),
                        ]
                    )) {
                    throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '更新还款记录失败');
                };
                if (0 == RepayInstallmentRef::where('repayment_id', $repayRecord['id'])->where('status', Constant::COMMON_STATUS_INIT)->update(
                        [
                            'status' => Constant::COMMON_STATUS_FAILED,
                        ]
                    )) {
                    throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '更新还款计划映射失败');
                };
            });
            // 此时，释放用户还款锁
            $lockerKey = 'repay_' . $repayRecord['user_id'];
            $locker = new Locker();
            $locker->restoreLock($lockerKey, $bizNo);
            // 置银行卡锁定状态
            $now = time();
            $endOfDay = strtotime(date('Y-m-d', strtotime('+1 day')));
            $lockedSeconds = $endOfDay - $now;
            $bankCard = BankCard::find($repayRecord['bank_card_id'])->toArray();
            RedisClient::setWithExpire('bank_card_locker_' . $bankCard['card_no'], false, $lockedSeconds);
        } catch (\Exception $e) {// CT补偿
            // todo: 添加短信报警
            $ret = false;
            $msg = "module=repay\tbiz_no=$bizNo\tmsg=repay fail, but update data error\terror=" . $e->getMessage();
            AlertClient::sendAlertEmail(new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, $msg));
            Log::warning($msg);
        }
        self::_doNotifyToUserOfRepayFail($repayRecord);
        return $ret;
    }

    /**
     * 通知用户（还款失败）
     * @param $repayRecord
     */
    public static function _doNotifyToUserOfRepayFail($repayRecord)
    {
        if ($repayRecord) {
            try {
                $user = Users::where('id', $repayRecord['user_id'])->first();
                if (self::REPAY_TYPE_SINGLE_PERIOD == $repayRecord['type']) {
                    $content = sprintf(SmsContent::REPAY_FAIL_SINGLE_PERIOD_FORMAT,
                        $repayRecord['amount'] / 100.0);
                    SmsClient::sendSms($user['phone'], $content);
                } else if (self::REPAY_TYPE_LEFT_ALL == $repayRecord['type']) {
                    $content = sprintf(SmsContent::REPAY_FAIL_PAID_OFF_FORMAT,
                        $repayRecord['amount'] / 100.0);
                    SmsClient::sendSms($user['phone'], $content);
                }
                // 系统划扣，且是15点触发
                if ($repayRecord['business_type'] == Constant::RECHARGE_BUSINESS_TYPE_SYYTEM_TIMING
                    && date('H') == '15') {
                    $content = sprintf('亲，水莲金条自动还款失败，%.2f元未还，请保持银行卡资金充足哦', $repayRecord['amount'] / 100.0);
                    PushClient::pushByUserId($user['id'], '还款失败', $content);
                }

            } catch (\Exception $exception) {
                Log::warning("module=repay\tbiz_no=" . $repayRecord['biz_no'] . "\tmsg=repay fail, but notify error\terror" . $exception->getMessage());
            }
        }
    }

    /**
     * 线下还款-冲账
     * @param $user 用户信息
     * @param $order 订单信息
     * @param $amount 还款金额
     * @param string $couponBizNo
     * @param int $couponAmount 其中优惠券金额
     * @throws CustomException
     */
    public static function offlineRepay($user, $order, $amount, $couponBizNo = '', $couponAmount = 0)
    {
        if (Constant::ORDER_STATUS_ONGOING != $order['status']) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '订单状态错误');
        }
        $bizNo = Utils::genBizNo();
        $lockerKey = 'repay_' . $user['id'];
        $locker = new Locker();
        if (!$locker->lock($lockerKey, 2 * 60 * 60, $bizNo)) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '有一笔还款进行中，请稍后重试');
        }
        try {
            $repayData = RepayCenter::repayTrial($order, 1);
            if ($repayData['amount'] == 0) {
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '无待还');
            }
            self::_insertRepayRecord($bizNo, $user['id'], 0, self::REPAY_TYPE_OTHER, $amount, $repayData,
                Constant::RECHARGE_BUSINESS_TYPE_OFFLINE, $couponBizNo, $couponAmount, $order['id']);
        } catch (\Exception $e) {
            $locker->restoreLock($lockerKey, $bizNo);
            throw $e;
        }
        if (!self::repaySuccess($bizNo, '')) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '系统异常');
        }
    }

    /**
     * 还款状态同步（from老系统）
     * @param $userId
     * @param $orderInfo
     * @param $amount
     * @param $periods
     * @param $deductOrderId
     * @param $isPartSuccess
     * @throws CustomException
     */
    public static function repaySyncFromLegacy($userId, $orderInfo, $amount, $periods, $deductOrderId, $isPartSuccess)
    {
        $bizNo = Utils::genBizNo();
        $lockerKey = 'repay_' . $userId;
        $locker = new Locker();
        if (!$locker->lock($lockerKey, 2 * 60 * 60, $bizNo)) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '有一笔还款进行中，请稍后重试');
        }
        try {
            $isPayOff = (($periods == -1) ? 1 : 0);
            $installmentId = 0;
            if (0 == $isPayOff) {
                $installment = OrderInstallments::where('order_id', $orderInfo['id'])->where('period', $periods)->first();
                $installmentId = $installment['id'];
            }
            $repayData = RepayCenter::repayTrial($orderInfo, $isPayOff, $installmentId, true);
            $couponAmount = 0;
            $successAmount = $amount;

            if ($repayData['amount'] != $amount) {
                if ($repayData['amount'] == 0) { // 重复消息，已还清
                    Log::warning("module=repaySyncFromLegacy\tmsg=已还清\torder_id="
                        . $orderInfo['biz_no'] . "\tperiods=" . $periods);
                    $locker->restoreLock($lockerKey, $bizNo);
                    return;
                }
                do {
                    if (($isPartSuccess == 1) && ($repayData['amount'] > $amount)) { // 部分成功，分扣逻辑，只有代扣和线上还款存在
                        $amount = $repayData['amount'];
                        Log::warning("module=repaySyncFromLegacy\tmsg=部分还款成功\torder_id="
                            . $orderInfo['biz_no'] . "\tperiods=" . $periods);
                        break;
                    }

                    $fee = 0;
                    foreach ($repayData['installments'] as $bill) {
                        $fee += $bill['fee'];
                    }
                    if ($fee + $amount == $repayData['amount']) {  // 默认可以是免逾期费的，通过优惠券抹平
                        $couponAmount = $fee;
                        $amount += $fee;
                        Log::warning("module=repaySyncFromLegacy\tmsg=免逾期费\torder_id="
                            . $orderInfo['biz_no'] . "\tperiods=" . $periods);
                        break;
                    }
                    if (count($repayData['installments']) == 1) { // 单期
                        // 同步过来的数据中逾期费少N天，通过优惠券抹平
                        $installment = $repayData['installments'][0];
                        $diff = $repayData['amount'] - $amount;
                        $fee = round($installment['capital'] * 1
                            * (ComputeCenter::OVERDUE_FEE_RATE_PER_DAY / 10000.0));
                        if ($fee != 0 && $diff % $fee == 0) {
                            $couponAmount = $diff;
                            $amount += $diff;
                            Log::warning("module=repaySyncFromLegacy\tmsg=逾期天数不匹配\torder_id="
                                . $orderInfo['biz_no'] . "\tperiods=" . $periods);
                            break;
                        }
                        if ($amount == $installment['capital']){
                            $couponAmount = $diff;
                            $amount += $diff;
                            Log::warning("module=repaySyncFromLegacy\tmsg=利息特殊减免\torder_id="
                                . $orderInfo['biz_no'] . "\tperiods=" . $periods);
                            break;
                        }
                        $value = RedisClient::get(sprintf('repaySyncFromLegacy_%s_%d', $orderInfo['biz_no'], $periods));
                        if ($value) {
                            $couponAmount = $diff;
                            $amount += $diff;
                            Log::warning("module=repaySyncFromLegacy\tmsg=特殊减免\torder_id="
                                . $orderInfo['biz_no'] . "\tperiods=" . $periods);
                            break;
                        }
                    }
                    throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR,
                        '还款金额不匹配(计算值' . $repayData['amount'] . 'vs同步值' . $amount . ')');
                } while (false);
            }
            $type = ($isPayOff == 1) ? self::REPAY_TYPE_LEFT_ALL : self::REPAY_TYPE_SINGLE_PERIOD;
            self::_insertRepayRecord($bizNo, $userId, 0, $type, $amount, $repayData,
                Constant::RECHARGE_BUSINESS_TYPE_SYNC_FROM_LEGACY,
                'repaySyncFromLegacy', $couponAmount, $orderInfo['id'], $isPartSuccess);
        } catch (\Exception $e) {
            $locker->restoreLock($lockerKey, $bizNo);
            throw $e;
        }
        if ($isPartSuccess == 1) {
            $ret = self::repayPartSuccess($bizNo, ['deduct_order_id' => $deductOrderId, 'successAmount' => $successAmount], true);
        } else {
            $ret = self::repaySuccess($bizNo, ['deduct_order_id' => $deductOrderId,], true);
        }
        if (!$ret) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '系统异常');
        }
    }

    /**
     * 部分还款成功
     * @param $bizNo
     * @param string $retData
     * @param bool $isLegacy 是否来自老系统（不免息）
     * @return bool
     */
    public static function repayPartSuccess($bizNo, $retData, $isLegacy = false)
    {
        $ret = true;
        $repayRecord = null;
        try {
            if (!$isLegacy) {
                $retData['successAmount'] = intval($retData['successAmount'] * 100);
            }

            $repayRecord = RepaymentRecords::where('biz_no', $bizNo)->first();
            if (!$repayRecord) {
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '流水号不存在');
            }

            // 二次校验是否全部还款成功
            if ($retData['successAmount'] == intval($repayRecord['pay_amount'])) {
                return self::repaySuccess($bizNo, $retData);
            }

            DB::transaction(function () use ($repayRecord, $retData, $isLegacy) {
                // 是否还清所有账单
                $isPayOff = 0;

                $order = Orders::where('id', $repayRecord['order_id'])->first();
                $refs = RepayInstallmentRef::where('repayment_id', $repayRecord['id'])->get()->toArray();
                $installmentId = (count($refs) == 1) ? $refs[0]['installment_id'] : 0;
                $repayData = RepayCenter::repayTrial($order, $isPayOff, $installmentId, $isLegacy);
                // 更新还款记录数据，部分还款成功
                self::_updateRepayRecordByPartSuccess($repayRecord, $retData, $repayData);

                $refs = RepayInstallmentRef::where('repayment_id', $repayRecord['id'])->get()->toArray();

                // 更新还款计划
                foreach ($refs as $ref) {
                    $installment = OrderInstallments::where('id', $ref['installment_id'])->lockForUpdate()->first();
                    $orderId = $installment['order_id'];
                    // 计算最新的还款计划
                    $orderInfo = Orders::where('id', $orderId)->first();
                    $oneRepayData = self::repayTrial($orderInfo, $isPayOff, $ref['installment_id'], $isLegacy);
                    $newInstallment = [];
                    foreach ($oneRepayData['installments'] as $install) {
                        if ($install['id'] == $installment['id']) {
                            $newInstallment = $install;
                            break;
                        }
                    }
                    $paidCapital = $installment['paid_capital'] + $ref['capital'];
                    $paidInterest = $installment['paid_interest'] + $ref['interest'];
                    $paidFee = $installment['paid_fee'] + $ref['fee'];
                    $paidOtherFee = $installment['paid_other_fee'] + $ref['other_fee'];
                    $data = [
                        'paid_capital' => $paidCapital,
                        'paid_interest' => $paidInterest,
                        'paid_fee' => $paidFee,
                        'paid_other_fee' => $paidOtherFee,
                        'capital' => $newInstallment['capital'],
                        'fee' => $newInstallment['fee'],
                    ];
                    $isPaidOff = false;
                    if ($newInstallment['capital'] == $paidCapital && $newInstallment['interest'] == $paidInterest
                        && $newInstallment['fee'] == $paidFee && $newInstallment['other_fee'] == $paidOtherFee) {
                        // 还清，则重置利息(针对提前还款免息)
                        $data['interest'] = $newInstallment['interest'];
                        $data['status'] = Constant::ORDER_STATUS_PAID_OFF;
                        $data['pay_off_time'] = date('Y-m-d H:i:s');
                        $isPaidOff = true;
                    }
                    if (!OrderInstallments::where('id', $installment['id'])->where('status', Constant::ORDER_STATUS_ONGOING)
                        ->update($data)) {
                        throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR,
                            '还款计划更新失败=>' . $installment['id'] . '=>' . json_encode($data));
                    }
                    // 更新订单
                    if ($isPaidOff && !OrderInstallments::where('order_id', $installment['order_id'])
                            ->where('status', Constant::ORDER_STATUS_ONGOING)->exists()) {
                        $data = [
                            'status' => Constant::ORDER_STATUS_PAID_OFF,
                            'pay_off_date' => date('Y-m-d H:i:s'),
                        ];
                        if (!Orders::where('id', $installment['order_id'])->where('status', Constant::ORDER_STATUS_ONGOING)
                            ->update($data)) {
                            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR,
                                '订单状态更新失败=>' . $installment['order_id'] . '=>' . json_encode($data));
                        }
                    }
                }

            });
            // 生成还款计划更新事件
            event(new InstallmentUpdateEvent($repayRecord['order_id']));
            // 释放用户还款锁
            $lockerKey = 'repay_' . $repayRecord['user_id'];
            $locker = new Locker();
            $locker->restoreLock($lockerKey, $bizNo);
        } catch (\Exception $e) { // CT补偿
            $ret = false;
            // todo: 添加短信报警
            $msg = "module=repay\tbiz_no=$bizNo\tmsg=repay success, but update data error\terror=" . $e->getMessage();
            AlertClient::sendAlertEmail(new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, $msg));
            Log::warning($msg);
        }

        //self::_doNotifyToUserOfRepaySuccess($repayRecord);

        return $ret;
    }

    /**
     * 更新还款记录数据，部分还款成功
     * @param $repayRecord 支付记录
     * @param string $retData 还款结果
     * @param $repayData
     */
    private static function _updateRepayRecordByPartSuccess($repayRecord, $retData, $repayData)
    {
        DB::transaction(function () use ($repayRecord, $retData, $repayData) {
            $oldAmount = $amount = $retData['successAmount'] + $repayRecord['amount'] - $repayRecord['pay_amount'];

            $capital = 0;
            $other = 0;
            $interest = 0;
            $fee = 0;

            foreach ($repayData['installments'] as $installment) {
                $refStatus = Constant::COMMON_STATUS_SUCCESS;

                $c = $installment['capital'] - $installment['paid_capital'];
                $o = $installment['other_fee'] - $installment['paid_other_fee'];
                $i = $installment['interest'] - $installment['paid_interest'];
                $f = $installment['fee'] - $installment['paid_fee'];
                // 冲账顺序： 本金->其他费用->利息->逾期费
                if ($amount == 0) {
                    $refStatus = Constant::COMMON_STATUS_FAILED;
                }
                if ($amount >= $c) {
                    $amount -= $c;
                } else {
                    $c = $amount;
                    $amount = 0;
                    $o = 0;
                    $i = 0;
                    $f = 0;
                    $refStatus = Constant::COMMON_STATUS_PART_SUCCESS;
                }
                if ($amount >= $o) {
                    $amount -= $o;
                } else {
                    $o = $amount;
                    $amount = 0;
                    $i = 0;
                    $f = 0;
                    $refStatus = Constant::COMMON_STATUS_PART_SUCCESS;
                }
                if ($amount >= $i) {
                    $amount -= $i;
                } else {
                    $i = $amount;
                    $amount = 0;
                    $f = 0;
                    $refStatus = Constant::COMMON_STATUS_PART_SUCCESS;
                }
                if ($amount >= $f) {
                    $amount -= $f;
                } else {
                    $f = $amount;
                    $amount = 0;
                    $refStatus = Constant::COMMON_STATUS_PART_SUCCESS;
                }
                $capital += $c;
                $other += $o;
                $interest += $i;
                $fee += $f;

                if(!RepayInstallmentRef::where('repayment_id', $repayRecord['id'])
                    ->where('installment_id', $installment['id'])
                    ->where('status', Constant::COMMON_STATUS_INIT)->update(
                    [
                        'capital' => $c,
                        'other_fee' => $o,
                        'interest' => $i,
                        'fee' => $f,
                        'status' => $refStatus,
                    ]
                )) {
                    throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '更新还款计划映射失败');
                }
            }
            // double check
            if ($oldAmount != ($capital + $other + $interest + $fee + $amount)) {
                throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '金额异常，请重试');
            }
            if (!RepaymentRecords::where('id', $repayRecord['id'])->update([
                'pay_amount' => $retData['successAmount'],
                'capital' => $capital,
                'other_fee' => $other,
                'interest' => $interest,
                'fee' => $fee,
                'overfulfil_amount' => $amount,
                'finish_time' => date('Y-m-d H:i:s'),
                'status' => Constant::COMMON_STATUS_PART_SUCCESS,
                'extra' => json_encode($retData),
            ])) {
                throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '更新还款记录失败');
            }
        });
    }
}