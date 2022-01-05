<?php

namespace App\Jobs\Alerts;

use App\Common\SmsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmsAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $_alertMsg;

    /**
     * Create a new job instance.
     *
     * @param $alertMsg
     *
     * @return void
     */
    public function __construct($alertMsg)
    {
        $this->_alertMsg = $alertMsg;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("module=sms_alert\talert_msg=" . $this->_alertMsg);
        try {
            $phones = json_decode(env('ALERT_SMS_USER_LIST'), true);
            foreach ($phones as $phone) {
                SmsClient::sendSms($phone, $this->_alertMsg);
            }
        } catch (\Exception $e) {
            Log::warning("module=sms_alert\talert_msg=" . $this->_alertMsg . "\tmsg=" . $e->getMessage());
        }

    }
}
