<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-11
 * Time: 10:36
 */

namespace App\Helpers;


use App\Consts\Fee;

class ComputeCenter
{
    const OVERDUE_FEE_RATE_PER_DAY = 0; // 逾期罚息利率，万分位， 100 for 1%


    /**
     * 计算指定期次的本金&利息
     * @param $amount
     * @param $periods
     * @param $interestRate
     * @param $period
     * @param $leftCapital 剩余本金
     * @return array
     */
    public static function calcCapitalInterest($amount, $periods, $interestRate, $period, $leftCapital)
    {
        $fees = Fee::CAPITAL_FEE_DICT[$periods];
        $fakeInterestRate = $interestRate * 12 * ($periods / 12); // 名义利率

        $rate = $fees[$period]['rate'];
        $divisor = $fees[$period]['divisor'];

        $capital = round($amount * $rate / $divisor, 0);
        if ($period == $periods) { // 最后一期，本金抹平
            $capital = $leftCapital;
        }
        $interest = round($amount * $fakeInterestRate * $rate / $divisor / 10000.0, 0);
        return [
            'capital' => $capital,
            'interest' => $interest,
        ];
    }

    /**
     * 获取逾期信息
     * @param $installment 还款计划
     * @return array ['days'=>0, 'fee'=>0]
     */
    public static function getOverdueInfo($installment)
    {
        $data = [
            'days' => 0,
            'fee' => 0,
        ];
        $dayTs = strtotime($installment['date']);
        $nowTs = strtotime(date('Y-m-d'));
        if ($nowTs <= $dayTs) {
            return $data;
        }
        $diff = $nowTs - $dayTs;
        $days = $diff / (24 * 3600);
        $days += ($diff % (24 * 3600) == 0 ? 0 : 1);
        $capital = $installment['capital'];
        if ($installment['other_fee_type'] == Fee::OTHER_FEE_TYPE_MEMBER_FEE) {
            $capital += $installment['other_fee_capital'];
        }
        $fee = round($capital * $days * (self::OVERDUE_FEE_RATE_PER_DAY / 10000.0));
        $data = [
            'days' => $days,
            'fee' => $fee,
        ];
        return $data;
    }

}