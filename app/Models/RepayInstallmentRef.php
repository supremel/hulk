<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepayInstallmentRef extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'repay_installment_ref';

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
        'repayment_id',
        'order_id',
        'installment_id',
        'capital',
        'interest',
        'fee',
        'other_fee',
        'status',
    ];
}
