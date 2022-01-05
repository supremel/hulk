<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-08
 * Time: 17:59
 */

namespace App\Models\MongoDB;


use Jenssegers\Mongodb\Eloquent\Model;

class DeviceHistory extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'DEVICE_USER';
    protected $primaryKey = '_id';    //设置主键

    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'device_id',
        'imei',
        'client_ip',
        'version',
        'extra',
        'time',
    ];  //设置字段白名单
}