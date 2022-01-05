<?php
/**
 * Created by Vim.
 * User: liyang
 * Date: 2019-06-28
 * Time: 14:44
 */

namespace App\Services\States;

use App\Common\AsyncTaskClient;
use App\Consts\Constant;
use App\Consts\SmsContent;
use App\Consts\Procedure;
use App\Models\OrderInstallments;
use App\Models\Users;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CustomException;
use App\Consts\ErrorCode;
use App\Models\Orders;

class WithdrawState extends State
{

    // 状态回调处理逻辑
    public function callback ()
    {
        // 开启事务
        try {
            DB::transaction(function () {
                if ( $this->_params['status'] == Constant::COMMON_STATUS_SUCCESS ) {
                    // 订单记录
                    $orderData = [
                        'withdrawed_date'   => date('Y-m-d H:i:s'),
                    ];
                    $orderWhere = [
                        'id'    => $this->_procedure->order_id,
                    ];
                    if ( ! Orders::where($orderWhere)->update($orderData) ) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                    }

                    // 状态流转操作
                    if ( ! parent::manageCallbackSuccess() ) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                    }
                } else {
                    // 状态流转操作
                    if ( ! parent::manageCallbackFailed( Procedure::STATE_WITHDRAW_FAILED ) ) {
                        return false;
                    }
                }
            });
        } catch ( \Exception $e ) {
            $this->setLogWarning($e->getMessage());
            return false;
        }

        $this->sendPushAndSms ($this->_params['status']);

        return true;
    }

    protected function sendPushAndSms ($result)
    {
        try {
            $userInfo = Users::find($this->_procedure->user_id)->toArray();
            if ( $result == Constant::COMMON_STATUS_SUCCESS ) {
                // 发送放款通过短信
                $installments = OrderInstallments::where('order_id', $this->_procedure->order_id)->where('period', 1)->first();
                $successSmsContent = sprintf(SmsContent::WITHDRAW_SUCCESS_FORMAT,
                    intval($this->_procedure->order_amount/100),
                    substr($userInfo['card_no'], -4),
                    sprintf('%.2f', ($installments->capital + $installments->interest)/100),
                    date('m', strtotime($installments->date)),
                    date('d', strtotime($installments->date))
                );
                AsyncTaskClient::sendSmsByPhone($userInfo['phone'], $successSmsContent);
                // 发送放款通过push
                $pushTitle = sprintf('水莲金条借款%d元', intval($this->_procedure->order_amount/100));
                $pushContent = sprintf('您在水莲金条的借款%d元，已经提现到您尾号%s的银行卡中，请查收！', intval($this->_procedure->order_amount/100), substr($userInfo['card_no'], -4));
                AsyncTaskClient::sendPushByUserId($userInfo['id'], $pushTitle, $pushContent);
            }
        } catch (\Exception $e) {
            $this->setLogWarning($e->getMessage());
        }
    }

}
