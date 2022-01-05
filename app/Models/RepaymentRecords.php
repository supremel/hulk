<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepaymentRecords extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'repayment_records';

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
        'biz_no',
        'order_id',
        'user_id',
        'type',
        'business_type',
        'bank_card_id',
        'amount',
        'pay_amount',
        'capital',
        'interest',
        'fee',
        'other_fee',
        'coupon_biz_no',
        'coupon_amount',
        'request_time',
        'finish_time',
        'status',
        'extra',
        'overfulfil_amount',
        'repay_api',
    ];
}
