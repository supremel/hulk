<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddrInfo extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'addrs';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'code';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped. By default, Eloquent expects created_at and updated_at columns.
     *
     * @var bool
     */
    public $timestamps = false;

    const CREATED_AT = null;
    const UPDATED_AT = null;

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
        'code',
        'name',
        'province',
        'city',
    ];


    /**
     * @desc   通过地区ID获取该地区的详细信息
     * @action getAddrInfoByCountyId
     * @param array $codeList
     * @return array
     * @author liuhao
     * @date   2019-08-26
     */
    public function getAddrInfoByCode(array $codeList)
    {
        $info = static::select('*')
            ->whereIn('code',$codeList)
            ->get();

        if (empty($info)) {
            return [];
        }

        return $info->toArray();
    }
}
