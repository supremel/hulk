<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-09
 * Time: 14:24
 */

namespace App\Http\Controllers\Apis;

use App\Common\AuthCenterClient;
use App\Common\MnsClient;
use App\Common\RedisClient;
use App\Common\Utils;
use App\Consts\Constant;
use App\Consts\Contract;
use App\Consts\ErrorCode;
use App\Consts\Scheme;
use App\Exceptions\CustomException;
use App\Helpers\AuthCenter;
use App\Helpers\Locker;
use App\Http\Controllers\Controller;
use App\Models\AuthInfo;
use App\Models\DeviceInfo;
use App\Models\Users;
use App\Services\HulkEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TpAuthController extends Controller
{
    const OPERATOR_TOKEN_KEY = 'tmp_token_';

    //获取授权地址
    public function index(Request $request)
    {
        $user = $request->user;
        $validatedData = $request->validate(
            [
                'type' => 'required|in:7,8',
            ]
        );
        $type = $validatedData['type'];
        $data = AuthCenter::getAuthUrl($user, $type);
        return $this->render($data);
    }


    public function tpCallback(Request $request)
    {
        $validatedData = $request->validate(
            [
                'biz_no' => 'required|size:32',
            ]
        );
        $bizNo = $validatedData['biz_no'];
        $isSuccess = true;
        $authInfo = AuthInfo::where('biz_no', $bizNo)->first();
        if ($authInfo['tp'] == 'moxie') {
            // 针对魔蝎特殊处理
            $taskId = $request->input('taskId', '');
            $mxCode = $request->input('mxcode', 0);
            if (empty($taskId) || $mxCode != 1) {
                $isSuccess = false;
            }
        }
        if ($isSuccess) {
            $updateResult =AuthInfo::where('biz_no', $bizNo)->where('status', Constant::AUTH_STATUS_ONGOING)->update(
                ['status' => Constant::AUTH_STATUS_SUCCESS,]
            );
        }
        $taskId       = $taskId ?? 0;
        $mxCode       = $mxCode ?? 0;
        $updateResult = $updateResult ?? "";
        $authInfoJson = json_encode($authInfo,JSON_UNESCAPED_UNICODE);
        $logString = "biz_no={$validatedData['biz_no']}\ttp={$authInfo['tp']}\t";
        $logString .= "taskId={$taskId}\tmxCode=$mxCode\tupdateResult={$updateResult}\tauthInfo={$authInfoJson}";
        Log::Info("module=TpAuth\taction=tpCallBack\t{$logString}");

        try{
            $data['contractType'] = Contract::TYPE_AUTH;
            $data['contractStep'] = Contract::STEP_GEN_DISPENSE;
            $data['relationType'] = Contract::RELATION_TYPE_USER;
            $data['relationId'] = $authInfo->user_id;
            $data['authSuccessTime'] = date('Y-m-d H:i:s');
            $msg = [
                'event' => HulkEventService::EVENT_TYPE_CONTRACT,
                'params' => $data,
            ];
            MnsClient::sendMsg2Queue(env('HULK_EVENT_ACCESS_ID'), env('HULK_EVENT_ACCESS_KEY'), env('HULK_EVENT_QUEUE_NAME'), json_encode($msg));
        }catch(\Exception $e) {
            Log::warning($e->getMessage());
        }

        header("HTTP/1.1 302 Moved Temp");
        header("Location:" . Scheme::APP_CLOSE_PAGE);
        return;
    }

    /**
     * @desc   运营商回调接口,风控说:就是空方法，能调就行，随便他传啥，反正你返回就对了
     * @action operatorCallbackAction
     * @return void
     * @author liuhao
     * @date   2019/10/22
     */
    public function operatorCallback(Request $request)
    {

        //风控对于第三方的一切都是未知,包括提交的参数是什么,是什么形式都不知道,只能通过日志形式现行记录
        $postParams = $request->post() ? json_encode($request->post()) : '';
        $getParams  = $request->query() ? json_encode($request->query()) : '';

        Log::Info("module=TpAuth\taction=operatorCallback\thttpRequestPostParams={$postParams}\thttpRequestGetParams={$getParams}");

        $return = [
            'return_code'    => '0',
            'data'           => '',
            'return_message' => 'success',
        ];
        $return = json_encode($return);

        return response($return, 200)->header('Content-Type', 'application/json;charset=UTF-8');
    }

    public function asyncNotify(Request $request)
    {
        $validatedData = $request->validate(
            [
                'biz_no' => 'required',
            ]
        );
        $bizNo = $validatedData['biz_no'];
        do {
            $authInfo = AuthInfo::where('biz_no', $bizNo)->first();
            if (!$authInfo) {
                break;
            }
            $data = $request->getContent(); //回调推送的数据
            if (empty($data)) {
                break;
            }
            AuthInfo::where('id', $authInfo['id'])->update([
                'extra' => $data,]);
            $userInfo = Users::where('id', $authInfo['user_id'])->first();
            $deviceInfo = DeviceInfo::where('user_id', $userInfo['id'])->first();
            $toMnsData = [
                'third' => $authInfo['tp'],
                'data' => $data
            ];

            #数据回传给风控
            $mnsResult = AuthCenter::sendAsyncDataToRisk($userInfo, $authInfo['type'], Constant::SEND_MSN_TYPE_AUTH,
                $deviceInfo, json_encode($toMnsData));

            if ($mnsResult) {
                AuthInfo::where('id', $authInfo['id'])->update([
                    'is_pushed' => 1,]);
            }
        } while (false);

        return 'ok';
    }

    public function taobaoAsyncNotifyFromMoxieSdk(Request $request)
    {
        do {
            $data = $request->getContent(); //回调推送的数据
            if (empty($data)) {
                break;
            }

            $dataArr = json_decode($data, true);
            $uid = $dataArr['user_id'] ?? '';
            if (empty($uid)) {
                break;
            }
            $userInfo = Users::where('uid', $uid)->first();
            if (!$userInfo) {
                break;
            }

            $bizNo = Utils::genBizNo();
            $authInfo = AuthInfo::create([
                'biz_no' => $bizNo,
                'user_id' => $userInfo['id'],
                'type' => Constant::DATA_TYPE_TAOBAO,
                'tp' => 'moxie',
                'is_pushed' => Constant::COMMON_STATUS_INIT,
                'extra' => $data,
                'status' => Constant::AUTH_STATUS_SUCCESS,
            ]);
            $deviceInfo = DeviceInfo::where('user_id', $userInfo['id'])->first();
            unset($dataArr['user_id']);
            $toMnsData = [
                'third' => 'moxie',
                'data' => json_encode($dataArr)
            ];

            #数据回传给风控
            $mnsResult = AuthCenter::sendAsyncDataToRisk($userInfo, Constant::DATA_TYPE_TAOBAO, Constant::SEND_MSN_TYPE_AUTH,
                $deviceInfo, json_encode($toMnsData));

            if ($mnsResult) {
                AuthInfo::where('id', $authInfo['id'])->update(['is_pushed' => 1]);
            }
        } while (false);

        return response('', 201)->header('Content-Type', 'text/plain');
    }

    public function phoneAsyncNotifyFromMoxieApi(Request $request)
    {
        do {
            $data = $request->getContent(); //回调推送的数据
            if (empty($data)) {
                break;
            }

            $dataArr = json_decode($data, true);
            $uid = $dataArr['user_id'] ?? '';
            if (empty($uid)) {
                break;
            }
            $items = explode('_', $uid);
            if (count($items) != 2) {
                break;
            }
            $bizNo = $items[1];
            $authInfo = AuthInfo::where('biz_no', $bizNo)->first();
            if (!$authInfo) {
                break;
            }
            AuthInfo::where('id', $authInfo['id'])->update([
                'extra' => $data,
                'is_pushed' => Constant::COMMON_STATUS_INIT,
            ]);

            $deviceInfo = DeviceInfo::where('user_id', $authInfo['user_id'])->first();
            unset($dataArr['user_id']);
            $toMnsData = [
                'third' => 'moxie',
                'data' => json_encode($dataArr)
            ];

            #数据回传给风控
            $userInfo = Users::where('id', $authInfo['user_id'])->first();
            $mnsResult = AuthCenter::sendAsyncDataToRisk($userInfo, Constant::DATA_TYPE_PHONE, Constant::SEND_MSN_TYPE_AUTH,
                $deviceInfo, json_encode($toMnsData));

            if ($mnsResult) {
                AuthInfo::where('id', $authInfo['id'])->update(['is_pushed' => 1]);
            }
        } while (false);

        return response('', 201)->header('Content-Type', 'text/plain');
    }

    /**
     * @desc   验证运营商,发送短信验证码
     * @action operatorSendSmsAction
     * @param  Request  $request
     * @return JsonResponse
     * @throws CustomException
     * @author liuhao
     * @date   2019/9/10
     */
    public function operatorSendSms(Request $request)
    {
        //参数处理
        $validatedData    = $request->validate(
            [
                'biz_no'            => 'required|alpha_num',  //业务流水号
                'phone'             => 'required|regex:/^1[3-9][0-9]{9}$/',
                'operator_password' => 'required|regex:/^[a-zA-Z0-9\/+]*={0,2}$/',//运营商密码base64
            ]
        );
        $bizNo            = $validatedData['biz_no'];
        $phone            = $validatedData['phone'];
        $operatorPassword = $validatedData['operator_password'];
        if ($operatorPassword != base64_encode(base64_decode($operatorPassword))) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '密码格式错误，请重试');
        }
        //获取用户信息
        $authInfo = AuthInfo::where('biz_no', $bizNo)->first();
        if (empty($authInfo)) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '参数错误，请重试');
        }
        $authInfo = $authInfo->toArray();
        $userInfo = Users::where('id', $authInfo['user_id'])->first();
        if (empty($userInfo)) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '参数错误，请重试');
        }
        $userInfo  = $userInfo->toArray();
        if ($userInfo['phone'] != $phone) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '手机号码有误');
        }
        //风控是单进程阻塞处理,我们要限制请求
        $lock = new Locker();
        if (!$lock->lock('operator_lock_'.$phone, '3')) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '无需重复提交');
        }
        /**
         * 第三方有限制: 每个手机号,发送验证码后,如果没有提交验证码验进行证码,10分钟内禁止再次发送验证码.
         * 这里直接在咱们这里做限制,不穿透到风控.(风控是阻塞式单进程)
         */
        $operatorLimit = self::OPERATOR_TOKEN_KEY.$phone;
        $leftTime      = RedisClient::ttl($operatorLimit);
        if ($leftTime > 0) {
            $leftDate = round($leftTime / 60);
            $leftDate = $leftDate <= 1 ? "{$leftTime}秒" : "{$leftDate}分钟";
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, $leftDate.'内无需重复获取短信验证码');
        }

        $deviceRes = DeviceInfo::where('user_id', $userInfo['id'])->first();
        if (empty($deviceRes)) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '参数错误，请重试');
        }
        $deviceRes = $deviceRes->toArray();

        //请求风控
        $riskRequestArgs   = [
            'data_type'     => Constant::DATA_TYPE_PHONE,
            'old_user_id'   => $userInfo['old_user_id'],
            'uid'           => $userInfo['uid'],
            'product'       => Constant::PRODUCT_TYPE_LOTUS,
            'device_id'     => $deviceRes['device_id'],
            'machine_id'    => $deviceRes['imei'],
            'user_name'     => $userInfo['name'],
            'user_password' => base64_decode($operatorPassword),
            'user_phone'    => $phone,
            'user_id_no'    => $userInfo['identity'],
        ];
        $riskRequestParams = [
            'remote_function' => 'jiazhou_get_verify_code',
            'args'            => json_encode($riskRequestArgs),
        ];
        $riskResponse = AuthCenterClient::riskOperator($riskRequestParams);

        //记录日志
        $logParams   = json_encode($riskRequestArgs, JSON_UNESCAPED_UNICODE);
        $logResponse = json_encode($riskResponse, JSON_UNESCAPED_UNICODE);
        Log::Info("module=TpAuth\taction=operatorSendSms\tparams=".$logParams."\tresponse={$logResponse}");

        //处理风控结果
        if (empty($riskResponse['result_dict']) || $riskResponse['code'] != ErrorCode::SUCCESS) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '系统繁忙，请稍后重试');
        }

        //分发临时token,下一步要用
        RedisClient::setWithExpire($operatorLimit, $bizNo, '600');

        $resultData = [
            'crawler_id'    => $riskResponse['result_dict']['crawlerId'],
            'crawler_token' => $riskResponse['result_dict']['crawlerToken'],
            'tmp_token'     => $bizNo,
        ];

        return $this->render($resultData);
    }

    /**
     * @desc   验证运营商,验证短信验证码
     * @action operatorVerifySmsAction
     * @param  Request  $request
     * @throws CustomException
     * @return JsonResponse
     * @author liuhao
     * @date   2019/9/10
     */
    public function operatorVerifySms(Request $request)
    {
        //参数处理
        $validatedData = $request->validate(
            [
                'crawler_id'    => 'required|string',//风控用
                'crawler_token' => 'required|string',//风控用
                'tmp_token'     => 'required|alpha_num',//前置要求token,后期改为流水号
                'sms_code'      => 'required|alpha_num',//短信验证码
                'phone'         => 'required|regex:/^1[3-9][0-9]{9}$/',
            ]
        );
        //前置条件判断
        $redisKey = self::OPERATOR_TOKEN_KEY.$validatedData['phone'];
        $bizNo    = RedisClient::get($redisKey);
        if (empty($bizNo) || $bizNo != $validatedData['tmp_token']) {
            //防止直接打风控,风控是阻塞单进程
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '请先获取手机验证码');
        }
        //获取用户信息
        $authInfo = AuthInfo::where('biz_no', $bizNo)->first();
        if (empty($authInfo)) {
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '参数错误，请重试');
        }
        $authInfo  = $authInfo->toArray();
        $userInfo  = Users::where('id', $authInfo['user_id'])->first();
        $userInfo  = $userInfo->toArray();
        $deviceRes = DeviceInfo::where('user_id', $userInfo['id'])->first();
        $deviceRes = $deviceRes->toArray();
        //发送验证请求
        $riskRequestArgs   = [
            'data_type'     => Constant::DATA_TYPE_PHONE,
            'old_user_id'   => $userInfo['old_user_id'],
            'uid'           => $userInfo['uid'],
            'product'       => Constant::PRODUCT_TYPE_LOTUS,
            'device_id'     => $deviceRes['device_id'],
            'machine_id'    => $deviceRes['imei'],
            'crawler_id'    => $validatedData['crawler_id'],
            'crawler_token' => $validatedData['crawler_token'],
            'verify_code'   => $validatedData['sms_code'],
            'user_phone'    => $validatedData['phone'],
        ];
        $riskRequestParams = [
            'remote_function' => 'jiazhou_send_verify_code',
            'args'            => json_encode($riskRequestArgs),
        ];
        $riskResponse      = AuthCenterClient::riskOperator($riskRequestParams);
        //删除10分钟限制
        RedisClient::delete($redisKey);
        //记录日志
        $logParams   = json_encode($riskRequestArgs, JSON_UNESCAPED_UNICODE);
        $logResponse = json_encode($riskResponse, JSON_UNESCAPED_UNICODE);
        Log::Info("module=TpAuth\taction=operatorVerifySms\tparams=".$logParams."\tresponse={$logResponse}");
        //处理验证结果
        if (empty($riskResponse) || $riskResponse['code'] != ErrorCode::SUCCESS) {
            //需要二次输入验证码
            if ($riskResponse['code'] == ErrorCode::RISK_RESPONSE_CODE_RESEND) {
                //分发临时token,下一步要用
                RedisClient::setWithExpire($redisKey, $bizNo, '600');
                throw new CustomException(ErrorCode::RISK_RESPONSE_CODE_RESEND);
            }
            throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, '验证码错误，请重新获取手机验证码');
        }

        return $this->render();
    }


}