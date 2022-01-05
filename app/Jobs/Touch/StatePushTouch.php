<?php
/**
 * 推送触达用户相关,该类方法只用于流程中,流程中需要添加前置判断
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-08-05
 * Time: 16:08
 */

namespace App\Jobs\Touch;

use App\Common\PushClient;
use App\Services\ProcedureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StatePushTouch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $_userId;
    protected $_title;
    protected $_content;
    protected $_procedureId;
    protected $_state;
    protected $_action;
    protected $_statisticId;

    /**
     * Create a new job instance.
     *
     * @param $userId
     * @param $title
     * @param $content
     * @param $procedureId
     * @param $state
     * @param string $action
     * @param string $statisticId
     */
    public function __construct($userId, $title, $content, $procedureId, $state, $action = '', $statisticId = '')
    {
        $this->_userId = $userId;
        $this->_title = $title;
        $this->_content = $content;
        $this->_procedureId = $procedureId;
        $this->_state = $state;
        $this->_action = $action;
        $this->_statisticId = $statisticId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $logContent = "module=state_push_touch\tuser_id=" . $this->_userId . "\ttitle=" . $this->_title .
            "\tcontent=" . $this->_content . "\tprocedureId=" . $this->_procedureId . "\tstate=" . $this->_state .
            "\taction=" . $this->_action . "\tstatistic_id=" . $this->_statisticId;
        Log::info($logContent);

        // 前置判断
        $procedureService = new ProcedureService($this->_procedureId);
        if ( empty( $procedureService->getState() ) || ( $procedureService->getState() != $this->_state ) ) {
            return false;
        }

        try {
            PushClient::pushByUserId($this->_userId, $this->_title, $this->_content, $this->_action, $this->_statisticId);
        } catch (\Exception $e) {
            Log::warning($logContent . "\tmsg=" . $e->getMessage());
        }

    }
}
