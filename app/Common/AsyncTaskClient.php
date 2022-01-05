<?php
/**
 * 异步任务相关
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-22
 * Time: 16:08
 */

namespace App\Common;


use App\Jobs\Touch\PushTouch;
use App\Jobs\Touch\SmsTouch;
use App\Jobs\Touch\StatePushTouch;
use App\Jobs\Touch\StateSmsTouch;

class AsyncTaskClient
{
    public static function sendPushByUserId($userId, $title, $content, $action = '', $statisticId = '', $delaySeconds = 0)
    {
        PushTouch::dispatch($userId, $title, $content, $action, $statisticId)->delay(now()->addSeconds($delaySeconds));
    }

    public static function sendSmsByPhone($phone, $content, $delaySeconds = 0)
    {
        SmsTouch::dispatch($phone, $content)->delay(now()->addSeconds($delaySeconds));
    }

    public static function sendStatePushByUserId($userId, $title, $content, $procedureId, $state, $action = '', $statisticId = '', $delaySeconds = 0)
    {
        StatePushTouch::dispatch($userId, $title, $content, $procedureId, $state, $action, $statisticId)->delay(now()->addSeconds($delaySeconds));
    }

    public static function sendStateSmsByPhone($phone, $content, $procedureId, $state, $delaySeconds = 0)
    {
        StateSmsTouch::dispatch($phone, $content, $procedureId, $state)->delay(now()->addSeconds($delaySeconds));
    }
}