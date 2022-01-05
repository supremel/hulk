<?php
/**
 * 业务事件统一处理
 * User: hexuefei
 * Date: 2019-08-12
 * Time: 14:44
 */

namespace App\Services\HulkEvents\Contracts;

use App\Common\AlertClient;
use App\Common\MnsClient;
use App\Common\OssClient;
use App\Consts\Constant;
use App\Consts\Contract;
use App\Consts\ErrorCode;
use App\Exceptions\CustomException;
use App\Models\Contracts;
use App\Services\HulkEventService;
use Illuminate\Support\Facades\Log;

class GenPdfFile
{
    public function handle($params)
    {
        try {
            Log::info("module=GenPdfFile\tmsg=ongoing\tcontent=" . json_encode($params));
            // 判断用户是否已经生成该协议
            $contractInfo = Contracts::where('relation_id', $params['relationId'])
                ->where('relation_type', $params['relationType'])
                ->where('contract_type', $params['contractAgreement'])
                ->whereIn('status', [Constant::COMMON_STATUS_SUCCESS, Constant::COMMON_STATUS_INIT])
                ->first();

            if ($contractInfo && ($contractInfo->status == Constant::COMMON_STATUS_SUCCESS)) {
                return true;
            }

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
                $modelFile = view(Contract::AGREEMENT_RELATION[$params['contractAgreement']]['view'], $params['contractPdfData']);
                $fileName = $params['contractSn'] . '.original.contract.pdf';
                if ($fileName = $this->createPdf($fileName, $modelFile)) {
                    $contractData = ['original_pdf' => $fileName];
                    $contractWhere = [
                        'relation_id' => $params['relationId'],
                        'relation_type' => $params['relationType'],
                        'contract_type' => $params['contractAgreement'],
                        'contract_sn' => $params['contractSn'],
                    ];
                    if (Contracts::where($contractWhere)->update($contractData)) {
                        $params['contractStep'] = Contract::STEP_GEN_PDF_SIGN;
                        $msg = [
                            'event' => HulkEventService::EVENT_TYPE_CONTRACT,
                            'params' => $params,
                        ];
                        MnsClient::sendMsg2Queue(env('HULK_EVENT_ACCESS_ID'), env('HULK_EVENT_ACCESS_KEY'), env('HULK_EVENT_QUEUE_NAME'), json_encode($msg));

                        return true;
                    }
                }

                throw  new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '合同生成-生成初始PDF失败');
            }
        } catch (\Exception $e) {
            $message = "module=GenPdfFile\tmethod=" . __METHOD__ . "\tparams=" . json_encode($params) . "\tmsg=" . $e->getMessage();
            AlertClient::sendAlertEmail($e);
            Log::warning($message);
            return false;
        }
    }

    protected function createPdf($fileName, $modelFile)
    {
        #设置参数
        $conf = [
            'mode' => 'zh-CN',
            'default_font_size' => 10,
            'default_font' => '宋体',
        ];
        $mpdf = new \Mpdf\Mpdf($conf);
        $mpdf->useAdobeCJK = TRUE;
        $mpdf->autoLangToFont = TRUE;
        $mpdf->autoScriptToLang = false;
        $mpdf->baseScript = 1;
        $mpdf->autoVietnamese = true;
        $mpdf->autoArabic = true;
        $mpdf->list_indent_first_level = 0;
        $mpdf->SetDisplayMode('fullpage');

        //创建pdf文件
        $mpdf->WriteHTML($modelFile);

        //输出pdf
        $pdfContent = $mpdf->Output($fileName, 'S');
        return OssClient::upload(Constant::FILE_TYPE_CONTRACT, $fileName, $pdfContent);
    }
}
