<?php
/**
 * Created by Vim.
 * User: liyang
 * Date: 2019-06-28
 * Time: 14:44
 */

namespace App\Services\States;

use App\Consts\Procedure;

class SecondRiskState extends State
{
    use RiskStateTrait;

    protected $_risk_num = Procedure::RISK_SECOND;

    protected $_risk_source = Procedure::RISK_ORDER;

}
