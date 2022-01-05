<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-11
 * Time: 10:24
 */

namespace App\Http\Controllers\Apis;

use App\Common\AlertClient;
use App\Common\Utils;
use App\Consts\Banks;
use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Consts\Procedure;
use App\Consts\Profile;
use App\Consts\Scheme;
use App\Consts\Text;
use App\Exceptions\CustomException;
use App\Helpers\AuthCenter;
use App\Helpers\Locker;
use App\Helpers\RepayCenter;
use App\Helpers\UserHelper;
use App\Http\Controllers\Controller;
use App\Models\AuthRecords;
use App\Models\BankCard;
use App\Models\OpenAccountRecords;
use App\Models\Orders;
use App\Models\Procedures;
use App\Services\ProcedureService;
use App\Services\States\Helpers\RiskHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProcedureController extends Controller
{
    // 初始化流程数据
    public function init(Request $request)
    {
        $validatedData = $request->validate([
            'bqs_token_key' => 'required',
            'device_token_key' => 'required',
        ]);
        $user = $request->user;
        $bizNo = Utils::genBizNo();
        $lockerKey = 'procedure_init_' . $request->user['id'];
        $locker = new Locker();
        if (!$locker->lock($lockerKey, 2 * 60, $bizNo)) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '有一个流程初始化中，请稍后重试');
        }

        try {
            // 检测用户是否被冻结
            if (!empty($request->user['frozen_end_time']) && (strtotime($request->user['frozen_end_time']) > time())) {
                throw new CustomException(ErrorCode::COMMON_ILLEGAL_REQUEST, '用户被冻结');
            }

            // 检测用户是否全部认证通过
            if (!UserHelper::checkUserAuth($request->user['id'])) {
                throw new CustomException(ErrorCode::COMMON_ILLEGAL_REQUEST, '认证未完成');
            }

            // 检测是否有流程数据
            $procedure = Procedures::where(['user_id' => $request->user['id'], 'status' => Constant::COMMON_STATUS_INIT])->first();
            if ($procedure) {
                throw new CustomException(ErrorCode::COMMON_ILLEGAL_REQUEST, '已有在贷流程单');
            }

            // 检测是否有在贷未结清
            if (Orders::where(['user_id' => $request->user['id'], 'status' => Constant::ORDER_STATUS_ONGOING])->first()) {
                throw new CustomException(ErrorCode::COMMON_ILLEGAL_REQUEST, '已有在贷未结清');
            }

            // 初始化数据
            $data = [
                'biz_no' => Utils::genBizNo(),
                'user_id' => $request->user['id'],
                'status' => Constant::COMMON_STATUS_INIT,
                'sub_status' => Procedure::STATE_FIRST_RISK,
                'source' => Constant::USER_SOURCE_APP,
            ];

            Procedures::create($data);
        } catch (\Exception $e) {
            $locker->restoreLock($lockerKey, $bizNo);
            throw new CustomException($e->getCode(), $e->getMessage());
        }

        $locker->restoreLock($lockerKey, $bizNo);
        // 保存授权数据
        AuthCenter::saveAuthInfoOfBqsDevice($user, $validatedData['bqs_token_key'],
            $validatedData['device_token_key'], AuthCenter::AUTH_SCENE_FIRST_RISK);
        return $this->render();
    }

    // 接收风控回调
    public function riskCallback(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'application_id' => 'required|string',
                'suggestion' => 'required|int|in:0,1',
                'product' => 'required|int|in:' . Constant::PRODUCT_TYPE_LOTUS,
                'remark' => 'nullable|string',
                'unvalid_list' => 'nullable|json',
                'freeze' => 'required|int',
            ]);
            if (1 == $validatedData['suggestion']) {
                $validatedData = $request->validate([
                    'application_id' => 'required|string',
                    'suggestion' => 'required|int|in:0,1',
                    'unvalid_list' => 'nullable|json',
                    'product' => 'required|int|in:' . Constant::PRODUCT_TYPE_LOTUS,
                    'remark' => 'nullable|string',
                    'score' => 'required|int',
                    'amount' => 'required|int|min:' . intval(Constant::AMOUNT_MIN / 100) . '|max:' . intval(Constant::AMOUNT_MAX / 100),
                    'min_amount' => 'required|int|min:' . intval(Constant::AMOUNT_MIN / 100),
                    'step_amount' => 'required|int|min:' . intval(Constant::AMOUNT_STEP / 100),
                    'valid_days' => 'required|int|min:1',
                    'cate' => 'required|json',
                    'fee_rate' => 'required|numeric|min:0|max:0.03',
                    'vendor' => 'required|int',
                    'repay_type' => 'required|int|in:' . Constant::REPAY_TYPE_MONTHLY,
                    'fee_type' => 'required|int|in:' . Constant::FEE_TYPE_EQUAL_CAPITAL_EQUAL_INTEREST,
                    'freeze' => 'required|int',
                ]);
                $cates = json_decode($validatedData['cate'], true);
                if (!is_array($cates)) {
                    throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '期次信息错误');
                }
                foreach ($cates as $cate) {
                    if (!in_array($cate, Constant::BORROW_PERIODS_LIST)) {
                        throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '期次信息错误');
                    }
                }
                $validatedData['amount'] *= 100;
                $validatedData['min_amount'] *= 100;
                $validatedData['step_amount'] *= 100;
                $validatedData['cate'] = implode(',', $cates);
                $validatedData['fee_rate'] *= 10000;
            }

            $validatedData['unvalid_list'] = (isset($validatedData['unvalid_list']) && $validatedData['unvalid_list'])
                ? implode(',', json_decode($validatedData['unvalid_list'], true)) : '';
            $validatedData['status'] = ($validatedData['suggestion'] == 1) ? Constant::COMMON_STATUS_SUCCESS : Constant::COMMON_STATUS_FAILED;
            unset($validatedData['suggestion']);
            unset($validatedData['product']);
            $bizNo = $validatedData['application_id'];
            unset($validatedData['application_id']);
        } catch (\Exception $e) {
            AlertClient::sendAlertEmail($e, $request);
            Log::warning("module=risk_callback\tmsg=" . $e->getMessage() . "\tinput=" . json_encode($request->input()));
            throw $e;
        }

        if (!RiskHelper::callback($bizNo, $validatedData)) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '请求失败');
        }

        return $this->render();
    }

    // 用户开户
    public function openAccount(Request $request)
    {
        $user = $request->user;
        $validatedData = $request->validate([
            'procedure_no' => 'required|string',
        ]);
        $bizNo = Utils::genBizNo();
        $lockerKey = 'procedure_open_account_' . $user['id'];
        $locker = new Locker();
        if (!$locker->lock($lockerKey, 2 * 60, $bizNo)) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '有一个开户中，请稍后重试');
        }
        try {
            $procedureInfo = Procedures::where(['biz_no' => $validatedData['procedure_no']])->first();
            if (empty($procedureInfo)) {
                throw new CustomException(ErrorCode::COMMON_PARAM_ERROR);
            }

            $procedureService = new ProcedureService ($procedureInfo->id);
            if (empty($procedureService->getState())
                || ($procedureService->getState() != Procedure::STATE_OPEN_ACCOUNT)
                || ($procedureService->getUser() != $user['id'])
            ) {
                throw new CustomException(ErrorCode::COMMON_PARAM_ERROR);
            }

            $result = $procedureService->runState();
            if (empty($result['url'])) {
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, $result['msg']);
            }
        } catch (\Exception $e) {
            $locker->restoreLock($lockerKey, $bizNo);
            throw new CustomException($e->getCode(), $e->getMessage());
        }

        $locker->restoreLock($lockerKey, $bizNo);
        $cardInfo = BankCard::where('type', Constant::BANK_CARD_AUTH_TYPE_AUTH)->where('user_id', $user['id'])
            ->where('status', Constant::AUTH_STATUS_SUCCESS)->first();
        $data = [
            'h5_url' => $result['url'],
            'intercept_url' => '',
            'callback_url' => '',
            'finish_url' => Scheme::APP_CLOSE_PAGE,
            'intercepts' => Utils::getInterceptInfoForOpenAccount($cardInfo['card_no'])
        ];

        return $this->render($data);
    }

    // 放款验证
    public function loanVerify(Request $request)
    {
        $bizNo = Utils::genBizNo();
        $lockerKey = 'procedure_loan_verify_' . $request->user['id'];
        $locker = new Locker();
        if (!$locker->lock($lockerKey, 2 * 60, $bizNo)) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '有一个放款验证中，请稍后重试');
        }

        try {
            $validatedData = $request->validate([
                'procedure_no' => 'required|string',
            ]);

            $procedureInfo = Procedures::where(['biz_no' => $validatedData['procedure_no']])->first();
            if (empty($procedureInfo)) {
                throw new CustomException(ErrorCode::COMMON_PARAM_ERROR);
            }

            $procedureService = new ProcedureService ($procedureInfo->id);
            if (empty($procedureService->getState())
                || ($procedureService->getState() != Procedure::STATE_USER_AUTH)
                || ($procedureService->getUser() != $request->user['id'])
            ) {
                throw new CustomException(ErrorCode::COMMON_PARAM_ERROR);
            }

            $result = $procedureService->runState();
            if (empty($result['url'])) {
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, $result['msg']);
            }
        } catch (\Exception $e) {
            $locker->restoreLock($lockerKey, $bizNo);
            throw new CustomException($e->getCode(), $e->getMessage());
        }

        $locker->restoreLock($lockerKey, $bizNo);

        $data = [
            'h5_url' => $result['url'],
            'intercept_url' => '',
            'callback_url' => '',
            'finish_url' => Scheme::APP_CLOSE_PAGE,
        ];

        return $this->render($data);
    }

    // 接受开户同步回调
    public function openAccountCallback(Request $request)
    {
        $validatedData = $request->validate([
            'biz_no' => 'required|string',
        ]);

        $record = OpenAccountRecords::where('biz_no', $validatedData['biz_no'])->first();

        if (empty($record)) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR);
        }

        if (!OpenAccountRecords::where(['id' => $record->id, 'is_submit' => Procedure::SUBMIT_NO])->update(['is_submit' => Procedure::SUBMIT_OK])) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '请求失败');
        }

        header("HTTP/1.1 302 Moved Temp");
        header("Location:" . Scheme::APP_CLOSE_PAGE);
        return;
    }

    // 接受放款验证同步回调
    public function loanVerifyCallback(Request $request)
    {
        $validatedData = $request->validate([
            'biz_no' => 'required|string',
        ]);

        $record = AuthRecords::where('biz_no', $validatedData['biz_no'])->first();

        if (empty($record)) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR);
        }

        if (!AuthRecords::where(['id' => $record->id, 'is_submit' => Procedure::SUBMIT_NO])->update(['is_submit' => Procedure::SUBMIT_OK])) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '请求失败');
        }

        header("HTTP/1.1 302 Moved Temp");
        header("Location:" . Scheme::APP_CLOSE_PAGE);
        return;
    }

    // 提交订单
    public function orderSubmit(Request $request)
    {
        $validatedData = $request->validate([
            'procedure_no' => 'required|string',
            'amount' => 'required|int',
            'periods' => 'required|int',
            'purpose' => 'required|string',
            'bqs_token_key' => 'required',
            'device_token_key' => 'required',
        ]);
        $user = $request->user;
        $bizNo = Utils::genBizNo();
        $lockerKey = 'procedure_order_submit_' . $request->user['id'];
        $locker = new Locker();
        if (!$locker->lock($lockerKey, 2 * 60, $bizNo)) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '有一个订单提交中，请稍后重试');
        }

        try {
            // 检测借款参数
            $this->checkOrderAmount($request);

            $procedureInfo = Procedures::where(['biz_no' => $validatedData['procedure_no']])->first();

            $params = [
                'amount' => $validatedData['amount'],
                'periods' => $validatedData['periods'],
                'loan_usage' => $validatedData['purpose'],
                'periods_type' => 0,
            ];

            $procedureService = new ProcedureService ($procedureInfo->id);
            if (empty($procedureService->getState())
                || ($procedureService->getState() != Procedure::STATE_ORDER_SUBMIT)
                || ($procedureService->getUser() != $request->user['id'])
            ) {
                throw new CustomException(ErrorCode::COMMON_ILLEGAL_REQUEST, '无效的订单请求');
            }

            if (!$procedureService->runState($params)) {
                throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '请求失败');
            }
        } catch (\Exception $e) {
            $locker->restoreLock($lockerKey, $bizNo);
            throw new CustomException($e->getCode(), $e->getMessage());
        }

        $locker->restoreLock($lockerKey, $bizNo);
        // 保存授权数据
        AuthCenter::saveAuthInfoOfBqsDevice($user, $validatedData['bqs_token_key'],
            $validatedData['device_token_key'], AuthCenter::AUTH_SCENE_ORDER_CREATION);
        return $this->render();
    }

    // 借款信息
    public function borrowInfo(Request $request)
    {
        $validatedData = $request->validate([
            'procedure_no' => 'required|string',
        ]);

        $procedureInfo = Procedures::where(['biz_no' => $validatedData['procedure_no']])->first();
        if (empty($procedureInfo)) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '无效的流水号');
        }

        $usedAuthedAmount = UserHelper::getUserUsedAuthedAmount($request->user['id']);
        $amountMax = min(Constant::AMOUNT_MAX, ($request->user['authed_amount'] - $usedAuthedAmount));
        $amountMin = max($procedureInfo->authed_min_amount, Constant::AMOUNT_MIN);

        $periodsList = [];
        foreach (explode(',', $procedureInfo->authed_periods) as $val) {
            $periodsList[] = ['k' => $val, 'v' => $val . '个月'];
        }

        $bankName = Banks::CODE_NAME_MAPPINGS[$request->user['bank_code']];
        $cardNo = substr($request->user['card_no'], -4);
        $data = [
            "max_amount" => sprintf('%.2f', $amountMax / 100),
            "min_amount" => sprintf('%.2f', $amountMax / 100), //sprintf('%.2f', $amountMin / 100),
            "fee_rate" => "0%",
            "purposes" => Profile::PURPOSES,
            "periods" => $periodsList,
            "repay_method" => Text::REPAY_METHOD,
            "receive_account" => $bankName . ' ' . $cardNo,
            "card_no_postfix" => $cardNo,
            "contracts" => [
                Utils::genNavigationItem('',
                    sprintf(Scheme::APP_WEBVIEW_NONEED_LOGIN_FORMAT, urlencode(env('H5_BASE_URL') . Scheme::H5_LOAN_AGREEMENT), urlencode('《借款协议》')),
                    '《借款协议》', '', '', '', '0501012'),
                Utils::genNavigationItem('',
                    sprintf(Scheme::APP_WEBVIEW_NONEED_LOGIN_FORMAT, urlencode(env('H5_BASE_URL') . Scheme::H5_BORROWER_SERVICE_AGREEMENT), urlencode('《借款人服务协议》')),
                    '《借款人服务协议》', '', '', '', '0501013'),
                Utils::genNavigationItem('',
                    sprintf(Scheme::APP_WEBVIEW_NONEED_LOGIN_FORMAT, urlencode(env('H5_BASE_URL') . Scheme::H5_SHUILIAN_DANBAO_AGREEMENT), urlencode('《委托担保申请》')),
                    '《委托担保申请》', '', '', '', '0501014'),
            ],
            "back_alert" => Text::AUTH_BACK_ALERT,
            "back_link" => Scheme::APP_INDEX,
            "next_link" => Scheme::APP_INDEX,
        ];

        return $this->render($data);
    }

    // 借款试算
    public function borrowTrial(Request $request)
    {
        $validatedData = $request->validate([
            'procedure_no' => 'required|string',
            'amount' => 'required|int',
            'periods' => 'required|int',
        ]);

        // 检测借款参数
        $this->checkOrderAmount($request);

        $procedureInfo = Procedures::where(['biz_no' => $validatedData['procedure_no']])->first();

        $installments = RepayCenter::genInstallments($validatedData['amount'], $validatedData['periods'], $procedureInfo->authed_fee_rate);

        $total_period_amount = 0;

        foreach ($installments as $key => $val) {
            $installments[$key]['capital'] = sprintf('%.2f', $val['capital'] / 100);
            $installments[$key]['interest'] = sprintf('%.2f', $val['interest'] / 100);
            $installments[$key]['amount'] = sprintf('%.2f', ($val['capital'] + $val['interest']) / 100);
            $total_period_amount += $val['capital'] + $val['interest'];
        }

        $data = [
            'total_period_amount' => sprintf('%.2f', $total_period_amount / 100),
            'installments' => $installments,
        ];

        return $this->render($data);
    }

    // 检测借款参数
    protected function checkOrderAmount(Request $request)
    {
        $procedureInfo = Procedures::where(['biz_no' => $request->input('procedure_no')])->first();
        if (empty($procedureInfo)) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '无效的流水号');
        }

        $amount = $request->input('amount');
        $periods = $request->input('periods');
        $purpose = $request->input('purpose');

        $usedAuthedAmount = UserHelper::getUserUsedAuthedAmount($request->user['id']);
        $amountMax = min(Constant::AMOUNT_MAX, ($request->user['authed_amount'] - $usedAuthedAmount));
        $amountMin = max($procedureInfo->authed_min_amount, Constant::AMOUNT_MIN);
        $amountStep = max($procedureInfo->authed_step_amount, Constant::AMOUNT_STEP);

        if (($amount % $amountStep) != 0) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, sprintf('借款金额需为%.2f的整数倍', $amountStep / 100));
        }
        if ($amount < $amountMin) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, sprintf('单笔借款金额不得低于%.2f元', $amountMin / 100));
        }
        if ($amount > $amountMax) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, sprintf('当前单笔最高可借%.2f元', $amountMax / 100));
        }
        if (!in_array($periods, explode(',', $procedureInfo->authed_periods))) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '无效的借款期次');
        }
        if (!empty($purpose) && !in_array($purpose, array_column(Profile::PURPOSES, 'k'))) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '无效的借款用途');
        }

        return true;
    }

}
