<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Orders extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'orders';

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
        'status' => 0,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'biz_no',
        'user_id',
        'procedure_id',
        'capital_label',
        'amount',
        'identity',
        'periods',
        'periods_type',
        'interest_rate',
        'repay_type',
        'fee_type',
        'loan_usage',
        'capital_loan_usage',
        'source',
        'raised_date',
        'loaned_date',
        'pay_off_date',
        'status',
    ];

    /**
     * @desc   通过ID列表获取指定的数据
     * @action getInfosByIdListAction
     * @param $idList
     * @return array
     * @author liuhao
     * @date   2019-08-23
     */
    public function getInfosByIdList($idList)
    {
        $list = static::select('id', 'periods', 'biz_no', 'source')
            ->whereIn('id', $idList)
            ->get();

        if (empty($list)) {
            return [];
        }
        return $list->toArray();
    }

    /**
     * @desc   通过流水号获取指定的订单
     * @action getInfoByBizNoAction
     * @param $bizNumber
     * @return array
     * @author liuhao
     * @date   2019-08-23
     */
    public function getInfoByBizNo($bizNumber)
    {
        //读取订单信息
        $info = static::select('id', 'user_id', 'biz_no')
            ->where('biz_no', '=', $bizNumber)
            ->first();
        //订单不存在,直接返回
        if (empty($info)) {
            return [];
        }
        return $info->toArray();
    }

    /**
     * @desc   根据流水号和状态获取订单详情
     * @action getInfoByBizNoAndStatusAction
     * @param $bizNumber
     * @param $status
     * @return array
     * @author liuhao
     * @date   2019-08-23
     */
    public function getInfoByBizNoAndStatus($bizNumber, $status)
    {
        //读取订单信息
        $info = static::select('id', 'user_id', 'biz_no', 'periods')
            ->where('biz_no', '=', $bizNumber)
            ->where('status', '=', $status)
            ->first();
        //订单不存在,直接返回
        if (empty($info)) {
            return [];
        }
        return $info->toArray();
    }

    /**
     * @desc   ...
     * @action getListByBizNoListAndStatusAction
     * @param $bizNumberList
     * @param $status
     * @return array
     * @author liuhao
     * @date   2019-08-23
     */
    public function getListByBizNoListAndStatus($bizNumberList, $status)
    {
        $list = static::select('id', 'user_id')
            ->whereIn('biz_no', $bizNumberList)
            ->where('status', '=', $status) //放款成功(还款中)
            ->get();
        if (empty($list)) {
            return [];
        }
        return $list->toArray();
    }
}
