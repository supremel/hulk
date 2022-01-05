<?php

namespace App\Models;

use App\Consts\Constant;
use Illuminate\Database\Eloquent\Model;

class IdCard extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_id_card';

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
        'front_id',
        'back_id',
        'name',
        'identity',
        'age',
        'gender',
        'ethnicity',
        'birthday',
        'addr',
        'start_time',
        'end_time',
        'issued_by',
        'extra',
        'status',
    ];

    /**
     * @desc   通过用户用户ID获取用户实名认证信息
     * @action getSuccessInfoByUserId
     * @param $userId
     * @return array
     * @author liuhao
     * @date   2019-08-23
     */
    public function getSuccessInfoByUserId($userId)
    {
        $info = static::select('addr', 'name', 'gender', 'age', 'identity')
            ->where('user_id', '=', $userId)
            ->where('status', '=', Constant::AUTH_STATUS_SUCCESS)
            ->first();
        if (empty($info)) {
            return [];
        }

        return $info->toArray();
    }
}
