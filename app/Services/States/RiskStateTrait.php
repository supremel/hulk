<?php
/**
 * Created by Vim.
 * User: liyang
 * Date: 2019-06-28
 * Time: 14:44
 */

namespace App\Services\States;

use App\Common\AsyncTaskClient;
use App\Common\LegacyClient;
use App\Common\MnsClient;
use App\Common\Utils;
use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Consts\Procedure;
use App\Consts\SmsContent;
use App\Exceptions\CustomException;
use App\Helpers\UserHelper;
use App\Models\IdCard;
use App\Models\Orders;
use App\Models\Procedures;
use App\Models\RiskEvaluations;
use App\Models\Users;
use App\Services\HulkEventService;
use App\Services\States\Helpers\RiskHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait RiskStateTrait
{
    // 状态流转处理逻辑
    public function run()
    {
        // 生成BIZ_NO
        $this->_relation['biz_no'] = Utils::genBizNo();

        // 优先写入记录数据
        if (!$this->preInit()) {
            return false;
        }

        // 请求风控MNS
        if (!$this->requestRiskMns()) {
            return false;
        }

        // 记录已提交
        if (!RiskEvaluations::where(['id' => $this->_relation['id']])->update(['is_submit' => Procedure::SUBMIT_OK])) {
            return false;
        }

        // 请求事件MNS
        $this->requestEventMns();

        return true;
    }

    // 优先写入记录数据
    protected function preInit()
    {
        $idCard = IdCard::where('user_id', $this->_procedure->user_id)->where('status', Constant::AUTH_STATUS_SUCCESS)->first();
        if (!$idCard || $idCard['identity'] == '') {
            return false;
        }
        // 查看是否存在已提交记录
        $searchWhere = [
            'user_id' => $this->_procedure->user_id,
            'procedure_id' => $this->_procedure->id,
            'status' => Constant::COMMON_STATUS_INIT,
            'num' => $this->_risk_num,
        ];
        $riskInfo = RiskEvaluations::where($searchWhere)->first();
        if ($riskInfo && ($riskInfo->is_submit == Procedure::SUBMIT_OK)) {
            return false;
        } elseif ($riskInfo && ($riskInfo->is_submit == Procedure::SUBMIT_NO)) {
            $this->_relation = $riskInfo->toArray();
            return true;
        }

        try {
            // 审核记录
            $riskData = [
                'biz_no' => $this->_relation['biz_no'],
                'user_id' => $this->_procedure->user_id,
                'procedure_id' => $this->_procedure->id,
                'num' => $this->_risk_num,
                'trigger_type' => Constant::RISK_TRIGGER_TYPE_USER,
                'status' => Constant::COMMON_STATUS_INIT,
                'request_time' => date('Y-m-d H:i:s'),
            ];
            if (!$this->_relation = RiskEvaluations::create($riskData)->toArray()) {
                throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
            }
        } catch (\Exception $e) {
            $this->setLogWarning($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * 计算用户标签，默认为0，1：有过结清订单且无在贷
     * @param $userId
     * @return int
     * @throws CustomException
     */
    protected function calcUserTag($userId)
    {
        $tag = 0;
        do {
            if (Orders::where('user_id', $userId)->where('status', Constant::ORDER_STATUS_ONGOING)->exists()) { // 有app在贷订单
                break;
            }
            $user = Users::where('id', $userId)->first();
            $apiOrderInfo = LegacyClient::apiOrderInfo($user['phone']);
            if ($apiOrderInfo['hasAPIOrder']) { // 有api在贷订单
                break;
            }
            if (Orders::where('user_id', $userId)->where('status', Constant::ORDER_STATUS_PAID_OFF)->exists()) { // 有app结清订单
                $tag = 1;
                break;
            }
            if ($apiOrderInfo['hasPaidOrder']) { // 有api结清订单
                $tag = 1;
                break;
            }

        } while (false);

        return $tag;
    }

    // 请求风控MNS
    protected function requestRiskMns()
    {
        try {
            $tag = $this->calcUserTag($this->_procedure->user_id);
        } catch (\Exception $e) {
            Log::warning("module=request_risk_mns\terror=计算用户标签错误");
            return false;
        }

        $userData = UserHelper::getUserData($this->_procedure->user_id);
        $orderInfo = $this->_procedure->order_id ? Orders::find($this->_procedure->order_id) : '';
        $mnsData = [
            'application_id' => $this->_relation['biz_no'],
            'order_id' => $orderInfo ? $orderInfo->biz_no : '',
            'source' => $this->_risk_source,
            'old_user_id' => $userData['old_user_id'],
            'uid' => $userData['uid'],
            'product' => Constant::PRODUCT_TYPE_LOTUS,
            'channel' => Constant::USER_RISK_SOURCE_DICT[$this->_procedure->source],
            'name' => $userData['name'],
            'phone' => $userData['phone'],
            'identity' => $userData['identity'],
            'bank_card_num' => $userData['card_no'],
            'created_time' => strtotime($this->_relation['request_time']),
            'user_tag' => $tag,
        ];
        $mnsResult = MnsClient::sendMsg2Queue(env('RISK_ACCESS_ID'), env('RISK_ACCESS_KEY'), env('RISK_EVALUATION_REQUEST_QUEUE_NAME'), json_encode($mnsData));

        return $mnsResult;
    }

    // 请求事件MNS
    protected function requestEventMns()
    {
        $mnsData = [
            'event' => HulkEventService::EVENT_TYPE_RISK_EVALUATION_CREATION,
            'params' => $this->_relation['id'],
        ];
        MnsClient::sendMsg2Queue(env('HULK_EVENT_ACCESS_ID'), env('HULK_EVENT_ACCESS_KEY'), env('HULK_EVENT_QUEUE_NAME'), json_encode($mnsData));

        return true;
    }

    // 状态回调处理逻辑
    public function callback()
    {
        // 开启事务
        try {
            DB::transaction(function () {
                // 优先写入回调记录数据
                if (!$this->callbackPre()) {
                    throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                }

                if ($this->_params['status'] == Constant::COMMON_STATUS_SUCCESS) {
                    // 状态流转操作
                    if (!parent::manageCallbackSuccess()) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                    }
                } else {
                    // 重置认证失效
                    if (!UserHelper::resetUserAuth($this->_procedure->user_id, explode(',', $this->_params['unvalid_list']))) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                    }

                    // 状态流转操作
                    $subStateFailed = ($this->_risk_num == Procedure::RISK_FIRST) ? Procedure::STATE_FIRST_RISK_FAILED : Procedure::STATE_SECOND_RISK_FAILED;
                    if (!parent::manageCallbackFailed($subStateFailed, $this->_params['freeze'])) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                    }
                }
            });
        } catch (\Exception $e) {
            $this->setLogWarning($e->getMessage());
            return false;
        }

        $this->sendPushAndSms($this->_params['status']);

        return true;
    }

    // 优先写入回调记录数据
    protected function callbackPre()
    {
        // 开启事务
        try {
            DB::transaction(function () {
                // 审核记录
                if (!$riskData = RiskHelper::updateRecord($this->_relation, $this->_params)) {
                    throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                }

                if (($this->_risk_source == Procedure::RISK_QUOTA) && ($this->_params['status'] == Constant::COMMON_STATUS_SUCCESS)) {
                    // 流程记录
                    $procedureData = [
                        'authed_amount' => $riskData['amount'],
                        'authed_min_amount' => $riskData['min_amount'],
                        'authed_step_amount' => $riskData['step_amount'],
                        'authed_valid_days' => $riskData['valid_days'],
                        'authed_periods' => $riskData['cate'],
                        'authed_fee_rate' => $riskData['fee_rate'],
                        'authed_repay_type' => $riskData['repay_type'],
                        'authed_fee_type' => $riskData['fee_type'],
                    ];
                    $procedureWhere = [
                        'id' => $this->_procedure->id,
                        'sub_status' => $this->_state,
                    ];
                    if (!Procedures::where($procedureWhere)->update($procedureData)) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                    }

                    // 用户记录
                    $userData = [
                        'authed_amount' => $riskData['amount'],
                    ];
                    $userWhere = [
                        'id' => $this->_procedure->user_id,
                    ];
                    if (!Users::where($userWhere)->update($userData)) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                    }
                } elseif (($this->_risk_source == Procedure::RISK_QUOTA) && ($this->_params['status'] == Constant::COMMON_STATUS_FAILED)) {
                    // 用户记录
                    $userData = [
                        'authed_amount' => 0,
                    ];
                    $userWhere = [
                        'id' => $this->_procedure->user_id,
                    ];
                    if (!Users::where($userWhere)->update($userData)) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                    }
                }
            });
        } catch (\Exception $e) {
            $this->setLogWarning($e->getMessage());
            return false;
        }

        return true;
    }

    protected function sendPushAndSms($result)
    {
        try {
            $userInfo = Users::find($this->_procedure->user_id)->toArray();
            if ($this->_risk_num == Procedure::RISK_FIRST) {
                if ($result == Constant::COMMON_STATUS_SUCCESS) {
                    // 发送授信通过短信
                    AsyncTaskClient::sendSmsByPhone($userInfo['phone'], sprintf(SmsContent::RISK_EVALUATION_RECEPTION_FORMAT, intval($this->_params['amount'] / 100)));
                    // 发送授信通过push
                    $pushTitle = sprintf('获得%d授信额度', intval($this->_params['amount'] / 100));
                    $pushContent = sprintf('恭喜您获得%d元的授信额度，马上来借款吧！', intval($this->_params['amount'] / 100));
                    AsyncTaskClient::sendPushByUserId($userInfo['id'], $pushTitle, $pushContent);
                    // 发送开户延时短信[T+1-T+7]
                    $openAccountSmsContent = sprintf(SmsContent::OPEN_ACCOUNT_REMIND_FORMAT, intval($this->_params['amount'] / 100));
                    foreach (range(1, 7) as $days) {
                        $delay = strtotime(date('Y-m-d 10:00:00', strtotime('+ ' . $days . ' days'))) - time();
                        AsyncTaskClient::sendStateSmsByPhone($userInfo['phone'], $openAccountSmsContent, $this->_procedure->id, Procedure::STATE_OPEN_ACCOUNT, $delay);
                    }
                } else {
                    // 发送授信拒绝短信
                    AsyncTaskClient::sendSmsByPhone($userInfo['phone'], SmsContent::RISK_EVALUATION_REFUSE);
                }
            } elseif ($this->_risk_num == Procedure::RISK_SECOND) {
                if ($result == Constant::COMMON_STATUS_FAILED) {
                    // 发送授信拒绝短信
                    AsyncTaskClient::sendSmsByPhone($userInfo['phone'], SmsContent::BORROW_ORDER_REFUSE);
                }
            }
        } catch (\Exception $e) {
            $this->setLogWarning($e->getMessage());
        }
    }

}
