<?php
/**
 * Created by Vim.
 * User: liyang
 * Date: 2019-06-28
 * Time: 14:44
 */

namespace App\Services\States;

use App\Common\AsyncTaskClient;
use App\Consts\SmsContent;
use App\Exceptions\CustomException;
use App\Consts\ErrorCode;
use App\Common\Utils;
use App\Consts\Constant;
use App\Consts\Procedure;
use App\Models\Users;
use Illuminate\Support\Facades\DB;
use App\Models\Procedures;
use App\Models\OrderPushRecords;
use App\Models\Orders;
use App\Common\CapitalClient;
use App\Helpers\UserHelper;
use App\Services\States\Helpers\OrderPushHelper;

class OrderPushState extends State
{
    // 状态流转处理逻辑
    public function run ()
    {
        // 生成BIZ_NO
        $this->_relation['biz_no'] = Utils::genBizNo();
        
        // 优先写入记录数据
        if ( ! $this->preInit() ) {
            return false;
        }
        
        // 请求进件
        $userData = UserHelper::getUserData($this->_procedure->user_id);
        $orderData = Orders::find($this->_procedure->order_id)->toArray();
        $orderPushResult = CapitalClient::orderPush($this->_relation['biz_no'], $userData, $orderData);

        if ( empty( $orderPushResult ) ) {
            return false;
        }
        if ( $orderPushResult['code'] == Procedure::ORDER_PUSH_RISK_FAILED_CODE ) {
            // 资方风控失败
            return parent::manageCallbackFailed( Procedure::STATE_ORDER_PUSH_FAILED );
        }
        if ( $orderPushResult['code'] != 0 ) {
            return false;
        }

        // 记录已提交
        if( ! OrderPushRecords::where(['id' => $this->_relation['id']])->update(['is_submit' => Procedure::SUBMIT_OK]) ) {
            return false;
        }

        return true;
    }

    // 优先写入记录数据
    protected function preInit ()
    {
        // 查看是否存在已提交记录
        $searchWhere = [
            'user_id'       => $this->_procedure->user_id,
            'procedure_id'  => $this->_procedure->id,
            'status'        => Constant::COMMON_STATUS_INIT,
        ];
        $orderPushInfo = OrderPushRecords::where($searchWhere)->first();
        if ( $orderPushInfo && ($orderPushInfo->is_submit == Procedure::SUBMIT_OK) ) {
            return false;
        } elseif ( $orderPushInfo && ($orderPushInfo->is_submit == Procedure::SUBMIT_NO) ) {
            $this->_relation = $orderPushInfo->toArray();
            return true;
        }

        try {
            // 进件记录
            $orderPushData = [
                'biz_no'            => $this->_relation['biz_no'],
                'user_id'           => $this->_procedure->user_id,
                'procedure_id'      => $this->_procedure->id,
                'order_id'          => $this->_procedure->order_id,
                'capital_label'     => $this->_procedure->capital_label,
                'status'            => Constant::COMMON_STATUS_INIT,
                'request_time'      => date('Y-m-d H:i:s'),
            ];
            if ( ! $this->_relation = OrderPushRecords::create($orderPushData)->toArray() ) {
                throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
            }
        } catch ( \Exception $e ) {
            $this->setLogWarning($e->getMessage());
            return false;
        }

        return true;
    }

    // 状态回调处理逻辑
    public function callback ()
    {
        // 开启事务
        try {
            if ( $this->_params['status'] == Constant::COMMON_STATUS_FAILED ) {
                $this->_params = OrderPushHelper::getThirdResult($this->_procedure->order_id);
            }

            DB::transaction(function () {
                // 进件记录
                if ( ! OrderPushHelper::updateRecord($this->_relation, $this->_params) ) {
                    throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                }

                if ( $this->_params['status'] == Constant::COMMON_STATUS_SUCCESS ) {
                    // 状态流转操作
                    if ( ! parent::manageCallbackSuccess() ) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                    }
                } else {
                    // 状态流转操作
                    if ( ! parent::manageCallbackFailed( Procedure::STATE_ORDER_PUSH_FAILED ) ) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
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
                // 发送进件通过短信
                AsyncTaskClient::sendSmsByPhone($userInfo['phone'], sprintf(SmsContent::BORROW_ORDER_RECEPTION_FORMAT, intval($this->_procedure->order_amount/100)));
                // 发送放款验证提醒push
                $authPushTitle = '请进行放款验证';
                $authPushContent = '亲，为了保证您的借款能及时到账，请30分钟内进行放款验证！';
                AsyncTaskClient::sendPushByUserId($userInfo['id'], $authPushTitle, $authPushContent);
                // 发送放款验证提醒短信
                $authSmsContent = sprintf(SmsContent::WITHDRAW_REMIND_CURRENT_FORMAT, intval($this->_procedure->order_amount/100), substr($userInfo['card_no'], -4));
                AsyncTaskClient::sendStateSmsByPhone($userInfo['phone'], $authSmsContent, $this->_procedure->id, Procedure::STATE_USER_AUTH, 600);
                // 发送放款验证延时短信[T+1-T+3]
                $authSmsContent = sprintf(SmsContent::WITHDRAW_REMIND_FORMAT, intval($this->_procedure->order_amount/100), substr($userInfo['card_no'], -4));
                foreach ( range(1, 3) as $days ) {
                    $delay = strtotime( date('Y-m-d 10:00:00', strtotime('+ ' . $days . ' days')) ) - time();
                    AsyncTaskClient::sendStateSmsByPhone($userInfo['phone'], $authSmsContent, $this->_procedure->id, Procedure::STATE_USER_AUTH, $delay);
                }
            } else {
                // 发送进件拒绝短信
                AsyncTaskClient::sendSmsByPhone($userInfo['phone'], SmsContent::BORROW_ORDER_REFUSE);
            }
        } catch (\Exception $e) {
            $this->setLogWarning($e->getMessage());
        }
    }

}
