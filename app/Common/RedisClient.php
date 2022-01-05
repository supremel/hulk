<?php
/**
 * redis操作相关
 * User: hexuefei
 * Date: 2019-03-23
 * Time: 18:24
 */

namespace App\Common;

use App\Consts\ErrorCode;
use App\Exceptions\CustomException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisClient
{
    private static function exec($method, $params)
    {
        try {
            Log::debug("module=redis\tmethod=" . $method . "\tparams=" . json_encode($params));
            return Redis::$method(...$params);
        } catch (\Exception $e) {
            Log::warning("module=redis\tmethod=" . $method . "\tmsg=" . $e->getMessage());
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
        }
    }

    /**
     * @param $kvs
     * @param $expire
     * @throws CustomException
     */
    public static function msetWithExpire($kvs, $expire)
    {
        $pipe = self::exec('multi', [1]);
        $pipe->mset($kvs);
        foreach ($kvs as $k => $v) {
            $pipe->expire($k, $expire);
        }
        $pipe->exec();
    }

    /**
     * @param $k
     * @return mixed
     * @throws CustomException
     */
    public static function get($k)
    {
        return self::exec('get', [$k]);
    }

    /**
     * @param $k
     * @return mixed
     * @throws CustomException
     */
    public static function delete($k)
    {
        return self::exec('del', [$k]);
    }

    /**
     * @param $k
     * @param $v
     * @param $expire
     * @return mixed
     * @throws CustomException
     */
    public static function setWithExpire($k, $v, $expire)
    {
        return self::exec('set', [$k, $v, 'EX', $expire]);
    }

    /**
     * @param $k
     * @return mixed
     * @throws CustomException
     */
    public static function ttl($k)
    {
        return self::exec('ttl', [$k]);
    }

}