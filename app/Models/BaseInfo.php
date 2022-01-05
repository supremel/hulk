<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaseInfo extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_base_info';

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
        'education',
        'industry',
        'company_name',
        'month_income',
        'addr',
        'email',
        'province',
        'city',
        'county',
        'status',
    ];

    /**
     * @desc   通过用户ID获取用户居住地等信息
     * @action getInfoByUserId
     * @param $userId
     * @return array
     * @author liuhao
     * @date   2019-08-23
     */
    public function getInfoByUserId($userId)
    {
        $info = static::select('addr', 'company_name', 'county', 'province', 'city')
            ->where('user_id', '=', $userId)
            ->orderBy('id', 'desc')
            ->first();
        if (empty($info)) {
            return [];
        }
        return $info->toArray();
    }
}
