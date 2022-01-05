<?php

namespace App\Console\Commands\Monitors;

use App\Consts\Constant;
use App\Jobs\Monitors\EmailMonitor;
use App\Models\AuthRecords;
use App\Models\OpenAccountRecords;
use App\Models\OrderPushRecords;
use App\Models\Orders;
use App\Models\RepaymentRecords;
use App\Models\RiskEvaluations;
use App\Models\Users;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;

class KpiCommand extends Command
{
    private $_tplFile = 'monitor.xlsx';
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'monitor:kpi';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '监控-kpi统计（业务节点&还款）';

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
     * 统计业务节点监控数据
     * @param $startTime YYYY-mm-dd HH:ii:ss
     * @return array
     */
    private function _statKeyNodesData($startTime)
    {
        $users = Users::where('created_at', '>=', $startTime)->get()->toArray();
        Log::info('module=' . $this->signature . "\tmsg=ongoing\tstart_time=" . $startTime . "\tnew_reg_users=" . count($users));
        $data = [];
        foreach ($users as $user) {
            $channel = $user['reg_channel'];
            if (!in_array($channel, Constant::API_CHANNELS)) {
                $channel = Constant::REGISTER_CHANNEL_APP;
            }
            if (!isset($data[$channel])) {
                $data[$channel] = [
                    'regs' => 1,
                    'risks' => 0,
                    'first_risk_passed' => 0,
                    'opens' => 0,
                    'orders' => 0,
                    'second_risk_passed' => 0,
                    'pushed' => 0,
                    'withdraw_authed' => 0,
                    'loaned' => 0,
                ];
            }
            $data[$channel]['regs'] += 1;
            $riskRecord = RiskEvaluations::where('user_id', $user['id'])
                ->where('num', '=', 1)->first();
            if ($riskRecord) {
                $data[$channel]['risks'] += 1;
                if ($riskRecord['status'] == Constant::COMMON_STATUS_SUCCESS) {
                    $data[$channel]['first_risk_passed'] += 1;
                }
            }
            $openRecord = OpenAccountRecords::where('user_id', $user['id'])
                ->where('status', Constant::COMMON_STATUS_SUCCESS)->first();
            if ($openRecord) {
                $data[$channel]['opens'] += 1;
            }
            $orderRecord = Orders::where('user_id', $user['id'])->first();
            if ($orderRecord) {
                $data[$channel]['orders'] += 1;
            }
            $riskRecord = RiskEvaluations::where('user_id', $user['id'])
                ->where('num', '=', 2)
                ->where('status', '=', Constant::COMMON_STATUS_SUCCESS)->first();
            if ($riskRecord) {
                $data[$channel]['second_risk_passed'] += 1;
            }
            $pushRecord = OrderPushRecords::where('user_id', $user['id'])
                ->where('status', '=', Constant::COMMON_STATUS_SUCCESS)->first();
            if ($pushRecord) {
                $data[$channel]['pushed'] += 1;
            }
            $withdrawAuthRecord = AuthRecords::where('user_id', $user['id'])
                ->where('status', '=', Constant::COMMON_STATUS_SUCCESS)->first();
            if ($withdrawAuthRecord) {
                $data[$channel]['withdraw_authed'] += 1;
            }
            $orderRecord = Orders::where('user_id', $user['id'])
                ->where('status', '=', Constant::ORDER_STATUS_ONGOING)->first();
            if ($orderRecord) {
                $data[$channel]['loaned'] += 1;
            }
        }
        return $data;
    }

