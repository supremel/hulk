<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapitalRouteRecords extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'capital_route_records';

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
        'user_id',
        'procedure_id',
        'label',
        'need_open_account',
    ];
}
