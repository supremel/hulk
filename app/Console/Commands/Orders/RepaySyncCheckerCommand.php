<?php

namespace App\Console\Commands\Orders;

use App\Common\AlertClient;
use App\Consts\Constant;
use App\Models\OrderInstallments;
use App\Models\Orders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RepaySyncCheckerCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'order:repay_sync_checker';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '订单-还款状态同步数据校验（from老系统）';

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
        $startTime = strtotime(date('Y-m-d')) * 1000;
        $sql = "select id,term_num,deduct_amount,deduct_order_id,asset_id  from payment_deduct_record where deduct_state=1 
                and (ident is null or ident='APPBULLION' or ident='JIE360') and create_time >= " . $startTime;
        $data = DB::connection('legacy')->select($sql);
        $orderSql = "select biz_status from user_order where order_sn='%s'";
        foreach ($data as $item) {
            $deductOrderId = $item->deduct_order_id;
            $orderId = $item->asset_id;
            $id = $item->id;
            $amount = $item->deduct_amount;
            $period = $item->term_num;
            if (1 == $period) {
                $orderData = DB::connection('legacy')->select(sprintf($orderSql, $orderId));
                $bizStatus = ($orderData[0])->biz_status;
                if (1100 == $bizStatus) {
                    $period = -1;
                }
            }
            Log::info('module=' . $this->signature . "\tmsg=ongoing\torder_id=$orderId\tperiod=$period");
            $newOrder = Orders::where('biz_no', $orderId)->first();
            if (!$newOrder) {
                continue;
            }
            if (-1 != $period) {
                $installments = OrderInstallments::where('order_id', $newOrder['id'])
                    ->where('period', $period)->get()->toArray();
            } else {
                $installments = OrderInstallments::where('order_id', $newOrder['id'])
                    ->get()->toArray();
            }

            $isOk = true;
            foreach ($installments as $installment) {
                if ($installment['status'] != Constant::ORDER_STATUS_PAID_OFF) {
                    $isOk = false;
                    break;
                }
            }
            if (!$isOk) {
                AlertClient::sendAlertEmail(new \Exception("同步数据校验失败：" . $orderId . ":" . $period));
            }
        }

        Log::info('module=' . $this->signature . "\tmsg=ends");
    }

}
