<?php
/**
 * Created by Vim.
 * User: liyang
 * Date: 2019-06-28
 * Time: 14:44
 */

namespace App\Services;

use App\Models\Procedures;
use App\Consts\Constant;

class ProcedureService
{
    // 实例化状态机方法
    protected $_state;

    public function __construct (int $procedureId)
    {
        $this->initState($procedureId);

        return true;
    }

    // 初始化状态机
    protected function initState ($procedureId) 
    {
        $procedureInfo = Procedures::where('id', $procedureId)->where('status', Constant::COMMON_STATUS_INIT)->first();
        if ( empty($procedureInfo) ) {
            return false;
        }
        // 根据配置文件获取需要实例化的类
        $classMap = config('business.procedure.class_map');
        if ( empty($classMap[$procedureInfo->sub_status]) || !class_exists($classMap[$procedureInfo->sub_status]) ) {
            return false;
        }
        $this->_state = new $classMap[$procedureInfo->sub_status] ();
        $this->_state->_state       = $procedureInfo->sub_status;
        $this->_state->_procedure   = $procedureInfo;

        // 判断跳过状态
        if ( $this->_state->autoSkip() ) {
            return $this->initState($procedureId);
        }
        
        return true;
    }

    // 运行状态机
    public function runState ($params = [], $relation = [])
    {
        if ( empty($this->_state) ) {
            return false;
        }
        $this->_state->_params = $params;
        $this->_state->_relation = $relation;
        if ( $result = $this->_state->run() ) {
            $this->initState( $this->_state->_procedure->id );
        }

        return $result;
    }

    // 回调状态机
    public function callbackState ($params = [], $relation = [])
    {
        if ( empty($this->_state) ) {
            return false;
        }
        $this->_state->_params = $params;
        $this->_state->_relation = $relation;
        if ( $result = $this->_state->callback() ) {
            $this->initState( $this->_state->_procedure->id );
        }

        return $result;
    }

    // 获取流程当前状态
    public function getState ()
    {
        if ( empty($this->_state) ) {
            return false;
        }
        return $this->_state->_state;
    }

    // 获取流程当前用户
    public function getUser ()
    {
        if ( empty($this->_state) ) {
            return false;
        }
        return $this->_state->_procedure->user_id;
    }

    // 获取流程当前资方
    public function getCapital ()
    {
        if ( empty($this->_state) ) {
            return false;
        }
        return $this->_state->_procedure->capital_label;
    }

    // 获取流程订单ID
    public function getOrderId ()
    {
        if ( empty($this->_state) ) {
            return false;
        }
        return $this->_state->_procedure->order_id;
    }

}
