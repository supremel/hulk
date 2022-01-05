<?php
/**
 * 业务事件统一处理
 * User: hexuefei
 * Date: 2019-08-12
 * Time: 14:44
 */

namespace App\Services\HulkEvents\Contracts;

use App\Common\AlertClient;
use App\Common\ContractClient;
use App\Common\MnsClient;
use App\Consts\Constant;
use App\Consts\Contract;
use App\Consts\ErrorCode;
use App\Exceptions\CustomException;
use App\Models\BaseInfo;
use App\Models\Contracts;
use App\Models\OrderInstallments;
use App\Models\Orders;
use App\Models\Users;
use App\Services\HulkEventService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class GenPdfData
{
    public function handle($params)
    {
        try {
            Log::info("module=GenPdfData\tmsg=ongoing\tcontent=" . json_encode($params));

            // 整理数据
            $contractRelation = Contract::AGREEMENT_RELATION[$params['contractAgreement']];
            $contractSn = (string)date('YmdHis') . (string)$params['contractAgreement'] . (string)rand(1000, 9999);
            $params['contractTitle'] = $contractRelation['title'];
            $params['contractSn'] = $contractSn;
            $params['kwLotus'] = '88d4d7fc475660986824637ab54c0c263976dd4c';

            // 放款协议&关联订单
            if (($params['contractType'] == Contract::TYPE_LOAN) && ($params['relationType'] == Contract::RELATION_TYPE_ORDER)) {
                $orderInfo = Orders::find($params['relationId']);
                $userInfo = Users::find($orderInfo->user_id);
                $params['signDate'] = date('Y-m-d', strtotime($orderInfo->procedure_finish_date));
            }
            // 认证协议&关联用户
            if (($params['contractType'] == Contract::TYPE_AUTH) && ($params['relationType'] == Contract::RELATION_TYPE_USER)) {
                $userInfo = Users::find($params['relationId']);
                $params['signDate'] = date('Y-m-d', strtotime($params['authSuccessTime']));
            }
            // 代扣款协议&关联用户
            if (($params['contractType'] == Contract::TYPE_BANK) && ($params['relationType'] == Contract::RELATION_TYPE_USER)) {
                $userInfo = Users::find($params['relationId']);
                $params['signDate'] = date('Y-m-d', strtotime($params['bindSuccessTime']));
            }

            $params['loanName'] = $userInfo->name;
            $params['loanIdCard'] = $userInfo->identity;
            $params['loanMobile'] = $userInfo->phone;
            $params['loanCardNo'] = $userInfo->card_no;
            $params['kwBorrower'] = sha1($userInfo->uid . md5($userInfo->uid));

            $params['contractPdfSign'] = [];
            foreach ($contractRelation['sign'] as $oneSign) {
                if ($oneSign == Contract::SIGN_CA_USER) {
                    $params['contractPdfSign'][] = [
                        'type' => $oneSign,
                        'client_role' => 4,
                        'sign_keyword' => $params['kwBorrower'],
                        'customer_id' => $this->genUserCa($params),
                    ];
                } elseif ($oneSign == Contract::SIGN_CA_COMPANY) {
                    $params['contractPdfSign'][] = [
                        'type' => $oneSign,
                        'client_role' => 1,
                        'sign_keyword' => $params['kwLotus'],
                        'customer_id' => env('FDD_JIEJIEJIE_CA'),
                    ];
                }
            }

            // 判断用户是否已经生成该协议
            $contractInfo = Contracts::where('relation_id', $params['relationId'])
                ->where('relation_type', $params['relationType'])
                ->where('contract_type', $params['contractAgreement'])
                ->whereIn('status', [Constant::COMMON_STATUS_SUCCESS, Constant::COMMON_STATUS_INIT])
                ->first();

            if ($contractInfo && ($contractInfo->status == Constant::COMMON_STATUS_SUCCESS)) {
                return true;
            }

            $params['contractSn'] = $contractInfo ? $contractInfo->contract_sn : $params['contractSn'];

            if ($contractInfo && $contractInfo->original_pdf) {
                // PDF已经生成
                $params['contractStep'] = Contract::STEP_GEN_PDF_SIGN;
                $msg = [
                    'event' => HulkEventService::EVENT_TYPE_CONTRACT,
                    'params' => $params,
                ];
                MnsClient::sendMsg2Queue(env('HULK_EVENT_ACCESS_ID'), env('HULK_EVENT_ACCESS_KEY'), env('HULK_EVENT_QUEUE_NAME'), json_encode($msg));

                return true;
            } else {
                // PDF生成
                if (!$contractInfo) {
                    Contracts::create([
                        'contract_sn' => $params['contractSn'],
                        'relation_type' => $params['relationType'],
                        'relation_id' => $params['relationId'],
                        'contract_type' => $params['contractAgreement'],
                        'title' => $params['contractTitle'],
                    ]);
                }

                // 生成模版信息
                $pdfData = $this->genPdfDataByAgreement($params);

                $params['contractStep'] = Contract::STEP_GEN_PDF_FILE;
                $params['contractPdfData'] = $pdfData;

                $msg = [
                    'event' => HulkEventService::EVENT_TYPE_CONTRACT,
                    'params' => $params,
                ];
                MnsClient::sendMsg2Queue(env('HULK_EVENT_ACCESS_ID'), env('HULK_EVENT_ACCESS_KEY'), env('HULK_EVENT_QUEUE_NAME'), json_encode($msg));

                return true;
            }
        } catch (\Exception $e) {
            $message = "module=GenPdfData\tmethod=" . __METHOD__ . "\tparams=" . json_encode($params) . "\tmsg=" . $e->getMessage();
            AlertClient::sendAlertEmail($e);
            Log::warning($message);
            return false;
        }
    }

    protected function genPdfDataByAgreement($params)
    {
        $funcmap = [
            Contract::AGREEMENT_ENTRUST_GUARANTEE => 'genPdfDataByGuarantee',
            Contract::AGREEMENT_BORROWER_SERVICE => 'genPdfDataByBorrowerService',
            Contract::AGREEMENT_DATA_PARSING => 'genPdfDataByDataParsing',
            Contract::AGREEMENT_PERSONAL_INFORMATION => 'genPdfDataByPersonalInfo',
            Contract::AGREEMENT_DEDUCTION_PAYMENT => 'genPdfDataByDeductionPayment',
        ];

        $fun = $funcmap[$params['contractAgreement']];
        return $this->$fun($params);
    }

    protected function genPdfDataByGuarantee($params)
    {
        $result = [
            'contractSn' => $params['contractSn'],
            'loanName' => $params['loanName'],
            'kwBorrower' => $params['kwBorrower'],
            'loanIdCard' => $params['loanIdCard'],
            'loanMobile' => $params['loanMobile'],
            'signDate' => $params['signDate'],
        ];

        return $result;
    }

    protected function genPdfDataByBorrowerService($params)
    {
        $orderInfo = Orders::find($params['relationId']);
        $userBaseInfo = BaseInfo::where('user_id', $orderInfo->user_id)->first();
        $installments = OrderInstallments::where('order_id', $params['relationId'])->orderBy('period', 'asc')->get()->toArray();

        $plansPrincipalArr = array_column($params['plans'], 'principal', 'period');
        $plansInterestArr = array_column($params['plans'], 'interest', 'period');
        $plansFeeArr = array_column($params['plans'], 'fee', 'period');

        $aRepaymentsTotal = [
            'totalLenderPrincipal' => 0,
            'totalLenderInterest' => 0,
            'totalCServiceCharge' => 0,
            'totalBServiceCharge' => 0,
            'totalAll' => 0,
        ];

        foreach ($installments as $key => $record) {
            $planTotalByPeriod = $plansPrincipalArr[$record['period']] + $plansInterestArr[$record['period']] + $plansFeeArr[$record['period']];

            $installments[$key] = [
                'period' => $record['period'],
                'planDate' => $record['date'],
                'lenderPrincipal' => $plansPrincipalArr[$record['period']] * 100,
                'lenderInterest' => $plansInterestArr[$record['period']] * 100,
                'cServiceCharge' => $plansFeeArr[$record['period']] * 100,
                'bServiceCharge' => $record['capital'] + $record['interest'] - $planTotalByPeriod * 100,
                'total' => $record['capital'] + $record['interest'],
            ];

            $aRepaymentsTotal['totalLenderPrincipal'] += $installments[$key]['lenderPrincipal'];
            $aRepaymentsTotal['totalLenderInterest'] += $installments[$key]['lenderInterest'];
            $aRepaymentsTotal['totalCServiceCharge'] += $installments[$key]['cServiceCharge'];
            $aRepaymentsTotal['totalBServiceCharge'] += $installments[$key]['bServiceCharge'];
            $aRepaymentsTotal['totalAll'] += $installments[$key]['total'];

            if ($installments[$key]['bServiceCharge'] < 0) {
                throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '合同生成-资方还款计划服务费出现负数');
            }
        }

        $result = [
            'contractSn' => $params['contractSn'],
            'signDate' => $params['signDate'],
            'loanIdCard' => $params['loanIdCard'],
            'loanCardNo' => $params['loanCardNo'],
            'loanMobile' => $params['loanMobile'],
            'loanEmail' => $userBaseInfo ? $userBaseInfo->email : '',
            'loanAccountNo' => $params['accountId'],
            'principal' => bcdiv($aRepaymentsTotal['totalLenderPrincipal'], 100, 2),
            'periods' => count($installments),
            'loanDate' => $params['signDate'],
            'loanEndDate' => date('Y-m-d', strtotime($params['signDate'] . ' + ' . count($installments) . ' months')),
            'loanInterest' => bcdiv($aRepaymentsTotal['totalLenderInterest'], 100, 2),
            'bServiceCharge' => bcdiv($aRepaymentsTotal['totalBServiceCharge'], 100, 2),
            'cServiceCharge' => bcdiv($aRepaymentsTotal['totalCServiceCharge'], 100, 2),
            'aRepayments' => $installments,
            'aRepaymentsTotal' => $aRepaymentsTotal,
            'loanName' => $params['loanName'],
            'kwBorrower' => $params['kwBorrower'],
            'kwLotus' => $params['kwLotus'],
        ];

        return $result;
    }

    protected function genPdfDataByDataParsing($params)
    {
        $result = [
            'loanName' => $params['loanName'],
            'kwBorrower' => $params['kwBorrower'],
            'loanIdCard' => $params['loanIdCard'],
            'loanMobile' => $params['loanMobile'],
            'signDate' => $params['signDate'],
        ];

        return $result;
    }

    protected function genPdfDataByPersonalInfo($params)
    {
        $result = [
            'loanName' => $params['loanName'],
            'kwBorrower' => $params['kwBorrower'],
            'loanIdCard' => $params['loanIdCard'],
            'loanMobile' => $params['loanMobile'],
            'signDate' => $params['signDate'],
        ];

        return $result;
    }

    protected function genPdfDataByDeductionPayment($params)
    {
        $result = [
            'loanName' => $params['loanName'],
            'kwBorrower' => $params['kwBorrower'],
            'loanIdCard' => $params['loanIdCard'],
            'loanMobile' => $params['loanMobile'],
            'signDate' => $params['signDate'],
        ];

        return $result;
    }

    protected function genUserCa($params)
    {
        $redisKey = 'contract_user_ca_' . $params['loanIdCard'];
        $caInfo = Redis::get($redisKey);
        if (!$caInfo) {
            $caResult = ContractClient::genUserCa($params['loanName'], $params['loanIdCard'], $params['loanMobile']);
            $caInfo = $caResult['customer_id'];
            Redis::set($redisKey, $caInfo);
        }

        return $caInfo;
    }

}
