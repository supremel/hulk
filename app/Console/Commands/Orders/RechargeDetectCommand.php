<?php
/**
 * 充值状态检查
 * User: hexuefei
 * Date: 2019-07-19
 * Time: 18:16
 */

namespace App\Console\Commands\Orders;


use App\Common\CapitalClient;
use App\Consts\Constant;
use App\Helpers\RepayCenter;
use App\Models\RepaymentRecords;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RechargeDetectCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'order:recharge_detect';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '订单-还款-充值状态检查';

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
        $records = RepaymentRecords::where('created_at', '>', date('Y-m-d H:i:s', strtotime('-1 day')))->where('status', Constant::COMMON_STATUS_INIT)
            ->where('business_type', '!=', Constant::RECHARGE_BUSINESS_TYPE_OFFLINE)->get()->toArray();
        foreach ($records as $record) {
            if (Constant::RECHARGE_API_DEDUCTION == $record['repay_api']) {
                $retData = CapitalClient::getDeductionRechargeStatus($record['biz_no']);
            } else {
                $retData = CapitalClient::getRechargeStatus($record['biz_no']);
            }

            if ($retData && ('FAIL' == $retData['tranState'] || null == $retData['tranState'])) {
                try {
                    RepayCenter::repayFail($record['biz_no'], $retData);
                } catch (\Exception $e) {
                    Log::warning('module=' . $this->signature . "\trecord_id="
                        . $record['id'] . "\tmsg=" . $e->getMessage());
                }
            } else if ($retData && 'SUCCESS' == $retData['tranState']) {
                try {
                    RepayCenter::repaySuccess($record['biz_no'], $retData);
                } catch (\Exception $e) {
                    Log::warning('module=' . $this->signature . "\trecord_id="
                        . $record['id'] . "\tmsg=" . $e->getMessage());
                }
            }  else if ((Constant::RECHARGE_API_DEDUCTION == $record['repay_api']) && ($retData && 'PARTSUCCESS' == $retData['tranState'])) {
                try {
                    RepayCenter::repayPartSuccess($record['biz_no'], $retData);
                } catch (\Exception $e) {
                    Log::warning('module=' . $this->signature . "\trecord_id="
                        . $record['id'] . "\tmsg=" . $e->getMessage());
                }
            } else {
                Log::info('module=' . $this->signature . "\tmsg=status same\trecord_id=" .
                    $record['id'] . "\tstatus=" . Constant::COMMON_STATUS_INIT);
            }
        }
        Log::info('module=' . $this->signature . "\tmsg=ends");
    }
}