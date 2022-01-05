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
use App\Models\AuthRecords;
use App\Models\OpenAccountRecords;
use App\Models\OrderPushRecords;
use App\Models\Orders;
use App\Services\ProcedureService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserAuthHelper
{
    public static function updateRecord ($relation, $params, $operate = true)
    {
        try {
            // 授权记录
            $authData = array_merge( $params, [
                'finish_time'       => date('Y-m-d H:i:s'),
                'no_operate'        => $operate ? Procedure::OPERATE_OK : Procedure::OPERATE_NO,
            ]);
            $authWhere = [
                'id'            => $relation['id'],
                'status'        => Constant::COMMON_STATUS_INIT,
            ];

            return AuthRecords::where($authWhere)->update($authData);
        } catch ( \Exception $e ) {
            $message = "module=helper_state\tmethod=" . __METHOD__ . "\tparams=" . json_encode($params) . "\tmsg=" . $e->getMessage() . "\tfile=" . $e->getFile() . "\tline=" . $e->getLine();
            Log::warning( $message );
            return false;
        }
    }

    // 查询订单授权信息
    public static function getThirdResult ($orderId)
    {
        $orderData = Orders::find($orderId)->toArray();
        $result = CapitalClient::userAuthResult($orderData);

        $params = ['extra' => '查询失败', 'status' => Constant::COMMON_STATUS_FAILED];

        if ( $result && ( $result['code'] == 0 ) && ( $result['data']['status'] == 1 ) ) {
            $params = ['extra' => '查询成功', 'status' => Constant::COMMON_STATUS_SUCCESS];
        }

        return $params;
    }

}
