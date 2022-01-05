<?php
/**
 * 短信触达用户相关,该类方法只用于流程中,流程中需要添加前置判断
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-08-05
 * Time: 16:08
 */

namespace App\Jobs\Touch;

use App\Common\SmsClient;
use App\Services\ProcedureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StateSmsTouch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $_phone;
    protected $_content;
    protected $_procedureId;
    protected $_state;

    /**
     * Create a new job instance.
     *
     * @param $phone
     * @param $content
     * @param $procedureId
     * @param $state
     */
    public function __construct($phone, $content, $procedureId, $state)
    {
        $this->_phone = $phone;
        $this->_content = $content;
        $this->_procedureId = $procedureId;
        $this->_state = $state;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("module=state_sms_touch\tphone=" . $this->_phone . "\tcontent=" . $this->_content . "\tprocedureId=" . $this->_procedureId . "\tstate=" . $this->_state);

        // 前置判断
        $procedureService = new ProcedureService($this->_procedureId);
        if ( empty( $procedureService->getState() ) || ( $procedureService->getState() != $this->_state ) ) {
            return false;
        }

        try {
            SmsClient::sendSms($this->_phone, $this->_content);
        } catch (\Exception $e) {
            Log::warning("module=sms_touch\tphone=" . $this->_phone . "\tcontent=" . $this->_content . "\tmsg=" . $e->getMessage());
        }

    }
}
