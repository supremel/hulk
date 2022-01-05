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
use App\Models\AuthRecords;
use App\Services\States\Helpers\UserAuthHelper;
use App\Services\ProcedureService;
use Illuminate\Support\Facades\Log;

class UserAuthCallback
{
    public function handle ($mnsMsg)
    {
        try {
            if ( empty($mnsMsg['requestNo']) || !isset($mnsMsg['status']) ) {
                throw new \Exception("放款验证队列数据-参数缺失");
            }

            // 处理队列数据
            $bizNo = $mnsMsg['requestNo'];
            $status = ( $mnsMsg['status'] == 1 ) ? Constant::COMMON_STATUS_SUCCESS : Constant::COMMON_STATUS_FAILED;
            $params = ['status' => $status];

            // 检测放款验证数据
            if ( ! $userAuthInfo = AuthRecords::where(['biz_no' => $bizNo, 'status' => Constant::COMMON_STATUS_INIT])->first() ) {
                throw new \Exception("放款验证队列数据-放款验证记录无效");
            }

            // 初始化流程数据
            $procedureService = new ProcedureService ($userAuthInfo->procedure_id);

            // 当前流程到放款验证，立刻处理
            if( $procedureService->getState() && ($procedureService->getState() == Procedure::STATE_USER_AUTH) && ($procedureService->getOrderId() == $userAuthInfo->order_id) ) {
                if ( ! $procedureService->callbackState($params, $userAuthInfo->toArray()) ) {
                    return false;
                }
            } elseif ( ! UserAuthHelper::updateRecord($userAuthInfo->toArray(), $params, false) ) {
                return false;
            }
        } catch ( \Exception $e ) {
            $message = "module=callback_state\tmethod=" . __METHOD__ . "\tmnsMsg=" . json_encode($mnsMsg) . "\tmsg=" . $e->getMessage() . "\tfile=" . $e->getFile() . "\tline=" . $e->getLine();
            Log::warning( $message );
        }

        return true;
    }

}
