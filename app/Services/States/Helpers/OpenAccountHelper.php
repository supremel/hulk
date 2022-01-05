<?php
/**
 * Created by Vim.
 * User: liyang
 * Date: 2019-06-28
 * Time: 14:44
 */

namespace App\Services\States\Helpers;

use App\Common\AlertClient;
use App\Common\CapitalClient;
use App\Common\Utils;
use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Consts\Procedure;
use App\Helpers\Locker;
use App\Helpers\UserHelper;
use App\Models\OpenAccountRecords;
use App\Services\ProcedureService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OpenAccountHelper
{
    public static function updateRecord ($relation, $params, $operate = true)
    {
        try {
            // 开户记录
            $openAccountData = array_merge( $params, [
                'finish_time'       => date('Y-m-d H:i:s'),
                'no_operate'        => $operate ? Procedure::OPERATE_OK : Procedure::OPERATE_NO,
            ]);
            $openAccountWhere = [
                'id'            => $relation['id'],
                'status'        => Constant::COMMON_STATUS_INIT,
            ];

            return OpenAccountRecords::where($openAccountWhere)->update($openAccountData);
        } catch ( \Exception $e ) {
            $message = "module=helper_state\tmethod=" . __METHOD__ . "\tparams=" . json_encode($params) . "\tmsg=" . $e->getMessage() . "\tfile=" . $e->getFile() . "\tline=" . $e->getLine();
            Log::warning( $message );
            return false;
        }
    }

    // 查询用户开户信息
    public static function getThirdResult ($userId, $capitalLabel)
    {
        $userData = UserHelper::getUserData($userId);
        $result = CapitalClient::openAccountResult($userData, $capitalLabel);

        $params = [
            'status' => Constant::COMMON_STATUS_FAILED,
            'bank_code' => '',
            'card_no' => '',
            'extra' => '查询失败',
        ];

        if ( $result && ( $result['code'] == 200 ) && ( $result['data']['tranState'] == 'SUCCESS' ) ) {
            $params = [
                'status' => Constant::COMMON_STATUS_SUCCESS,
                'bank_code' => $result['data']['bankCode'],
                'card_no' => $result['data']['bankCardNo'],
                'extra' => '查询成功',
            ];
        }

        return $params;
    }

}
