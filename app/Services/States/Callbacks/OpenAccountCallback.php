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
use App\Models\OpenAccountRecords;
use App\Models\Users;
use App\Services\ProcedureService;
use App\Services\States\Helpers\OpenAccountHelper;
use Illuminate\Support\Facades\Log;

class OpenAccountCallback
{
    public function handle ($mnsMsg)
    {
        try {
            if ( empty($mnsMsg['requestNo']) || !isset($mnsMsg['bankCode']) || !isset($mnsMsg['bankCardNo']) || !isset($mnsMsg['status']) ) {
                throw new \Exception("开户队列数据-参数缺失");
            }

            // 处理队列数据
            $bizNo = $mnsMsg['requestNo'];
            $status = ( $mnsMsg['status'] == 1 ) ? Constant::COMMON_STATUS_SUCCESS : Constant::COMMON_STATUS_FAILED;
            $params = [
                'bank_code'     => $mnsMsg['bankCode'],
                'card_no'       => $mnsMsg['bankCardNo'],
                'status'        => $status,
            ];

            // 检测开户数据
            if ( ! $openAccountInfo = OpenAccountRecords::where(['biz_no' => $bizNo, 'status' => Constant::COMMON_STATUS_INIT])->first() ) {
                throw new \Exception("开户队列数据-开户记录无效");
            }

            // 开户银行卡和认证银行卡交叉验证
            if ( ! $userInfo = Users::find( $openAccountInfo->user_id ) ) {
                throw new \Exception("开户队列数据-用户记录不存在");
            }
            if ( $params['card_no'] != $userInfo->card_no ) {
                $message = "module=callback_state\tmethod=" . __METHOD__ . "\tmnsMsg=" . json_encode($mnsMsg) . "\tuserData=" . json_encode(['bank_code' => $userInfo->bank_code, 'card_no' => $userInfo->card_no]) . "\tmsg=开户-开户银行卡与认证银行卡不一致";
                AlertClient::sendAlertEmail(new \Exception($message));
                Log::warning( $message );
                return false;
            }

            // 初始化流程数据
            $procedureService = new ProcedureService ($openAccountInfo->procedure_id);

            // 当前流程到开户，立刻处理
            if( $procedureService->getState() && ($procedureService->getState() == Procedure::STATE_OPEN_ACCOUNT) && ($procedureService->getCapital() == $openAccountInfo->capital_label) ) {
                if ( ! $procedureService->callbackState($params, $openAccountInfo->toArray()) ) {
                    return false;
                }
            } elseif ( ! OpenAccountHelper::updateRecord($openAccountInfo->toArray(), $params, false) ) {
                return false;
            }
        } catch ( \Exception $e ) {
            $message = "module=callback_state\tmethod=" . __METHOD__ . "\tmnsMsg=" . json_encode($mnsMsg) . "\tmsg=" . $e->getMessage() . "\tfile=" . $e->getFile() . "\tline=" . $e->getLine();
            Log::warning( $message );
        }

        return true;
    }

}
