<?php
/**
 * 业务事件统一处理
 * User: hexuefei
 * Date: 2019-08-12
 * Time: 14:44
 */

namespace App\Services\HulkEvents;

use App\Common\AlertClient;
use App\Common\Utils;
use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Exceptions\CustomException;
use App\Helpers\Locker;
use App\Models\EventRecords;
use App\Models\Orders;
use App\Models\RiskEvaluations;
use App\Services\HulkEventService;
use Illuminate\Support\Facades\Log;

class RiskCreationEvent
{
    public function handle($riskId)
    {
        if (EventRecords::where('type', HulkEventService::EVENT_TYPE_RISK_EVALUATION_CREATION)->where('relation_id', $riskId)->first()) {
            AlertClient::sendAlertEmail(new CustomException(ErrorCode::COMMON_PARAM_ERROR, 'event repeat:' . $riskId));
            return true;
        }

        $riskInfo = RiskEvaluations::find($riskId);
        if (!$riskInfo) {
            AlertClient::sendAlertEmail(new CustomException(ErrorCode::COMMON_PARAM_ERROR, 'risk record not existed:' . $riskId));
            return true;
        }
        $bizNo = Utils::genBizNo();
        $lockerKey = 'risk_get_last_result_' . $riskId;
        $locker = new Locker();
        if (!$locker->lock($lockerKey, 2 * 60, $bizNo)) {
            return false;
        }

        try {
            $result = [];
            $lastRiskInfo = RiskEvaluations::where('user_id', $riskInfo->user_id)
                ->where('created_at', '<', $riskInfo->created_at)
                ->where('status', '<>', Constant::COMMON_STATUS_INIT)
                ->orderBy('id', 'desc')
                ->first();

            if (!$lastRiskInfo) {
                $result['last_is_rejected'] = -1;
            } else {
                $result['last_is_rejected'] = ($lastRiskInfo->status == Constant::COMMON_STATUS_SUCCESS) ? 0 : 1;
            }

            $successOrderInfo = Orders::where('user_id', $riskInfo->user_id)
                ->whereIn('status', [Constant::ORDER_STATUS_ONGOING, Constant::ORDER_STATUS_PAID_OFF])
                ->where('created_at', '<', $riskInfo->created_at)
                ->first();

            $result['is_loaned'] = empty($successOrderInfo) ? 0 : 1;

            if (!EventRecords::create([
                'user_id' => $riskInfo->user_id,
                'type' => HulkEventService::EVENT_TYPE_RISK_EVALUATION_CREATION,
                'relation_id' => $riskId,
                'data' => json_encode($result),
            ])) {
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, 'add relation failed');
            }

            $locker->restoreLock($lockerKey, $bizNo);
            return true;
        } catch (\Exception $e) {
            $message = "module=RiskCreationEvent\tmethod=" . __METHOD__ . "\triskId=" . $riskId . "\tmsg=" . $e->getMessage();
            AlertClient::sendAlertEmail($e);
            Log::warning($message);
            $locker->restoreLock($lockerKey, $bizNo);
            return false;
        }
    }
}
