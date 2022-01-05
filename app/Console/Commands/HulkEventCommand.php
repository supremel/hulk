<?php
/**
 * 业务事件统一处理入口
 * User: hexuefei
 * Date: 2019-08-12
 * Time: 18:16
 */

namespace App\Console\Commands;

use App\Common\MnsClient;
use App\Services\HulkEventService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HulkEventCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'hulk:event';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '业务事件统一处理入口';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::info('module=' . $this->signature . "\tmsg=starts");
        while (true) {
            if (!MnsClient::handleMsgFromQueue(env('HULK_EVENT_ACCESS_ID'), env('HULK_EVENT_ACCESS_KEY'), env('HULK_EVENT_QUEUE_NAME'), function ($msg) {
                Log::info('module=' . $this->signature . "\tmsg=ongoing\tcontent=" . $msg);
                $data = json_decode($msg, true);
                if (empty($data) || !isset($data['event']) || !array_key_exists($data['event'], HulkEventService::EVENT_TYPE_MAP)) {
                    Log::warning('module=' . $this->signature . "\tqueue_data=" . $msg . "\tmsg=invalid event");
                    return true;
                }
                $eventService = new HulkEventService();
                return $eventService->handle($data['event'], $data['params']);
            })) {
                break;
            }
        }
        Log::info('module=' . $this->signature . "\tmsg=ends");
    }

}
