<?php
/**
 * 逾期检查
 * User: hexuefei
 * Date: 2019-07-19
 * Time: 18:16
 */

namespace App\Console\Commands\Orders;


use App\Consts\Constant;
use App\Events\InstallmentUpdateEvent;
use App\Helpers\ComputeCenter;
use App\Jobs\Touch\PushTouch;
use App\Models\BankCard;
use App\Models\OrderInstallments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OverdueDetectCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'order:overdue_detect';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '订单-逾期检查';

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
        $installments = OrderInstallments::where('status', Constant::ORDER_STATUS_ONGOING)
            ->where('date', '<', date('Y-m-d'))->get()->toArray();
        foreach ($installments as $installment) {
            $overdueData = ComputeCenter::getOverdueInfo($installment);
            $data = [
                'overdue_days' => $overdueData['days'],
                'fee' => $overdueData['fee'],
            ];
            if (!OrderInstallments::where('id', $installment['id'])->update($data)) {
                Log::warning('module=' . $this->signature . "\tmsg=update overdue data error\tinstallment_id=" .
                    $installment['id'] . "\toverdueData=" . json_encode($data));
            } else {
                Log::info('module=' . $this->signature . "\tmsg=overdue\tinstallment_id=" .
                    $installment['id'] . "\toverdueData=" . json_encode($data));
            }

            // 生成还款计划更新事件
//            event(new InstallmentUpdateEvent($installment['order_id']));
            if (1 == $overdueData['days']) { // 逾期一天
                $bankCard = BankCard::where('user_id', $installment['user_id'])->where('type', Constant::BANK_CARD_AUTH_TYPE_AUTH)
                    ->where('status', Constant::AUTH_STATUS_SUCCESS)->first();
                if ($bankCard) {
                    $amount = $installment['capital'] - $installment['paid_capital'];
                    $amount += ($installment['interest'] - $installment['paid_interest']);
                    $amount += ($installment['fee'] - $installment['paid_fee']);
                    $content = sprintf('您在水莲金条借款已逾期，今日将从尾号%s的银行卡自动还款%.2f元，请保银行卡资金充足！',
                        substr($bankCard['card_no'], -4), $amount / 100.0);
                    PushTouch::dispatch($installment['user_id'], '请及时还款', $content)
                        ->delay(9 * 60 * 60);
                }

            }
        }
        Log::info('module=' . $this->signature . "\tmsg=ends");
    }
}