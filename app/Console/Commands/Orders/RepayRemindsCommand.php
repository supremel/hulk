<?php
/**
 * 还款提醒
 * User: hexuefei
 * Date: 2019-07-19
 * Time: 18:16
 */

namespace App\Console\Commands\Orders;


use App\Common\PushClient;
use App\Common\SmsClient;
use App\Consts\Constant;
use App\Consts\SmsContent;
use App\Models\OrderInstallments;
use App\Models\Users;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RepayRemindsCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'order:repay_reminds';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '订单-还款提醒';

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
        foreach (range(0, 3) as $_ => $days) {
            $d = date('Y-m-d', strtotime($days . " days"));
            $installments = OrderInstallments::where('date', $d)->where('status', Constant::ORDER_STATUS_ONGOING)
                ->get()->toArray();
            Log::info('module=' . $this->signature . "\tmsg=ongoing\tdate=" . $d . "\ttotal=" . count($installments));
            foreach ($installments as $installment) {
                $left = $installment['capital'] - $installment['paid_capital'];
                $left += ($installment['interest'] - $installment['paid_interest']);
                $left += ($installment['fee'] - $installment['paid_fee']);
                $month = substr($installment['date'], 5, 2);
                $day = substr($installment['date'], 8, 2);
                if ($days == 0) { // 当天提醒
                    $smsContent = sprintf(SmsContent::REPAY_REMIND_CURRENT_FORMAT, $left / 100.0);
                } else {
                    $smsContent = sprintf(SmsContent::REPAY_REMIND_FORMAT, $left / 100.0, $month, $day);
                }
                $userInfo = Users::where('id', $installment['user_id'])->first();
                SmsClient::sendSms($userInfo['phone'], $smsContent);
                if ($days == 1) { // 还款日前一天
                    $content = sprintf('亲，您本月水莲金条账单%.2f元，记得明天16:00前还款哦', $left / 100.0);
                    PushClient::pushByUserId($installment['user_id'], '请按时还款', $content);
                }
            }
        }
        Log::info('module=' . $this->signature . "\tmsg=ends");
    }
}