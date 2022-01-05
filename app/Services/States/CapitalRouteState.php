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
use App\Consts\Constant;
use App\Consts\Procedure;
use Illuminate\Support\Facades\DB;
use App\Models\Procedures;
use App\Models\CapitalRouteRecords;
use App\Common\CapitalClient;
use App\Helpers\UserHelper;
use App\Common\Utils;

class CapitalRouteState extends State
{

    // 状态流转处理逻辑
    public function run ()
    {
        // 生成BIZ_NO
        $this->_relation['biz_no'] = Utils::genBizNo();

        // 请求资金路由接口
        $userData = UserHelper::getUserData($this->_procedure->user_id);
        $capitalResult = CapitalClient::preRoute($this->_relation['biz_no'], $this->_procedure->source, $userData);

        if ( $capitalResult && ( $capitalResult['code'] == 0 ) ) {
            $this->_params['capital_label'] = $capitalResult['data']['vendor'];
            $this->_params['need_open_account'] = ($capitalResult['data']['vendorType'] == 1)
                ? Procedure::NONEED_OPEN_ACCOUNT : Procedure::NEED_OPEN_ACCOUNT;

            return $this->runSuccess();
        }

        return false;
    }

    // 状态流成功后续处理逻辑
    protected function runSuccess ()
    {
        $sampleId = $this->getSampleId( $this->_params['capital_label'] );
        $modeId = $this->getModeId( $sampleId );

        // 开启事务
        try {
            DB::transaction(function () use ($sampleId, $modeId) {
                // 资金路由记录
                $capitalData = [
                    'biz_no'            => $this->_relation['biz_no'],
                    'user_id'           => $this->_procedure->user_id,
                    'procedure_id'      => $this->_procedure->id,
                    'label'             => $this->_params['capital_label'],
                    'need_open_account' => $this->_params['need_open_account'],
                ];
                if ( ! CapitalRouteRecords::create($capitalData) ) {
                    throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                }
                
                // 流程记录
                $procedureData = [
                    'capital_label'     => $this->_params['capital_label'],
                    'need_open_account' => $this->_params['need_open_account'],
                    'sample_id'         => $sampleId,
                    'mode_id'           => $modeId,
                ];
                $procedureWhere = [
                    'id'            => $this->_procedure->id,
                    'sub_status'    => $this->_state,
                ];
                if ( ! Procedures::where($procedureWhere)->update($procedureData) ) {
                    throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                }
                $this->_procedure->sample_id = $sampleId;
                $this->_procedure->mode_id = $modeId;

                // 状态流转操作
                if ( empty( $this->_params['capital_label'] ) ) {
                    if ( ! parent::manageCallbackFailed( Procedure::STATE_CAPITAL_ROUTE_FAILED ) ) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                    }                    
                } elseif ( ! parent::manageCallbackSuccess() ) {
                    throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                }
            });
        } catch ( \Exception $e ) {
            $this->setLogWarning($e->getMessage());
            return false;
        }

        return true;
    }

}
