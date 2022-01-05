<?php
/**
 * 流程-（提现授权过期检测）
 * User: hexuefei
 * Date: 2019-06-19
 * Time: 18:16
 */

namespace App\Console\Commands\Procedures;

use App\Common\CapitalClient;
use App\Common\Utils;
use App\Consts\Constant;
use App\Consts\Procedure;
use App\Models\OrderPushRecords;
use App\Models\Orders;
use App\Models\Procedures;
use App\Services\ProcedureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WithdrawAuthExpireCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'procedure:withdraw_auth_expire_detect';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '订单-提现授权过期检测';

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
            ->where('sub_status', Procedure::STATE_USER_AUTH)
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

            // 检测，资方72小时超时失效
            $orderPushInfo = OrderPushRecords::where('user_id', $procedure['user_id'])
                ->where('procedure_id', $procedure['id'])
                ->where('status', Constant::COMMON_STATUS_SUCCESS)
                ->first();
            if (!$orderPushInfo || empty($orderPushInfo->finish_time)) {
                Log::warning('module=' . $this->signature . "\trequest_data=" . json_encode($procedure) . "\tmsg=notfound order push record");
            } elseif (Utils::getGapTime($orderPushInfo->finish_time, date('Y-m-d H:i:s'), 'hour') >= 75) {
                $procedureService = new ProcedureService ($procedure['id']);
                if ($procedureService->getState() && ($procedureService->getState() == Procedure::STATE_USER_AUTH)) {
                    // 请求提现授权H5页面
                    $orderData = Orders::find($procedure['order_id'])->toArray();
                    $authH5Result = CapitalClient::userAuth(Utils::genBizNo(), $orderData);
                    if ($authH5Result['code'] == Procedure::USER_AUTH_EXPIRE_CODE) {
                        $procedureService->runState();
                    }
                }
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
