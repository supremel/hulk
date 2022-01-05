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
use App\Common\OssClient;
use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Exceptions\CustomException;
use App\Models\Contracts;
use Illuminate\Support\Facades\Log;

class GenPdfSign
{
    public function handle($params)
    {
        try {
            Log::info("module=GenPdfSign\tmsg=ongoing\tcontent=" . json_encode($params));
            // 判断用户是否已经生成该协议
            $contractInfo = Contracts::where('relation_id', $params['relationId'])
                ->where('relation_type', $params['relationType'])
                ->where('contract_type', $params['contractAgreement'])
                ->whereIn('status', [Constant::COMMON_STATUS_SUCCESS, Constant::COMMON_STATUS_INIT])
                ->first();

            if ($contractInfo && ($contractInfo->status == Constant::COMMON_STATUS_SUCCESS)) {
                return true;
            }

            if ($contractInfo && $contractInfo->sign_pdf) {
                return true;
            } else {
                // PDF签章
                if ( $this->uploadPdf($params, $contractInfo->original_pdf) ) {
                    // 更新数据
                    $contractData = [
                        'is_upload' => 1,
                    ];
                    $contractWhere = [
                        'relation_id' => $params['relationId'],
                        'relation_type' => $params['relationType'],
                        'contract_type' => $params['contractAgreement'],
                        'contract_sn' => $params['contractSn'],
                    ];
                    if (!Contracts::where($contractWhere)->update($contractData)) {
                        throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '合同生成-上传合同数据更新失败');
                    }
                }

                $signResult = $this->signPdf($params);

                // 上传OSS
                $signPdf = file_get_contents($signResult['download_url']);
                $fileName = $params['contractSn'] . '.sign.contract.pdf';
                if (!$fileName = OssClient::upload(Constant::FILE_TYPE_CONTRACT, $fileName, $signPdf)) {
                    throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '合同生成-签署合同上传OSS失败');
                }

                // 更新数据
                $contractData = [
                    'sign_pdf' => $fileName,
                    'h5_view_url' => $signResult['viewpdf_url'],
                    'status' => Constant::COMMON_STATUS_SUCCESS,
                ];
                $contractWhere = [
                    'relation_id' => $params['relationId'],
                    'relation_type' => $params['relationType'],
                    'contract_type' => $params['contractAgreement'],
                    'contract_sn' => $params['contractSn'],
                ];
                if (!Contracts::where($contractWhere)->update($contractData)) {
                    throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '合同生成-签署合同数据更新失败');
                }

                // PDF归档
                try {
                    ContractClient::filingDoc($params['contractSn']);
                } catch (\Exception $e) {
                    Log::warning( $e->getMessage() );
                }

                return true;
            }
        } catch (\Exception $e) {
            $message = "module=GenPdfSign\tmethod=" . __METHOD__ . "\tparams=" . json_encode($params) . "\tmsg=" . $e->getMessage();
            AlertClient::sendAlertEmail($e);
            Log::warning( $message );
            return false;
        }
    }

    protected function uploadPdf($params, $originalPdf)
    {
        $contractId = $params['contractSn'];
        $docTitle = $params['contractTitle'];
        $docUrl = OssClient::getUrlByFilename(Constant::FILE_TYPE_CONTRACT, $originalPdf);
        return ContractClient::uploadDoc($contractId, $docTitle, $docUrl);
    }

    protected function signPdf($params)
    {
        $contractId = $params['contractSn'];
        $docTitle = $params['contractTitle'];

        $result = [];
        foreach ($params['contractPdfSign'] as $oneSign) {
            $result = ContractClient::signDoc($contractId, $oneSign['customer_id'], $oneSign['client_role'], $docTitle, $oneSign['sign_keyword']);
        }

        return $result;
    }

}
