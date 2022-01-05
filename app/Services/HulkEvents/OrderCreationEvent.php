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
use App\Services\HulkEventService;
use Illuminate\Support\Facades\Log;

class OrderCreationEvent
{
    public function handle($orderId)
    {
        if (EventRecords::where('type', HulkEventService::EVENT_TYPE_ORDER_CREATION)->where('relation_id', $orderId)->first()) {
            AlertClient::sendAlertEmail(new CustomException(ErrorCode::COMMON_PARAM_ERROR, 'event repeat:' . $orderId));
            return true;
        }
        $orderInfo = Orders::find($orderId);
        if (!$orderInfo) {
            AlertClient::sendAlertEmail(new CustomException(ErrorCode::COMMON_PARAM_ERROR, 'order not existed:' . $orderId));
            return true;
        }
        $bizNo = Utils::genBizNo();
        $lockerKey = 'order_get_last_result_' . $orderId;
        $locker = new Locker();
        if (!$locker->lock($lockerKey, 2 * 60, $bizNo)) {
            return false;
        }

        try {
            $result = [];
            $lastOrderInfo = Orders::where('user_id', $orderInfo->user_id)
                ->where('created_at', '<', $orderInfo->created_at)
                ->orderBy('id', 'desc')
                ->first();

            $result['is_loaned'] = 0;
            if (!$lastOrderInfo) {
                $result['last_is_rejected'] = -1;
            } elseif (in_array($lastOrderInfo->status, [Constant::ORDER_STATUS_ONGOING, Constant::ORDER_STATUS_PAID_OFF])) {
                $result['is_loaned'] = 1;
                $result['last_is_rejected'] = 1;
            } else {
                $result['last_is_rejected'] = 0;
            }

            if (($result['is_loaned'] == 0) && ($result['last_is_rejected'] == 0)) {
                $successOrderInfo = Orders::where('user_id', $orderInfo->user_id)
                    ->whereIn('status', [Constant::ORDER_STATUS_ONGOING, Constant::ORDER_STATUS_PAID_OFF])
                    ->where('created_at', '<', $orderInfo->created_at)
                    ->first();

                $result['is_loaned'] = empty($successOrderInfo) ? 0 : 1;
            }

            if (!EventRecords::create([
                'user_id' => $orderInfo->user_id,
                'type' => HulkEventService::EVENT_TYPE_ORDER_CREATION,
                'relation_id' => $orderId,
                'data' => json_encode($result),
            ])) {
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, 'add relation failed');
            }

            $locker->restoreLock($lockerKey, $bizNo);
            return true;
        } catch (\Exception $e) {
            $message = "module=OrderCreationEvent\tmethod=" . __METHOD__ . "\torderId=" . $orderId . "\tmsg=" . $e->getMessage();
            AlertClient::sendAlertEmail($e);
            Log::warning($message);
            $locker->restoreLock($lockerKey, $bizNo);
            return false;
        }
    }
}
