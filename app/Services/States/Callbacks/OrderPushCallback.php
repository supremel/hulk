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
use App\Models\OrderPushRecords;
use App\Models\Orders;
use App\Services\ProcedureService;
use App\Services\States\Helpers\OrderPushHelper;
use Illuminate\Support\Facades\Log;

class OrderPushCallback
{
    public function handle ($mnsMsg)
    {
        try {
            if ( empty($mnsMsg['requestNo']) || !isset($mnsMsg['status']) ) {
                throw new \Exception("进件队列数据-参数缺失");
            }

            // 处理队列数据
            $bizNo = $mnsMsg['requestNo'];
            $status = ( $mnsMsg['status'] == 1 ) ? Constant::COMMON_STATUS_SUCCESS : Constant::COMMON_STATUS_FAILED;
            $params = ['status' => $status];

            // 检测进件数据
            if ( ! $orderPushInfo = OrderPushRecords::where(['biz_no' => $bizNo, 'status' => Constant::COMMON_STATUS_INIT])->first() ) {
                throw new \Exception("进件队列数据-进件记录无效");
            }

            // 初始化流程数据
            $procedureService = new ProcedureService ($orderPushInfo->procedure_id);

            // 当前流程到进件，立刻处理
            if( $procedureService->getState() && ($procedureService->getState() == Procedure::STATE_ORDER_PUSH) && ($procedureService->getCapital() == $orderPushInfo->capital_label) ) {
                if ( ! $procedureService->callbackState($params, $orderPushInfo->toArray()) ) {
                    return false;
                }
            } elseif ( ! OrderPushHelper::updateRecord($orderPushInfo->toArray(), $params, false) ) {
                return false;
            }
        } catch ( \Exception $e ) {
            $message = "module=callback_state\tmethod=" . __METHOD__ . "\tmnsMsg=" . json_encode($mnsMsg) . "\tmsg=" . $e->getMessage() . "\tfile=" . $e->getFile() . "\tline=" . $e->getLine();
            Log::warning( $message );
        }

        return true;
    }

}
