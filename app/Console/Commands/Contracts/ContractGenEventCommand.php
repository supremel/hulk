<?php
/**
 * 合同生成事件处理（接收资方还款计划后生成事件）
 * User: hexuefei
 * Date: 2019-08-12
 * Time: 11:48
 */

namespace App\Console\Commands\Contracts;


use App\Common\AlertClient;
use App\Common\MnsClient;
use App\Consts\Constant;
use App\Consts\Contract;
use App\Models\Orders;
use App\Services\HulkEventService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ContractGenEventCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'contract:gen_event';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '合同-生成事件处理';

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
            if (!MnsClient::handleMsgFromQueue(env('CAPITAL_ACCESS_ID'), env('CAPITAL_ACCESS_KEY'), env('CAPITAL_CONTRACT_DATA_QUEUE_NAME'), function ($msg) {
                Log::info('module=' . $this->signature . "\tmsg=ongoing\tcontent=" . $msg);
                $data = json_decode($msg, true);
                $validator = Validator::make($data, [
                    'accountId' => 'required',
                    'orderId' => 'required',
                    'plans' => 'required|array',
                    'accountNo' => 'required',
                ]);
                if ($validator->fails()) {
                    AlertClient::sendAlertEmail(new \Exception('合同生成-资方还款计划中缺少参数'));
                    return false;
                }
                $order = Orders::where('biz_no', $data['orderId'])->first();
                if (!$order) {
                    AlertClient::sendAlertEmail(new \Exception('合同生成-资方还款计划中参数错误（订单不存在）orderId=' . $data['orderId']));
                    return false;
                }
                if ($order->status != Constant::ORDER_STATUS_ONGOING
                    && $order->status != Constant::ORDER_STATUS_PAID_OFF) {
                    AlertClient::sendAlertEmail(new \Exception('合同生成-资方还款计划中参数错误（订单状态非已放款）orderId=' . $data['orderId']));
                    return false;
                }
                if (count($data['plans']) != $order->periods) {
                    AlertClient::sendAlertEmail(new \Exception('合同生成-资方还款计划中参数错误（还款计划期次不匹配）orderId=' . $data['orderId']));
                    return false;
                }
                $data['contractType'] = Contract::TYPE_LOAN;
                $data['contractStep'] = Contract::STEP_GEN_DISPENSE;
                $data['relationType'] = Contract::RELATION_TYPE_ORDER;
                $data['relationId'] = $order->id;
                $msg = [
                    'event' => HulkEventService::EVENT_TYPE_CONTRACT,
                    'params' => $data,
                ];
                if (MnsClient::sendMsg2Queue(env('HULK_EVENT_ACCESS_ID'), env('HULK_EVENT_ACCESS_KEY'), env('HULK_EVENT_QUEUE_NAME'), json_encode($msg))) {
                    return true;
                }
                return false;
            })) {
                break;
            }
        }
        Log::info('module=' . $this->signature . "\tmsg=ends");
    }
}