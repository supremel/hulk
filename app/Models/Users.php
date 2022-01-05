<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Users extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

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
        'phone',
        'uid',
        'reg_channel',
        'name',
        'bank_code',
        'card_no',
        'old_user_id',
        'active_time',
    ];

    /**
     * @desc   根据userIdList获取指定用户的信息
     * @action getUserInfoByIdListAction
     * @param  array $userIdList
     * @return array
     * @author liuhao
     * @date   2019-08-23
     */
    public function getInfosByIdList(array $userIdList)
    {
        $list = static::select('id', 'name', 'phone', 'reg_channel')
            ->whereIn('id', $userIdList)
            ->get();
        if (empty($list)) {
            return [];
        }

        return $list->toArray();
    }

    /**
     * @desc   通过ID获取用户详情
     * @action getInfoByIdAction
     * @param $id
     * @return array
     * @author liuhao
     * @date   2019-08-23
     */
    public function getInfoById($id)
    {
        $info = static::select('phone', 'old_user_id', 'uid', 'name')
            ->where('id', '=', $id)
            ->first();

        if (empty($info)) {
            return [];
        }

        return $info->toArray();
    }
}
