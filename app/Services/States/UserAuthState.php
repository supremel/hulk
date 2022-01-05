<?php
/**
 * Created by Vim.
 * User: liyang
 * Date: 2019-06-28
 * Time: 14:44
 */

namespace App\Services\States;

use App\Exceptions\CustomException;
use App\Consts\ErrorCode;
use App\Consts\Procedure;
use App\Common\Utils;
use App\Consts\Constant;
use Illuminate\Support\Facades\DB;
use App\Models\Procedures;
use App\Models\AuthRecords;
use App\Models\Orders;
use App\Common\CapitalClient;
use App\Services\States\Helpers\UserAuthHelper;

class UserAuthState extends State
{

    // 状态流转处理逻辑
    public function run ()
    {
        // 授权前预置判断
        if ( ! $this->preInit() ) {
            return [
                'msg' => '系统异常',
            ];
        }

        // 生成BIZ_NO
        $this->_relation['biz_no'] = Utils::genBizNo();

        // 请求提现授权H5页面
        $orderData = Orders::find($this->_procedure->order_id)->toArray();
        $callBackUrl = env('APP_URL') . '/v1/procedure/loan_verify_callback?biz_no=' . $this->_relation['biz_no'];
        $authH5Result = CapitalClient::userAuth($this->_relation['biz_no'], $orderData, $callBackUrl);

        if ( empty( $authH5Result ) ) {
            return [
                'msg' => '资方返回异常',
            ];
        }
        if ( $authH5Result['code'] != 0 ) {
            if ( $authH5Result['code'] == Procedure::USER_AUTH_EXPIRE_CODE ) {
                // 资方72小时超时失效
                parent::manageCallbackFailed( Procedure::STATE_USER_AUTH_FAILED, 0 );
            }
            return [
                'msg' => $authH5Result['msg'],
            ];
        }

        if ( ! $this->authInit() ) {
            return [
                'msg' => '系统异常',
            ];
        }

        return [
            'url' => $authH5Result['data']['walk_url'],
            'biz_no' => $this->_relation['biz_no'],
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
            'type'          => Procedure::AUTH_TYPE_LOAN,
            'status'        => Constant::COMMON_STATUS_INIT,
            'is_submit'     => Procedure::SUBMIT_OK,
        ];
        if ( AuthRecords::where($searchWhere)->first() ) {
            return false;
        } 

        return true;
    }

    // 状态流成功后续处理逻辑
    protected function authInit ()
    {
        try {
            // 授权记录
            $authData = [
                'biz_no'            => $this->_relation['biz_no'],
                'user_id'           => $this->_procedure->user_id,
                'procedure_id'      => $this->_procedure->id,
                'order_id'          => $this->_procedure->order_id,
                'capital_label'     => $this->_procedure->capital_label,
                'type'              => Procedure::AUTH_TYPE_LOAN,
                'status'            => Constant::COMMON_STATUS_INIT,
                'request_time'      => date('Y-m-d H:i:s'),
            ];
            if ( ! AuthRecords::create($authData) ) {
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
                $this->_params = UserAuthHelper::getThirdResult($this->_procedure->order_id);
            }

            DB::transaction(function () {
                // 授权记录
                if ( ! UserAuthHelper::updateRecord($this->_relation, $this->_params) ) {
                    throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                }

                if ( $this->_params['status'] == Constant::COMMON_STATUS_SUCCESS ) {
                    // 状态流转操作
                    if ( ! parent::manageCallbackSuccess() ) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                    }
                }
            });
        } catch ( \Exception $e ) {
            $this->setLogWarning($e->getMessage());
            return false;
        }

        return true;
    }

}
