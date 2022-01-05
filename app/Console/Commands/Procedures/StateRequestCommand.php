<?php
/**
 * 流程（处理请求发起）
 * User: liyang
 * Date: 2019-06-19
 * Time: 18:16
 */

namespace App\Console\Commands\Procedures;

use App\Consts\Constant;
use App\Consts\Procedure;
use App\Models\Procedures;
use App\Services\ProcedureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StateRequestCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'procedure:do_request';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '流程-发起第三方请求';

    /**
     * 状态集合
     *
     * @var array
     */
    protected $stateCollect = [
        Procedure::STATE_FIRST_RISK,
        Procedure::STATE_CAPITAL_ROUTE,
        Procedure::STATE_SECOND_RISK,
        Procedure::STATE_ORDER_PUSH,
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
        $procedureList = Procedures::where('crontab_lock', Procedure::STATE_RUNING_NO)
            ->whereIn('sub_status', $this->stateCollect)
            ->where('status', Constant::COMMON_STATUS_INIT)
            ->where('updated_at', '>', date('Y-m-d H:i:s', strtotime('-1 days')))
            ->get()
            ->toArray();

        if (!$procedureList) {
            return false;
        }

        foreach ($procedureList as $procedure) {
            // 加锁
            if (!Procedures::where(['id' => $procedure['id'], 'crontab_lock' => Procedure::STATE_RUNING_NO])->update(['crontab_lock' => Procedure::STATE_RUNING_LOCK])) {
                Log::warning('module=' . $this->signature . "\trequest_data=" . json_encode($procedure) . "\tmsg=add procedure_rontab_lock failed");
                continue;
            }

            // 运行
            $procedureService = new ProcedureService ($procedure['id']);
            if ($procedureService->getState() && in_array($procedureService->getState(), $this->stateCollect)) {
                $procedureService->runState();
            }

            // 解锁
            if (!Procedures::where(['id' => $procedure['id'], 'crontab_lock' => Procedure::STATE_RUNING_LOCK])->update(['crontab_lock' => Procedure::STATE_RUNING_NO])) {
                Log::warning('module=' . $this->signature . "\trequest_data=" . json_encode($procedure) . "\tmsg=del procedure_rontab_lock failed");
            }
        }
        Log::info('module=' . $this->signature . "\tmsg=ends");
        return true;
    }
}
