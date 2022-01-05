<?php
/**
 * 短信触达用户相关
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-08-05
 * Time: 16:08
 */

namespace App\Jobs\Touch;

use App\Common\SmsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmsTouch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $_phone;
    protected $_content;

    /**
     * Create a new job instance.
     *
     * @param $phone
     *
     * @param $content
     *
     * @return void
     */
    public function __construct($phone, $content)
    {
        $this->_phone = $phone;
        $this->_content = $content;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("module=sms_touch\tphone=" . $this->_phone . "\tcontent=" . $this->_content);
        try {
            SmsClient::sendSms($this->_phone, $this->_content);
        } catch (\Exception $e) {
            Log::warning("module=sms_touch\tphone=" . $this->_phone . "\tcontent=" . $this->_content . "\tmsg=" . $e->getMessage());
        }

    }
}
