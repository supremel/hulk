<?php

namespace App\Models;

use App\Consts\Constant;
use Illuminate\Database\Eloquent\Model;

class OrderInstallments extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'order_installments';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Indicates if the model should be timestamped. By default, Eloquent expects created_at and updated_at columns.
     *
     * @var bool
     */
    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'order_id',
        'period',
        'capital',
        'paid_capital',
        'interest',
        'paid_interest',
        'fee',
        'paid_fee',
        'other_fee',
        'paid_other_fee',
        'overdue_days',
        'date',
        'pay_off_time',
        'status',
    ];


    /**
     * @desc   通过时间格式0000-00-00 获取当日逾期且未还款的分期计划
     * @action getInstallmentOverdueWaitByDate
     * @param string $date
     * @return array
     * @author liuhao
     * @date   2019-08-23
     */
    public function getInstallmentOverdueWaitByDate($date)
    {
        $list = static::select(
            'user_id',
            'order_id',
            'period',
            'capital',
            'date',
            'interest',
            'fee'
        )
            ->where('status', '=', Constant::ORDER_INTEREST_STATUS_WAIT)
            ->where('date', '=', $date)
            ->where('overdue_days', '>', 0)
            ->get();

        if (empty($list)) {
            return [];
        }

        return $list->toArray();
    }

    /**
     * @desc   通过时间格式orderId 获取当日逾期且未还款的分期计划
     * @action getWaitInstallmentOverdueByOrderId
     * @param $orderId
     * @return array
     * @author liuhao
     * @date   2019-08-23
     */
    public function getInstallmentOverdueWaitByOrderId($orderId)
    {
        $info = static::select('*')
            ->where('status', '=', Constant::ORDER_INTEREST_STATUS_WAIT)
            ->where('order_id', '=', $orderId)
            ->where('overdue_days', '>', 0)
            ->first();

        if (empty($info)) {
            return [];
        }

        return $info->toArray();
    }

    /**
     * @desc   根据OrderID获取所有的分期情况
     * @action getInstallmentListByOrderIdAction
     * @param $orderId
     * @return array
     * @author liuhao
     * @date   2019-08-23
     */
    public function getInstallmentListByOrderId($orderId)
    {
        $list = static::select('*')
            ->where('order_id', '=', $orderId)
            ->get();

        if (empty($list)) {
            return [];
        }

        return $list->toArray();
    }


    /**
     * @desc   根据orderID列表获取列表中所有订单记录的欠款
     * @action getSumDebtByOrderIdList
     * @param $orderIdList
     * @return string
     * @author liuhao
     * @date   2019-08-23
     */
    public function getSumDebtByOrderIdList($orderIdList)
    {
        $totalMoney = '0';
        //根据获得的orderId查询分期信息
        $totalSql = '(SUM(capital)+SUM(interest)+SUM(fee))';  //所有欠款总和,包括利息和逾期费
        $repaidSql = '(SUM(paid_capital)+SUM(paid_interest)+SUM(paid_fee))';   //所有已还欠款总和,包括利息和逾期费
        $list = static::selectRaw("{$totalSql} - {$repaidSql} as debt,order_id")
            ->where('status', '=', Constant::ORDER_INTEREST_STATUS_WAIT)
            ->whereIn('order_id', $orderIdList)
            ->where('overdue_days', '>', '0')
            ->groupBy('order_id')
            ->get();
        if (empty($list)) {
            return $totalMoney;
        }

        //求和
        $list = $list->toArray();
        foreach ($list as $value) {
            $totalMoney = bcadd($totalMoney, $value['debt']);
        }
        return $totalMoney;
    }
}
