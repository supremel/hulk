<?php
/**
 * 老系统还款同步
 * User: hexuefei
 * Date: 2019-08-12
 * Time: 18:16
 */

namespace App\Console\Commands\Orders;


use App\Common\AlertClient;
use App\Common\MnsClient;
use App\Consts\Constant;
use App\Helpers\RepayCenter;
use App\Models\Orders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RepaySyncFromLegacyCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'order:repay_sync_from_legacy';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '订单-还款状态同步（from老系统）';

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
            if (!MnsClient::handleMsgFromQueue(env('CAPITAL_ACCESS_ID'), env('CAPITAL_ACCESS_KEY'), env('CAPITAL_REPAY_SYNC_QUEUE_NAME'), function ($msg) {
                Log::info('module=' . $this->signature . "\tmsg=ongoing\tcontent=" . $msg);
                $data = json_decode($msg, true);
                $validator = Validator::make($data, [
                    'amount' => 'required',
                    'orderId' => 'required',
                    'period' => 'required',
                    'deductOrderId' => 'required',
                ]);
                if ($validator->fails()) {
                    Log::warning('module=' . $this->signature . "\tmsg=ongoing\terror=参数错误:" . $msg);
                    //AlertClient::sendAlertEmail(new \Exception($this->description . ":参数错误"));
                    return true;
                }
                $data['isPartSuccess'] = empty($data['isPartSuccess']) ? 0 : $data['isPartSuccess'];

                $orderInfo = Orders::where('biz_no', $data['orderId'])->first();
                if (!$orderInfo) {
                    Log::warning('module=' . $this->signature . "\tmsg=ongoing\terror=订单号错误:" . $data['orderId'] . ":" . $data['period']);
                    //AlertClient::sendAlertEmail(new \Exception($this->description . ":订单号错误:" . $data['orderId']));
                    return true;
                }
                if (Constant::ORDER_STATUS_ONGOING != $orderInfo['status']) {
                    Log::warning('module=' . $this->signature . "\tmsg=ongoing\terror=订单状态错误:" . $data['orderId'] . ":" . $data['period']);
                    //AlertClient::sendAlertEmail(new \Exception($this->description . ":订单状态错误:" . $data['orderId']));
                    return true;
                }
                try {
                    RepayCenter::repaySyncFromLegacy($orderInfo['user_id'], $orderInfo,
                        intval(bcmul($data['amount'], '100')), $data['period'], $data['deductOrderId'],
                        $data['isPartSuccess']);
                } catch (\Exception $exception) {
                    AlertClient::sendAlertEmail(new \Exception($this->description . ":同步错误:" . $data['orderId'] . ":" . $data['period'] . ":" . $exception->getMessage()));
                    return false;
                }
                return true;
            })) {
                break;
            }
        }
        Log::info('module=' . $this->signature . "\tmsg=ends");
    }
}