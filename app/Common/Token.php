<?php
/**
 * 用户token相关
 * User: hexuefei
 * Date: 2019-06-23
 * Time: 18:23
 */

namespace App\Common;

use App\Consts\Constant;
use App\Helpers\Locker;
use App\Models\Users;
use Illuminate\Support\Str;

class Token
{
    const TOKEN2USER = 'token_%d_%s';
    const USER2TOKEN = 'user_%d_%d';

    const EXPIRE = 365 * 24 * 60 * 60;

    /**
     * 生成token
     *
     * @param $user
     * @param int $source
     * @return string
     * @throws \App\Exceptions\CustomException
     */
    public static function create($user, $source = Constant::USER_SOURCE_APP)
    {
        do {
            $token = Str::random(32);
            $t2uKey = sprintf(self::TOKEN2USER, $source, $token);
            if (!RedisClient::get($t2uKey)) {
                break;
            }
        } while (true);

        $u2tKey = sprintf(self::USER2TOKEN, $source, $user['id']);
        $oldT2UKey = sprintf(self::TOKEN2USER, $source, RedisClient::get($u2tKey));
        RedisClient::delete([$t2uKey, $u2tKey, $oldT2UKey]);
        RedisClient::msetWithExpire(
            [
                $t2uKey => $user['id'],
                $u2tKey => $token,
            ],
            self::EXPIRE
        );
        return $token;
    }

    /**
     * 根据token查询user
     * @param $token
     * @param $source
     * @return mixed
     * @throws \App\Exceptions\CustomException
     */
    public static function getUserByToken($token, $source = Constant::USER_SOURCE_APP)
    {
        $user = null;
        if (empty($token)) {
            return $user;
        }
        $t2uKey = sprintf(self::TOKEN2USER, $source, $token);
        $redisClient = new RedisClient();
        $userId = $redisClient->get($t2uKey);

        if ($userId) {
            // 维护最近活跃时间
            $locker = new Locker();
            if ($locker->lock('user_active_time_' . $userId, 3 * 24 * 60 * 60)) {
                Users::where('id', $userId)->update(['active_time' => now(),]);
            }
            $user = Users::find($userId);
            $user = empty($user) ? null : json_decode(json_encode($user), true);
        }
        return $user;
    }

    /**
     * @param $token
     * @param int $source
     * @throws \App\Exceptions\CustomException
     */
    public static function clearByToken($token, $source = Constant::USER_SOURCE_APP)
    {
        $t2uKey = sprintf(self::TOKEN2USER, $source, $token);
        $userId = RedisClient::get($t2uKey);
        $u2tKey = sprintf(self::USER2TOKEN, $source, $userId);
        RedisClient::delete([$t2uKey, $u2tKey]);
    }

    /**
     * @param $userId
     * @param int $source
     * @throws \App\Exceptions\CustomException
     */
    public static function clearByUserId($userId, $source = Constant::USER_SOURCE_APP)
    {
        $u2tKey = sprintf(self::USER2TOKEN, $source, $userId);
        $token = RedisClient::get($u2tKey);
        $t2uKey = sprintf(self::TOKEN2USER, $source, $token);
        RedisClient::delete([$t2uKey, $u2tKey]);
    }


}
