<?php
/**
 * Created by Vim.
 * User: liyang
 * Date: 2019-06-28
 * Time: 14:44
 */

namespace App\Services\States;

use App\Common\MnsClient;
use App\Common\Utils;
use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Exceptions\CustomException;
use App\Models\Orders;
use App\Models\Procedures;
use App\Services\HulkEventService;
use Illuminate\Support\Facades\DB;

class OrderSubmitState extends State
{
    // 判断跳过状态
    public function autoSkip()
    {
        // 判断跳过订单
        if (Orders::where('id', $this->_procedure->order_id)
                ->whereIn('status', [Constant::ORDER_STATUS_INIT, Constant::ORDER_STATUS_AUDIT])
                ->first() && parent::manageCallbackSuccess()) {
            return true;
        }

        return false;
    }

    // 状态流转处理逻辑
    public function run()
    {
        // 生成BIZ_NO
        $this->_relation['biz_no'] = Utils::genBizNo(20);

        $capital_loan_usage = config('business.procedure.loan_usage.' . $this->_procedure->capital_label);

        // 开启事务
        try {
            DB::transaction(function () use ($capital_loan_usage) {
                // 订单数据
                $orderData = [
                    'biz_no' => $this->_relation['biz_no'],
                    'user_id' => $this->_procedure->user_id,
                    'procedure_id' => $this->_procedure->id,
                    'capital_label' => $this->_procedure->capital_label,
                    'amount' => $this->_params['amount'],
                    'periods' => $this->_params['periods'],
                    'periods_type' => $this->_params['periods_type'],
                    'interest_rate' => $this->_procedure->authed_fee_rate,
                    'repay_type' => $this->_procedure->authed_repay_type,
                    'fee_type' => $this->_procedure->authed_fee_type,
                    'loan_usage' => $this->_params['loan_usage'],
                    'capital_loan_usage' => $capital_loan_usage,
                    'source' => $this->_procedure->source,
                    'status' => Constant::ORDER_STATUS_INIT,
                ];
                if (!$this->_relation = Orders::create($orderData)->toArray()) {
                    throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                }

                // 流程记录
                $procedureData = [
                    'order_amount' => $this->_relation['amount'],
                    'order_periods' => $this->_relation['periods'],
                    'order_id' => $this->_relation['id'],
                ];
                $procedureWhere = [
                    'id' => $this->_procedure->id,
                    'sub_status' => $this->_state,
                ];
                if (!Procedures::where($procedureWhere)->update($procedureData)) {
                    throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                }
                $this->_procedure->order_id = $this->_relation['id'];

                // 状态流转操作
                if (!parent::manageCallbackSuccess()) {
                    throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                }
            });
        } catch (\Exception $e) {
            $this->setLogWarning($e->getMessage());
            return false;
        }

        // 请求事件MNS
        $mnsData = [
            'event' => HulkEventService::EVENT_TYPE_ORDER_CREATION,
            'params' => $this->_relation['id'],
        ];
        MnsClient::sendMsg2Queue(env('HULK_EVENT_ACCESS_ID'), env('HULK_EVENT_ACCESS_KEY'), env('HULK_EVENT_QUEUE_NAME'), json_encode($mnsData));

        return true;
    }

}
