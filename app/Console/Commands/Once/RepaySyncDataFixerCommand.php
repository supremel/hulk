<?php

namespace App\Console\Commands\Once;

use App\Common\MnsClient;
use App\Common\RedisClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RepaySyncDataFixerCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'once:repay_sync_data_fixer {msg_info}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '临时任务-还款同步数据修复器';

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
        do {
            $msg = $this->argument('msg_info');;
            $data = json_decode($msg, true);
            if (empty($data)) {
                var_dump("mns消息格式不正确：" . $msg);
                break;
            }
            $orderId = $data['orderId'];
            $period = $data['period'];
            if (empty($orderId) || empty($period)) {
                var_dump("mns消息格式不正确：" . $msg);
                break;
            }
            RedisClient::setWithExpire(sprintf('repaySyncFromLegacy_%s_%d', $orderId, $period), 1, 3600);

            MnsClient::sendMsg2Queue(env('CAPITAL_ACCESS_ID'), env('CAPITAL_ACCESS_KEY'), env('CAPITAL_REPAY_SYNC_QUEUE_NAME'), $msg);

        } while (false);
        Log::info('module=' . $this->signature . "\tmsg=ends");
    }

}
