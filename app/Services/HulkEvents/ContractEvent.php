<?php
/**
 * 业务事件统一处理
 * User: hexuefei
 * Date: 2019-08-12
 * Time: 14:44
 */

namespace App\Services\HulkEvents;

use App\Common\AlertClient;
use App\Consts\Contract;
use App\Exceptions\CustomException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ContractEvent
{
    public function handle($params)
    {
        try {
            Log::info("module=ContractEvent\tmsg=ongoing\tcontent=" . json_encode($params));
            $validator = Validator::make($params, [
                'contractType' => 'required',
                'contractStep' => 'required',
                'relationType' => 'required',
                'relationId' => 'required',
            ]);
            if ($validator->fails()) {
                AlertClient::sendAlertEmail(new \Exception('合同生成-缺少类型和步骤参数'));
                return false;
            }

            $handleClass = Contract::STEP_RELATION[$params['contractStep']];
            $handleObj = new $handleClass();
            return $handleObj->handle($params);
        } catch (\Exception $e) {
            $message = "module=ContractEvent\tmethod=" . __METHOD__ . "\tparams=" . json_encode($params) . "\tmsg=" . $e->getMessage();
            AlertClient::sendAlertEmail($e);
            Log::warning( $message );
            return false;
        }
    }
}
