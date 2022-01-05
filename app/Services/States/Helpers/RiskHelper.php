<?php
/**
 * Created by Vim.
 * User: liyang
 * Date: 2019-06-28
 * Time: 14:44
 */

namespace App\Services\States\Helpers;

use App\Consts\Procedure;
use App\Services\ProcedureService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Consts\ErrorCode;
use App\Models\RiskEvaluations;
use App\Models\EventRecords;
use App\Models\Orders;
use App\Consts\Constant;
use App\Common\AlertClient;
use App\Helpers\Locker;
use App\Common\Utils;

class RiskHelper
{
    public static function callback ($bizNo, $params)
    {
        try{
            $riskInfo = RiskEvaluations::where('biz_no', $bizNo)->first();

            // 检测风控数据
            if ( empty($riskInfo) ) {
                throw new \Exception('请求记录不存在');
            }

            if ( $riskInfo->status != Constant::COMMON_STATUS_INIT ) {
                return true;
            }

            // 初始化流程数据
            $procedureService = new ProcedureService ($riskInfo->procedure_id);

            // 当前流程到风控，立刻处理
            if( $procedureService->getState() && in_array($procedureService->getState(), [Procedure::STATE_FIRST_RISK, Procedure::STATE_SECOND_RISK]) ) {
                if ( ! $procedureService->callbackState($params, $riskInfo->toArray()) ) {
                    throw new \Exception('流程记录处理失败');
                }
            } elseif ( ! self::updateRecord($riskInfo->toArray(), $params, false) ) {
                throw new \Exception('风控记录处理失败');
            }
        } catch ( \Exception $e ) {
            $message = "module=helper_state\tmethod=" . __METHOD__ . "\tparams=" . json_encode($params) . "\tmsg=" . $e->getMessage() . "\tfile=" . $e->getFile() . "\tline=" . $e->getLine();
            Log::warning( $message );
            return false;
        }

        return true;
    }

    public static function updateRecord ($relation, $params, $operate = true)
    {
        try{
            // 审核记录
            $riskData = array_merge( $params, [
                'finish_time'   => date('Y-m-d H:i:s'),
                'no_operate'    => $operate ? Procedure::OPERATE_OK : Procedure::OPERATE_NO,
            ]);
            $riskWhere = [
                'id'        => $relation['id'],
                'status'    => Constant::COMMON_STATUS_INIT,
            ];

            if ( RiskEvaluations::where($riskWhere)->update( $riskData ) ) {
                return $riskData;
            }

            return null;
        } catch ( \Exception $e ) {
            $message = "module=helper_state\tmethod=" . __METHOD__ . "\tparams=" . json_encode($params) . "\tmsg=" . $e->getMessage() . "\tfile=" . $e->getFile() . "\tline=" . $e->getLine();
            Log::warning( $message );
            return null;
        }
    }

}
