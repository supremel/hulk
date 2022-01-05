<?php
/**
 * 业务报警相关
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-22
 * Time: 16:08
 */

namespace App\Common;


use App\Jobs\Alerts\EmailAlert;
use App\Jobs\Alerts\SmsAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlertClient
{
    public static function sendAlertEmail(\Exception $e, $request = null)
    {
        try {
            Log::warning("path=" . ($request ? $request->getPathInfo() : '')
                . "\tcode=" . $e->getCode() . "\tmessage=" . $e->getMessage()
                . "\ttrace=" . str_replace("\n", ';', $e->getTraceAsString()));
            Mail::to(json_decode(env('ALERT_EMAIL_USER_LIST'), true))
                ->queue(new EmailAlert($e, $request));
//            Mail::to(json_decode(env('ALERT_EMAIL_USER_LIST'), true))
//                ->send(new EmailAlert($e, $request));
        } catch (\Exception $e) {
            Log::warning("module=alert_client\tfunc=sendAlertEmail\tmsg=" . $e->getMessage());
        }

    }

    public static function sendAlertSms($alertMsg)
    {

        try {
            SmsAlert::dispatch($alertMsg);
        } catch (\Exception $e) {
            Log::warning("module=alert_client\tfunc=sendAlertSms\tmsg=" . $e->getMessage());
        }

    }
}