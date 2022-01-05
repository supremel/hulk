<?php
/**
 * 流程（处理异步结果）
 * User: liyang
 * Date: 2019-06-19
 * Time: 18:16
 */

namespace App\Console\Commands\Procedures;

use App\Common\MnsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StateCallbackCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'procedure:handle_async_result';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '流程-处理异步结果(mns)';

    /**
     * 方法映射
     *
     * @var string
     */
    protected $classmap = [
        'open-account' => '\App\Services\States\Callbacks\OpenAccountCallback',
        'apply' => '\App\Services\States\Callbacks\OrderPushCallback',
        'withdrawauth' => '\App\Services\States\Callbacks\UserAuthCallback',
        'loan' => '\App\Services\States\Callbacks\LoanCallback',
        'withdraw' => '\App\Services\States\Callbacks\WithdrawCallback',
    ];

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
            $result = MnsClient::handleMsgFromQueue(env('CAPITAL_ACCESS_ID'), env('CAPITAL_ACCESS_KEY'), env('CAPITAL_QUEUE_NAME'), function ($msg) {

                if (empty($mnsMsg = json_decode($msg, true))) {
                    Log::warning('module=' . $this->signature . "\tqueue_data=" . $msg . "\tmsg=invalid json");
                    return true;
                }

                if (empty($mnsMsg['action']) || !in_array($mnsMsg['action'], array_keys($this->classmap))) {
                    Log::warning('module=' . $this->signature . "\tqueue_data=" . $msg . "\tmsg=invalid action");
                    return true;
                }

                $class = $this->classmap[$mnsMsg['action']];
                $obj = new $class();
                return $obj->handle($mnsMsg);
            });

            if (!$result) {
                break;
            }
        }
        Log::info('module=' . $this->signature . "\tmsg=ends");
    }

}
