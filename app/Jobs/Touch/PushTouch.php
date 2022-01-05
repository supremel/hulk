<?php
/**
 * 推送触达用户相关
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-08-05
 * Time: 16:08
 */

namespace App\Jobs\Touch;

use App\Common\PushClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushTouch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $_userId;
    protected $_title;
    protected $_content;
    protected $_statisticId;
    protected $_action;

    /**
     * Create a new job instance.
     *
     * @param $userId
     * @param $title
     * @param $content
     * @param string $action
     * @param string $statisticId
     */
    public function __construct($userId, $title, $content, $action = '', $statisticId = '')
    {
        $this->_userId = $userId;
        $this->_title = $title;
        $this->_content = $content;
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
        $logContent = "module=push_touch\tuser_id=" . $this->_userId . "\ttitle=" . $this->_title .
            "\tcontent=" . $this->_content . "\taction=" . $this->_action . "\tstatistic_id=" . $this->_statisticId;
        Log::info($logContent);
        try {
            PushClient::pushByUserId($this->_userId, $this->_title, $this->_content, $this->_action, $this->_statisticId);
        } catch (\Exception $e) {
            Log::warning($logContent . "\tmsg=" . $e->getMessage());
        }

    }
}
