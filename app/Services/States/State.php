<?php
/**
 * Created by Vim.
 * User: liyang
 * Date: 2019-06-28
 * Time: 14:44
 */

namespace App\Services\States;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CustomException;
use App\Consts\ErrorCode;
use App\Consts\Constant;
use App\Consts\Procedure;
use App\Models\Procedures;
use App\Models\Users;
use App\Models\Orders;
use App\Models\OpenAccountRecords;
use App\Helpers\RepayCenter;
use App\Models\OrderInstallments;
use App\Common\Utils;
use App\Common\AsyncTaskClient;

abstract class State
{
    // 状态流转请求数据
    public $_params;
    // 状态机值
    public $_state;
    // 流程数据
    public $_procedure;
    // 关联记录
    public $_relation;

    // 状态流转处理逻辑
    public function run ()
    {
        return false;
    }

    // 状态回调处理逻辑
    public function callback ()
    {
        return false;
    }

    // 判断跳过状态
    public function autoSkip ()
    {
        return false;
    }

    // 获取SAMPLEID
    protected function getSampleId ($capitalLabel)
    {
        $sampleConfig = config('business.procedure.capital_shunt.' . $capitalLabel);

        return empty($sampleConfig) ? Procedure::SAMPLE_NORMAL : $sampleConfig;
    }

    // 获取MODEID
    protected function getModeId ($sampleId)
    {
        return Procedure::MODE_NORMAL;
        // 概率分配模式
        //$modeConfig = config('business.procedure.sample.' . $sampleId);
        //$modeRate = config($modeConfig . '.mode_rate');
        //$modeId = Utils::hitProbability($modeRate);
        //return empty( $modeId ) ? Procedure::MODE_NORMAL : $modeId;
    }

    // 获取下一个状态
    protected function getNextState ()
    {
        $stateFlowDefault = config('business.procedure.state_flow');
        $stateFlowShunt = [];
        if ( $this->_procedure->sample_id ) {
           $stateFlowShuntConfig = config('business.procedure.sample.' . $this->_procedure->sample_id);
           $stateFlowShunt = config($stateFlowShuntConfig . '.state_flow.' . $this->_procedure->mode_id);
        }
        $stateFlow = array_merge($stateFlowDefault, $stateFlowShunt);
        $stateIndex = array_search($this->_state, $stateFlow);

        return $stateFlow[$stateIndex+1];
    }
    
    // 回调结果成功，公共处理逻辑
    protected function manageCallbackSuccess () 
    {
        // 开启事务
        try {
            DB::transaction(function () {
                $nextState = $this->getNextState();
                // 状态流转操作
                $procedureData = [
                    'sub_status' => $nextState,
                ];
                if ( $nextState == Procedure::STATE_SUCCESS ) {
                    $procedureData['status'] = Constant::COMMON_STATUS_SUCCESS;
                }
                $procedureWhere = [
                    'id'            => $this->_procedure->id,
                    'sub_status'    => $this->_state,
                ];
                if ( ! Procedures::where($procedureWhere)->update($procedureData) ) {
                    throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                }

                if ( $nextState == Procedure::STATE_SUCCESS ) {
                    // 生成还款计划
                    $orderInfo = Orders::find($this->_procedure->order_id);
                    $installments = RepayCenter::genInstallments($orderInfo->amount, $orderInfo->periods, $orderInfo->interest_rate, date('Y-m-d', strtotime($this->_params['procedure_finish_date'])));
                    foreach ($installments as $key => $val) {
                        $val = array_merge(['user_id' => $this->_procedure->user_id, 'order_id' => $this->_procedure->order_id], $val);
                        if ( ! OrderInstallments::create($val) ) {
                            throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                        }
                    }
                }

                if ( $orderStatus = config('business.procedure.order_status.' . $nextState) ) {
                    // 订单记录
                    $orderData = [
                        'status'    => $orderStatus,
                    ];
                    if ( $nextState == Procedure::STATE_SUCCESS ) {
                        $orderData['procedure_finish_date'] = $this->_params['procedure_finish_date'];;
                    }
                    $orderWhere = [
                        'id'    => $this->_procedure->order_id,
                    ];
                    if ( ! Orders::where($orderWhere)->update($orderData) ) {
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

    // 回调结果失败，公共处理逻辑
    protected function manageCallbackFailed ($failedStatus, $frozen = -1)
    {
        // 开启事务
        try {
            DB::transaction(function () use ($failedStatus, $frozen) {
                // 状态流转操作
                $procedureData = [
                    'status'        => Constant::COMMON_STATUS_FAILED,
                    'sub_status'    => $failedStatus,
                ];
                $procedureWhere = [
                    'id'            => $this->_procedure->id,
                    'sub_status'    => $this->_state,
                ];
                if ( ! Procedures::where($procedureWhere)->update($procedureData) ) {
                    throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                }

                // 用户冻结
                if ( $frozen == -1 ) {
                    $frozen = (int)config('business.procedure.user_frozen.' . $this->_state);
                }
                if ( $frozen > 0 ) {
                    $userData = [
                        'frozen_status'     => $failedStatus,
                        'frozen_start_time' => date('Y-m-d H:i:s'),
                        'frozen_end_time'   => date('Y-m-d H:i:s', strtotime('+ ' . $frozen . ' days'))
                    ];
                    $userWhere = [
                        'id'        => $this->_procedure->user_id,
                    ];
                    if ( ! Users::where($userWhere)->update($userData) ) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
                    }
                }

                // 订单记录
                if ( ! empty($this->_procedure->order_id) ) {
                    // 订单记录
                    $orderData = [
                        'status'    => Constant::ORDER_STATUS_LOAN_FAILED,
                    ];
                    $orderWhere = [
                        'id'        => $this->_procedure->order_id,
                    ];
                    if ( ! Orders::where($orderWhere)->update($orderData) ) {
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
    
    // 设置LOGERROR
    protected function setLogWarning ($message)
    {
        $stateClass = config('business.procedure.class_map.' . $this->_state);
        Log::warning( "module=service_state\tstate=" . $stateClass . "\tmsg=" . $message . "\tparams=" . json_encode($this->_params)
            . "\tprocedure=" . json_encode($this->_procedure)
        );
    }

}
