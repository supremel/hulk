<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Relationship extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_relationship';

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
        'name',
        'phone',
        'type',
        'relation',
        'status',
    ];

    /**
     * @desc   通过用户的ID获取用户所有社会关系,(仅限成功认证的)
     * @action getSuccessListByUserIdAction
     * @param $userId
     * @return array
     * @author liuhao
     * @date   2019-08-23
     */
    public function getSuccessListByUserId($userId)
    {
        $list = static::select('phone', 'name', 'relation', 'type')
            ->where('user_id', '=', $userId)
            ->where('status', '=', \App\Consts\Constant::AUTH_STATUS_SUCCESS)
            ->get();
        if (empty($list)) {
            return [];
        }
        return $list->toArray();
    }
}
