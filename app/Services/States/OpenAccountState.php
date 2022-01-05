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
use Illuminate\Support\Facades\DB;
use App\Models\Procedures;
use App\Models\OpenAccountRecords;
use App\Models\Users;
use App\Common\CapitalClient;
use App\Helpers\UserHelper;
use App\Services\States\Helpers\OpenAccountHelper;

class OpenAccountState extends State
{

    // 判断跳过状态
    public function autoSkip ()
    {
        $skipFlag = false;

        if ( $this->_procedure->need_open_account == Procedure::NONEED_OPEN_ACCOUNT ) {
            $skipFlag = true;
        } elseif ( OpenAccountRecords::where([
                'user_id' => $this->_procedure->user_id,
                'status' => Constant::COMMON_STATUS_SUCCESS,
                'capital_label' => $this->_procedure->capital_label,
            ])->first() ) {
            $skipFlag = true;
        }

        if ( $skipFlag && parent::manageCallbackSuccess() ) {
            return true;
        }

        return false;
    }

    /**
     * 状态流转处理逻辑
     *  */
    public function run ()
    {
        // 开户前预置判断
        if ( ! $this->preInit() ) {
            return [
                'msg' => '系统异常',
            ];
        }

        // 生成BIZ_NO
        $this->_relation['biz_no'] = Utils::genBizNo();
        
        // 请求开户H5页面
        $userData = UserHelper::getUserData($this->_procedure->user_id);
        $callBackUrl = env('APP_URL') . '/v1/procedure/open_account_callback?biz_no=' . $this->_relation['biz_no'];
        $accountH5Result = CapitalClient::openAccount($this->_relation['biz_no'], $userData, $callBackUrl);

        if ( $accountH5Result && ( $accountH5Result['code'] == 200 ) && ( $accountH5Result['data']['tranState'] == 'SUCCESS' ) ) {
            if ( ! $this->accountInit() ) {
                return [
                    'msg' => '系统异常',
                ];
            }

            return [
                'url' => $accountH5Result['data']['url'],
                'biz_no' => $this->_relation['biz_no'],
            ];
        } else {
            $this->_params = OpenAccountHelper::getThirdResult($this->_procedure->user_id, $this->_procedure->capital_label);
            if ( $this->_params['status'] == Constant::COMMON_STATUS_SUCCESS ) {
                if ( $this->accountInit() && OpenAccountHelper::updateRecord($this->_relation, $this->_params) && parent::manageCallbackSuccess() ) {
                    return [
                        'msg' => '开户成功',
                    ];
                }
            }
        }

        return [
            'msg' => $accountH5Result['data']['resMsg'] ?? '资方返回异常',
        ];
    }

    /**
     * 状态流转检测
     *  */ 
    protected function preInit ()
    {
        // 查看是否存在已提交记录
        $searchWhere = [
            'user_id'       => $this->_procedure->user_id,
            'procedure_id'  => $this->_procedure->id,
            'status'        => Constant::COMMON_STATUS_INIT,
            'is_submit'     => Procedure::SUBMIT_OK,
        ];
        if ( OpenAccountRecords::where($searchWhere)->first() ) {
            return false;
        } 

        return true;
    }

    /**
     * 初始化开户记录
     *  */
    protected function accountInit ()
    {
        try {
            // 开户记录
            $accountData = [
                'biz_no'            => $this->_relation['biz_no'],
                'user_id'           => $this->_procedure->user_id,
                'procedure_id'      => $this->_procedure->id,
                'capital_label'     => $this->_procedure->capital_label,
                'status'            => Constant::COMMON_STATUS_INIT,
                'request_time'      => date('Y-m-d H:i:s'),
            ];
            if ( ! $this->_relation = OpenAccountRecords::create($accountData)->toArray() ) {
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
        try {
            if ( $this->_params['status'] == Constant::COMMON_STATUS_FAILED ) {
                $this->_params = OpenAccountHelper::getThirdResult($this->_procedure->user_id, $this->_procedure->capital_label);
            }

            DB::transaction(function () {
                // 开户记录
                if ( ! OpenAccountHelper::updateRecord($this->_relation, $this->_params) ) {
                    throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                }

                if ( $this->_params['status'] == Constant::COMMON_STATUS_SUCCESS ) {
                    // 状态流转操作
                    if ( ! parent::manageCallbackSuccess() ) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                    }
                } else {
                    // 状态流转操作
                    $procedureData = [
                        'sub_status'    => Procedure::STATE_CAPITAL_ROUTE,
                    ];
                    $procedureWhere = [
                        'id'            => $this->_procedure->id,
                        'sub_status'    => $this->_state,
                    ];
                    if ( ! Procedures::where($procedureWhere)->update($procedureData) ) {
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
                // 发送开户通过短信
                AsyncTaskClient::sendSmsByPhone($userInfo['phone'], SmsContent::OPEN_ACCOUNT_SUCCESS_FORMAT);
                // 发送开户通过push
                $pushTitle = '开户成功';
                $pushContent = '恭喜您开户成功，马上来借款吧！';
                AsyncTaskClient::sendPushByUserId($userInfo['id'], $pushTitle, $pushContent);
            } else {
                // 发送开户拒绝短信
                AsyncTaskClient::sendSmsByPhone($userInfo['phone'], SmsContent::OPEN_ACCOUNT_FAIL);
            }
        } catch (\Exception $e) {
            $this->setLogWarning($e->getMessage());
        }
    }

}
