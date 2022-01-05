<?php
/**
 * 系统自动充值(还款)
 * User: hexuefei
 * Date: 2019-07-19
 * Time: 18:16
 */

namespace App\Console\Commands\Orders;


use App\Consts\Constant;
use App\Helpers\RepayCenter;
use App\Models\BankCard;
use App\Models\OrderInstallments;
use App\Models\Orders;
use App\Models\Users;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoRepayDeductionCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'order:auto_repay_deduction';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '订单-系统自动扣款（分扣）';

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
        $installments = OrderInstallments::where('date', date('Y-m-d'))->where('status', Constant::ORDER_STATUS_ONGOING)
            ->get()->toArray();
        foreach ($installments as $installment) {
            $order = Orders::where('id', $installment['order_id'])->first();

            // 过滤导流API数据-系统代扣
            if($order->source != Constant::USER_SOURCE_APP) {
                continue;
            }

            $user = Users::where('id', $installment['user_id'])->first();
            $card = BankCard::where('user_id', $installment['user_id'])
                ->where('type', Constant::BANK_CARD_AUTH_TYPE_AUTH)
                ->where('status', Constant::AUTH_STATUS_SUCCESS)->first();
            $amount = $installment['capital'] + $installment['interest'] + $installment['fee'] + $installment['other_fee']
                - $installment['paid_interest'] - $installment['paid_capital']
                - $installment['paid_fee'] - $installment['paid_other_fee'];
            try {
                RepayCenter::doRepay($user, $order, $card, $amount, 0,
                    Constant::RECHARGE_BUSINESS_TYPE_SYYTEM_TIMING, $installment['id'], '', 0, true);
                Log::warning('module=' . $this->signature . "\tinstallment_id=" . $installment['id'] . "\tmsg=repay request success");
            } catch (\Exception $e) {
                Log::warning('module=' . $this->signature . "\tinstallment_id=" . $installment['id'] . "\tmsg=repay failed," . $e->getMessage());
            }
        }
        Log::info('module=' . $this->signature . "\tmsg=ends");
    }
}