<?php
/**
 * 安全的分布式锁
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-06-14
 * Time: 16:51
 */

namespace App\Helpers;


use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class Locker implements \Illuminate\Contracts\Cache\LockProvider
{
    public function lock($name, $seconds = 0, $owner = null)
    {
        Log::debug("module=locker\taction=lock\tname=" . $name . "\tseconds=" . $seconds . "\towner=" . $owner);
        return Redis::set($name, $owner, 'EX', $seconds, 'NX');
    }

    public function restoreLock($name, $owner)
    {
        $lua = <<<EOF
           if redis.call('get',KEYS[1]) == ARGV[1] then  
                return redis.call('del',KEYS[1]) 
           else 
                return 0 
           end
EOF;
        Log::debug("module=locker\taction=restoreLock\tname=" . $name . "\towner=" . $owner);
        return Redis::eval($lua, 1, $name, $owner);
    }

}