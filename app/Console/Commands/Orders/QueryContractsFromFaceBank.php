<?php
/**
 * 借款协议查询（from笑脸）
 * User: hexuefei
 * Date: 2019-08-19
 * Time: 17:29
 */

namespace App\Console\Commands\Orders;


use App\Common\CapitalClient;
use App\Common\OssClient;
use App\Common\Pdf2HtmlClient;
use App\Consts\Constant;
use App\Consts\Contract;
use App\Models\Contracts;
use App\Models\Orders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class QueryContractsFromFaceBank extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'order:query_contracts_from_facebank';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '订单-从笑脸查询借款协议';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws \App\Exceptions\CustomException
     */
    public function handle()
    {
        Log::info('module=' . $this->signature . "\tmsg=starts");
        $startTime = strtotime('-14 days');
        $orders = Orders::where('created_at', '>', date('Y-m-d H:i:s', $startTime))
            ->whereIn('status', [Constant::ORDER_STATUS_ONGOING, Constant::ORDER_STATUS_PAID_OFF])
            ->get()->toArray();
        foreach ($orders as $order) {
            $record = Contracts::where('relation_id', $order['id'])->where('relation_type', Contract::RELATION_TYPE_ORDER)
                ->where('contract_type', '=', Contract::AGREEMENT_FACE_BANK)
                ->first();
            if (!$record) {
                $contractUrl = CapitalClient::queryContract($order['biz_no']);
                if ($contractUrl) {
                    try {
                        $record = Contracts::create(
                            [
                                'relation_type' => Contract::RELATION_TYPE_ORDER,
                                'relation_id' => $order['id'],
                                'title' => '借款协议',
                                'contract_sn' => $order['biz_no'],
                                'contract_type' => Contract::AGREEMENT_FACE_BANK,
                                'original_pdf' => $contractUrl,
                                'sign_pdf' => '',
                                'h5_view_url' => '',
                                'status' => Constant::COMMON_STATUS_INIT,
                                'is_upload' => Constant::COMMON_STATUS_SUCCESS,
                            ]
                        );
                    } catch (\Exception $e) {
                        Log::info('module=' . $this->signature . "\tmsg=ongoing\terror=" . $e->getMessage());
                        continue;
                    }
                }
            }
            // 转存
            if ($record['original_pdf'] != '' && $record['sign_pdf'] == '') {
                $filename = $order['biz_no'] . '.sign.contract.pdf';
                $uploadRet = OssClient::upload(Constant::FILE_TYPE_CONTRACT, $filename, file_get_contents($record['original_pdf']));
                if (!empty($uploadRet)) {
                    Contracts::where('id', $record['id'])
                        ->update([
                            'sign_pdf' => $uploadRet
                        ]);
                    $record['sign_pdf'] = $uploadRet;
                }
            }
            // 格式转换
            if ($record['sign_pdf'] != '' && $record['h5_view_url'] == '') {
                $pdfUrl = OssClient::getUrlByFilename(Constant::FILE_TYPE_CONTRACT, $record['sign_pdf']);
                if (!empty($pdfUrl)) {
                    // 格式转换
                    $htmlUrl = Pdf2HtmlClient::doConvert('借款协议',
                        $order['biz_no'] . '_' . Contract::AGREEMENT_FACE_BANK,
                        $pdfUrl);
                    if ($htmlUrl) {
                        Contracts::where('id', $record['id'])
                            ->update([
                                'h5_view_url' => $htmlUrl,
                                'status' => Constant::COMMON_STATUS_SUCCESS,
                            ]);
                        $record['h5_view_url'] = $htmlUrl;
                    }
                }
            }
            if ($record['h5_view_url'] != '') {
                Log::info('module=' . $this->signature . "\tmsg=contract created\torder_id=" . $order['id']);
            }
        }
        Log::info('module=' . $this->signature . "\tmsg=ends");
    }
}