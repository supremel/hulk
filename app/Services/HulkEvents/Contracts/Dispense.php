<?php
/**
 * 业务事件统一处理
 * User: hexuefei
 * Date: 2019-08-12
 * Time: 14:44
 */

namespace App\Services\HulkEvents\Contracts;

use App\Common\AlertClient;
use App\Common\MnsClient;
use App\Consts\Contract;
use App\Services\HulkEventService;
use Illuminate\Support\Facades\Log;

class Dispense
{
    public function handle($params)
    {
        try {
            Log::info("module=Dispense\tmsg=ongoing\tcontent=" . json_encode($params));
            $typeRelation = Contract::TYPE_RELATION[$params['contractType']];
            foreach ($typeRelation as $relation) {
                $params['contractStep'] = Contract::STEP_GEN_PDF_DATA;
                $params['contractAgreement'] = $relation;
                $msg = [
                    'event' => HulkEventService::EVENT_TYPE_CONTRACT,
                    'params' => $params,
                ];
                MnsClient::sendMsg2Queue(env('HULK_EVENT_ACCESS_ID'), env('HULK_EVENT_ACCESS_KEY'), env('HULK_EVENT_QUEUE_NAME'), json_encode($msg));
            }
            return true;
        } catch (\Exception $e) {
            $message = "module=Dispense\tmethod=" . __METHOD__ . "\tparams=" . json_encode($params) . "\tmsg=" . $e->getMessage();
            AlertClient::sendAlertEmail($e);
            Log::warning($message);
            return false;
        }
    }
}