    /**
     * 写业务节点监控对应的sheet
     * @param $sheet
     * @param $data
     * @return array
     */
    private function _writeKeyNodeSheet($sheet, $data)
    {
        $ret = [];
        $rows = 3;
        foreach ($data as $channel => $item) {
            $regs = $item['regs'];
            $risks = $item['risks'];
            $firstPassed = $item['first_risk_passed'];
            $opens = $item['opens'];
            $orders = $item['orders'];
            $secondRiskPassed = $item['second_risk_passed'];
            $pushed = $item['pushed'];
            $authed = $item['withdraw_authed'];
            $loaned = $item['loaned'];
            $ret[$channel] = [
                date('m月d日'),
                $regs,
                $risks,
                sprintf('%.2f%%', $risks / $regs * 100),
                $firstPassed,
                sprintf('%.2f%%', ($risks == 0 ? 0 : $firstPassed / $risks * 100)),
                $opens,
                sprintf('%.2f%%', ($firstPassed == 0 ? 0 : $opens / $firstPassed * 100)),
                $orders,
                sprintf('%.2f%%', ($opens == 0 ? 0 : $orders / $opens * 100)),
                $secondRiskPassed,
                sprintf('%.2f%%', ($orders == 0 ? 0 : $secondRiskPassed / $orders * 100)),
                $pushed,
                sprintf('%.2f%%', ($secondRiskPassed == 0 ? 0 : $pushed / $secondRiskPassed * 100)),
                $authed,
                sprintf('%.2f%%', ($pushed == 0 ? 0 : $authed / $pushed * 100)),
                $loaned,
                sprintf('%.2f%%', ($firstPassed == 0 ? 0 : $pushed / $firstPassed * 100))
            ];
            $sheet->setCellValue('A' . $rows, $channel)
                ->setCellValue('B' . $rows, date('m月d日'))
                ->setCellValue('C' . $rows, $regs)
                ->setCellValue('D' . $rows, $risks)
                ->setCellValue('E' . $rows, sprintf('%.2f%%', $risks / $regs * 100))
                ->setCellValue('F' . $rows, $firstPassed)
                ->setCellValue('G' . $rows, sprintf('%.2f%%', ($risks == 0 ? 0 : $firstPassed / $risks * 100)))
                ->setCellValue('H' . $rows, $opens)
                ->setCellValue('I' . $rows, sprintf('%.2f%%', ($firstPassed == 0 ? 0 : $opens / $firstPassed * 100)))
                ->setCellValue('J' . $rows, $orders)
                ->setCellValue('K' . $rows, sprintf('%.2f%%', ($opens == 0 ? 0 : $orders / $opens * 100)))
                ->setCellValue('L' . $rows, $secondRiskPassed)
                ->setCellValue('M' . $rows, sprintf('%.2f%%', ($orders == 0 ? 0 : $secondRiskPassed / $orders * 100)))
                ->setCellValue('N' . $rows, $pushed)
                ->setCellValue('O' . $rows, sprintf('%.2f%%', ($secondRiskPassed == 0 ? 0 : $pushed / $secondRiskPassed * 100)))
                ->setCellValue('P' . $rows, $authed)
                ->setCellValue('Q' . $rows, sprintf('%.2f%%', ($pushed == 0 ? 0 : $authed / $pushed * 100)))
                ->setCellValue('R' . $rows, $loaned)
                ->setCellValue('S' . $rows, sprintf('%.2f%%', ($firstPassed == 0 ? 0 : $pushed / $firstPassed * 100)));

            $rows += 1;
        }
        return $ret;
    }

    /**
     * 统计还款数据
     * @param $startTime
     * @return array
     */
    private function _statRepayData($startTime)
    {
        $data = [];
        $records = RepaymentRecords::where('created_at', '>=', $startTime)->get()->toArray();
        foreach ($records as $record) {
            $bType = $record['business_type'];
            if ($bType == Constant::RECHARGE_BUSINESS_TYPE_SYYTEM_TIMING ||
                $bType == Constant::RECHARGE_BUSINESS_TYPE_RECHARGE ||
                $bType == Constant::RECHARGE_BUSINESS_TYPE_SYNC_FROM_LEGACY) {
                $order = Orders::select('source')->where('id', $record['order_id'])->first();
                $source = $order['source'];
                if (!isset($data[$source])) {
                    $data[$source] = [
                        Constant::RECHARGE_BUSINESS_TYPE_RECHARGE => [
                            'r' => [],
                            's' => [],
                        ],
                        Constant::RECHARGE_BUSINESS_TYPE_SYYTEM_TIMING => [
                            'r' => [],
                            's' => [],
                        ],
                        Constant::RECHARGE_BUSINESS_TYPE_SYNC_FROM_LEGACY => [
                            'r' => [],
                            's' => [],
                        ],
                    ];
                }
                $data[$source][$bType]['r'][] = $record['user_id'];
                if ($record['status'] == Constant::COMMON_STATUS_SUCCESS) {
                    $data[$source][$bType]['s'][] = $record['user_id'];
                }
            }
        }
        return $data;
    }

