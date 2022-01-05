<?php
/**
 * Created by Vim.
 * User: liyang
 * Date: 2019-06-28
 * Time: 14:44
 */

namespace App\Services\States;

use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Consts\Procedure;
use App\Exceptions\CustomException;
use App\Models\Orders;
use Illuminate\Support\Facades\DB;

class LoanState extends State
{

    // 状态回调处理逻辑
    public function callback()
    {
        // 开启事务
        try {
            DB::transaction(function () {
                if ($this->_params['status'] == Constant::COMMON_STATUS_SUCCESS) {
                    // 订单记录
                    $orderData = [
                        'loaned_date' => date('Y-m-d H:i:s'),
                    ];
                    $orderWhere = [
                        'id' => $this->_procedure->order_id,
                    ];
                    if (!Orders::where($orderWhere)->update($orderData)) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                    }

                    // 状态流转操作
                    if (!parent::manageCallbackSuccess()) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                    }
                } else {
                    // 状态流转操作
                    if (!parent::manageCallbackFailed(Procedure::STATE_LOAN_FAILED)) {
                        return false;
                    }
                }
            });
        } catch (\Exception $e) {
            $this->setLogWarning($e->getMessage());
            return false;
        }

        return true;
    }

}
