<?php
/**
 * Created by Vim.
 * User: liyang
 * Date: 2019-06-28
 * Time: 14:44
 */

namespace App\Services\States\Callbacks;

use App\Common\AlertClient;
use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Consts\Procedure;
use App\Models\Orders;
use App\Services\ProcedureService;
use Illuminate\Support\Facades\Log;

class LoanCallback
{
    public function handle ($mnsMsg)
    {
        try {
            if ( empty($mnsMsg['orderId']) || !isset($mnsMsg['status']) ) {
                throw new \Exception("放款队列数据-参数缺失");
            }

            // 处理队列数据
            $bizNo = $mnsMsg['orderId'];
            $status = ( $mnsMsg['status'] == 1 ) ? Constant::COMMON_STATUS_SUCCESS : Constant::COMMON_STATUS_FAILED;
            $params = ['status' => $status];

            // 检测订单数据
            if ( ! $orderInfo = Orders::where(['biz_no' => $bizNo])->first() ) {
                throw new \Exception("放款队列数据-订单不存在");
            }

            // 初始化流程数据
            $procedureService = new ProcedureService ($orderInfo->procedure_id);

            // 当前流程未到放款，稍后处理
            if ( $procedureService->getState() && !in_array($procedureService->getState(), [Procedure::STATE_LOAN, Procedure::STATE_WITHDRAW]) ) {
                return false;
            }

            // 当前流程到放款，立刻处理
            if( $procedureService->getState() && ($procedureService->getState() == Procedure::STATE_LOAN) && ($procedureService->getOrderId() == $orderInfo->id) ) {
                if ( ! $procedureService->callbackState($params, $orderInfo->toArray()) ) {
                    return false;
                }
            }
        } catch ( \Exception $e ) {
            $message = "module=callback_state\tmethod=" . __METHOD__ . "\tmnsMsg=" . json_encode($mnsMsg) . "\tmsg=" . $e->getMessage() . "\tfile=" . $e->getFile() . "\tline=" . $e->getLine();
            Log::warning( $message );
        }

        return true;
    }

}