    /**
     * 写还款监控对应的sheet
     * @param $sheet
     * @param $data
     * @return array
     */
    private function _writeRepaySheet($sheet, $data)
    {
        $ret = [];
        $rows = 3;
        foreach ($data as $channelId => $item) {
            $channel = Constant::USER_SOURCE_DICT[$channelId];
            $countSR = count(array_unique($item[Constant::RECHARGE_BUSINESS_TYPE_SYYTEM_TIMING]['r']));
            $countSS = count(array_unique($item[Constant::RECHARGE_BUSINESS_TYPE_SYYTEM_TIMING]['s']));
            $countUR = count(array_unique($item[Constant::RECHARGE_BUSINESS_TYPE_RECHARGE]['r']));
            $countUS = count(array_unique($item[Constant::RECHARGE_BUSINESS_TYPE_RECHARGE]['s']));
            $countS = count(array_unique($item[Constant::RECHARGE_BUSINESS_TYPE_SYNC_FROM_LEGACY]['s']));
            $ret[$channel] = [
                $countSR,
                $countSS,
                sprintf('%.2f%%', $countSR == 0 ? 0 : ($countSS / $countSR * 100)),
                $countUR,
                $countUS,
                sprintf('%.2f%%', $countUR == 0 ? 0 : ($countUS / $countUR * 100)),
                $countS
            ];
            $sheet->setCellValue('A' . $rows, $channel)
                ->setCellValue('B' . $rows, $countSR)
                ->setCellValue('C' . $rows, $countSS)
                ->setCellValue('D' . $rows, sprintf('%.2f%%', $countSR == 0 ? 0 : ($countSS / $countSR * 100)))
                ->setCellValue('E' . $rows, $countUR)
                ->setCellValue('F' . $rows, $countUS)
                ->setCellValue('G' . $rows, sprintf('%.2f%%', $countUR == 0 ? 0 : ($countUS / $countUR * 100)));
            $rows += 1;
        }
        return $ret;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::info('module=' . $this->signature . "\tmsg=starts");
        $startTime = date('Y-m-d 00:00:00');
        $dir = dirname(__FILE__);
        $file = $dir . '/' . $this->_tplFile;
        try {
            $spreadsheet = IOFactory::load($file);
            $keyNodeData = $this->_statKeyNodesData($startTime);
            $keyData = $this->_writeKeyNodeSheet($spreadsheet->setActiveSheetIndex(0), $keyNodeData);
            $data['key'] = $keyData;
            $repayData = $this->_statRepayData($startTime);
            $repay = $this->_writeRepaySheet($spreadsheet->setActiveSheetIndex(1), $repayData);
            $data['repay'] = $repay;
//            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $outFile = null;
//            $outFile = '/tmp/' . date('Y-m-d H:00') . '.xlsx';
//            $writer->save($outFile);
            Mail::to(json_decode(env('MONITOR_EMAIL_USER_LIST'), true))
                ->send(new EmailMonitor("业务监控【" . date('Y-m-d H:00') . "】", $data, $outFile));
        } catch (\Exception $exception) {
            Log::warning('module=' . $this->signature . "\tmsg=" . $exception->getMessage());
        }

        Log::info('module=' . $this->signature . "\tmsg=ends");
    }
}
