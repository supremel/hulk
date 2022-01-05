<?php

namespace App\Console\Commands\Once;

use App\Common\OssClient;
use App\Common\Pdf2HtmlClient;
use App\Consts\Constant;
use App\Consts\Contract;
use App\Models\Contracts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ContractInitCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'once:contract_init';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '初始化历史合同数据';

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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::info('module=' . $this->signature . "\tmsg=starts");
        $contracts = Contracts::where('contract_type', Contract::AGREEMENT_FACE_BANK)
            ->where('h5_view_url', '=', '')->get()->toArray();
        foreach ($contracts as $contract) {
            try {
                if ($contract['original_pdf'] != '' && $contract['sign_pdf'] == '') {
                    $filename = $contract['contract_sn'] . '.sign.contract.pdf';
                    $uploadRet = OssClient::upload(Constant::FILE_TYPE_CONTRACT, $filename, file_get_contents($contract['original_pdf']));
                    if (!empty($uploadRet)) {
                        Contracts::where('id', $contract['id'])
                            ->update([
                                'sign_pdf' => $uploadRet
                            ]);
                        $contract['sign_pdf'] = $uploadRet;
                    }
                }
                // 格式转换
                if ($contract['sign_pdf'] != '' && $contract['h5_view_url'] == '') {
                    $pdfUrl = OssClient::getUrlByFilename(Constant::FILE_TYPE_CONTRACT, $contract['sign_pdf']);
                    if (!empty($pdfUrl)) {
                        // 格式转换
                        $htmlUrl = Pdf2HtmlClient::doConvert('借款协议',
                            $contract['contract_sn'] . '_' . Contract::AGREEMENT_FACE_BANK,
                            $pdfUrl);
                        if ($htmlUrl) {
                            Contracts::where('id', $contract['id'])
                                ->update([
                                    'h5_view_url' => $htmlUrl,
                                    'status' => Constant::COMMON_STATUS_SUCCESS,
                                ]);
                            $contract['h5_view_url'] = $htmlUrl;
                        }
                    }
                }
            } catch (\Exception $exception) {
                Log::warning('module=' . $this->signature . "\tmsg=ongoing\tid=" . $contract['id']
                    . "\terror=" . $exception->getMessage());
            }
        }
        Log::info('module=' . $this->signature . "\tmsg=ends");
    }
}
