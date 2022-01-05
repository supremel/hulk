<?php
/**
 * 系统划扣成功同步到老系统
 * User: hexuefei
 * Date: 2019-09-05
 * Time: 18:16
 */

namespace App\Console\Commands\Orders;


use App\Common\AlertClient;
use App\Common\MnsClient;
use App\Common\RedisClient;
use App\Common\Utils;
use App\Consts\Constant;
use App\Helpers\Locker;
use App\Models\OrderInstallments;
use App\Models\Orders;
use App\Models\RepayInstallmentRef;
use App\Models\RepaymentRecords;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DeductionSyncToLegacyCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'order:deduction_sync_to_legacy';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '订单-系统划扣状态同步（to老系统）';

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
     * @throws \App\Exceptions\CustomException
     */
    public function handle()
    {
        Log::info('module=' . $this->signature . "\tmsg=starts");

        $skipKey = 'deduction_sync_to_legacy_skip';
        $lastKey = 'deduction_sync_to_legacy';
        $lastId = RedisClient::get($lastKey);
        if (!$lastId) {
            $lastId = 0;
        }

        $isLastId = true;

        $records = RepaymentRecords::where('id', '>', $lastId)
            ->where('business_type', Constant::RECHARGE_BUSINESS_TYPE_SYYTEM_TIMING)
            ->orderBy('id', 'asc')->get()->toArray();

        if (empty($records) && !empty($skipArr = Redis::hgetall($lastKey))) {
            $isLastId = false;
            $repayIds = array_keys($skipArr);
            $records = RepaymentRecords::whereIn('id', $repayIds)
                ->where('business_type', Constant::RECHARGE_BUSINESS_TYPE_SYYTEM_TIMING)
                ->orderBy('id', 'asc')->get()->toArray();
        }

        Log::info('module=' . $this->signature . "\tmsg=onging\tlast_id=" . $lastId . "\ttotal=" . count($records));
        foreach ($records as $record) {
            $lastId = $isLastId ? $record['id'] : $lastId;
            Log::info('module=' . $this->signature . "\tmsg=ongoing\tstatus=" . $record['status'] . "\trecord=" . json_encode($record));
            if (Constant::COMMON_STATUS_SUCCESS == $record['status'] || Constant::COMMON_STATUS_PART_SUCCESS == $record['status']) {
                $orderId = $record['order_id'];
                $order = Orders::where('id', $orderId)->first();
                $repay = RepayInstallmentRef::where('repayment_id', $record['id'])->first();
                $installmentId = $repay['installment_id'];
                $installment = OrderInstallments::where('id', $installmentId)->first();
                $period = $installment['period'];

                // 防止多次同步数据
                $bizNo = Utils::genBizNo();
                $lockerKey = 'deduction_sync_to_legacy_' . $record['id'];
                $locker = new Locker();
                if (!$locker->lock($lockerKey, 10 * 60, $bizNo)) {
                    continue;
                }

                $msg = json_encode([
                    'orderId' => $order['biz_no'],
                    'period' => $period,
                    'isPartSuccess' => (Constant::COMMON_STATUS_PART_SUCCESS == $record['status']) ? 1 : 0,
                    'successAmount' => bcdiv($record['pay_amount'], 100, 2),
                ]);
                Log::info('module=' . $this->signature . "\tmsg=ongoing\tmns_content=" . $msg);
                $tryTime = 0;
                do {
                    if (MnsClient::sendMsg2Queue(env('DEDUCTION_SYNC_ACCESS_ID'), env('DEDUCTION_SYNC_ACCESS_KEY'),
                        env('DEDUCTION_SYNC_QUEUE_NAME'), $msg)) {
                        break;
                    }
                    $tryTime += 1;
                } while ($tryTime <= 10);
                if (Redis::hexists($skipKey, $record['id'])) {
                    Redis::hdel($skipKey, $record['id']);
                }
            } elseif (Constant::COMMON_STATUS_INIT == $record['status']) {
                Redis::hset($skipKey, $record['id'], true);
                if ((time() - strtotime($record['request_time'])) >= 30 * 60) {
                    AlertClient::sendAlertEmail(new \Exception($this->signature . ":划扣同步老系统（有处理中记录:{$record['biz_no']}）"));
                }
            } else {
                if (Redis::hexists($skipKey, $record['id'])) {
                    Redis::hdel($skipKey, $record['id']);
                }
            }
        }
        RedisClient::setWithExpire($lastKey, $lastId, 90 * 24 * 60 * 60);
        Log::info('module=' . $this->signature . "\tmsg=ends");
    }
}