<?php
/**
 * 格式化器
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-08
 * Time: 14:31
 */

namespace App\Common;


use App\Consts\Constant;
use App\Consts\Scheme;
use App\Models\OrderInstallments;

class Formatter
{
    /**
     * 格式化订单信息
     * @param $order
     * @return array
     */
    public static function formatOrderInfo($order)
    {
        $rec = [
            'order_id' => $order['biz_no'],
            'amount' => sprintf('%.2f', $order['amount'] / 100.0),
            'periods' => $order['periods'],
            'periods_str' => sprintf('%d个月', $order['periods']),
            'status' => $order['status'],
            'status_icon' => OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON,
                Constant::ORDER_STATUS_ICONS[$order['status']]),
            'color' => Constant::ORDER_STATUS_COLORS[$order['status']],
            'status_str' => Constant::ORDER_STATUS_NAME_DICT[$order['status']],
            'loaned_date' => substr($order['procedure_finish_date'], 0, 10),
            'btn_link' => '',
        ];
        if (Constant::ORDER_STATUS_ONGOING == $order['status']) { // 还款中订单
            $rec['btn_link'] = Scheme::APP_USER_LATEST_BILL;
            if (OrderInstallments::where('order_id', $order['id'])->whereRaw('fee!=paid_fee')->exists()) {
                $rec['status'] = Constant::ORDER_STATUS_OVERDUE;
                $rec['status_str'] = Constant::ORDER_STATUS_NAME_DICT[$rec['status']];
                $rec['status_icon'] = OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON,
                    Constant::ORDER_STATUS_ICONS[$rec['status']]);
                $rec['color'] = Constant::ORDER_STATUS_COLORS[$rec['status']];
            }
        }
        return $rec;
    }

    /**
     * 格式化还款计划信息
     * @param $order
     * @param $installments
     * @return array
     */
    public static function formatInstallments($order, $installments)
    {
        $recs = [];
        $periods = $order['periods'];
        foreach ($installments as $installment) {
            $capital = $installment['capital'];
            $interest = $installment['interest'];
            $fee = $installment['fee'];
            $status = $installment['status'];
            if ($installment['overdue_days'] > 0) {
                $status = Constant::ORDER_STATUS_OVERDUE;
            }
            $recs[] = [
                'repay_date' => $installment['date'],
                'amount' => sprintf('%.2f', ($capital + $interest + $fee) / 100.0),
                'period' => $installment['period'],
                'period_str' => sprintf('%d/%d期', $installment['period'], $periods),
                'status' => $status,
                'status_str' => Constant::ORDER_STATUS_NAME_DICT[$status],
                'color' => Constant::ORDER_STATUS_COLORS[$status],
            ];
        }
        return $recs;
    }
}