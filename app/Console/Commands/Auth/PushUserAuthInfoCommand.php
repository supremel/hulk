<?php

/**
 * 认证-第三方认证数据推送（to认证中心）
 * User: hexuefei
 * Date: 2019-07-19
 * Time: 18:16
 */

namespace App\Console\Commands\Auth;

use App\Common\AuthCenterClient;
use App\Common\OssClient;
use App\Consts\Constant;
use App\Helpers\AuthCenter;
use App\Models\AuthInfo;
use App\Models\DeviceInfo;
use App\Models\Users;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PushUserAuthInfoCommand extends Command
{

    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'auth:push_auth_info';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '认证-第三方认证数据推送（to认证中心）';

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
     * 定时处理当天未推送的数据
     *
     * @return mixed
     */
    public function handle()
    {
        Log::info('module=' . $this->signature . "\tmsg=starts");
        $start_time = date('Y-m-d H:i:s', strtotime('-1 day'));
        $data = AuthInfo::where('updated_at', '>=', $start_time)->where('is_pushed', 0)->get()->toArray();
        if (!empty($data)) {
            foreach ($data as $row) {
                $extra = $row['extra'];
                if (empty($extra)) {
                    continue;
                }
                $type = $row['type'];
                #设备类的数据，需要调用接口处理
                if ($type == Constant::DATA_TYPE_DEVICE_INFO) {
                    if (empty(json_decode($extra))) {
                        Log::warning('module=' . $this->signature . "\tbizNo=" . $row['biz_no'] . "\tmsg=extra erro");
                        continue;
                    }
                    $extraArr = json_decode($extra, true);
                    $requestId = $extraArr['requestId'] ?? '';
                    if (empty($requestId)) {
                        Log::warning('module=' . $this->signature . "\tbizNo=" . $row['biz_no'] . "\tmsg=not find requestId");
                        continue;
                    }
                    $decryptData = AuthCenterClient::decryptDeviceDataByRequestId($requestId);
                    if (empty($decryptData) || (0 != $decryptData['code'])) {
                        Log::warning('module=' . $this->signature . "\tbizNo=" . $row['biz_no'] . "\tmsg=decrypt device data error");
                        continue;
                    }
                    $data = [
                        'id' => $decryptData['data']['meta']['unique_snapshot_id'],
                        'source' => env('APP_NAME')
                    ];
                    $res = AuthCenterClient::getDeviceData($data);
                    if (!empty($res) && !empty($res['data'])) {
                        $content['token'] = $res['data']['id'];
                        $content['oss_url'] = $res['data']['url'];
                        $extra = json_encode($content);
                    } else if ('error' == $res) {
                        AuthInfo::where('id', $row['id'])
                            ->update([
                                'is_pushed' => Constant::COMMON_STATUS_FAILED,
                            ]);
                        Log::warning('module=' . $this->signature . "\tbizNo=" . $row['biz_no'] . "\tmsg=get device data error");
                        continue;
                    } else {
                        Log::warning('module=' . $this->signature . "\tbizNo=" . $row['biz_no'] . "\tmsg=request device-service api fail");
                        continue;
                    }
                } else if ($type == Constant::DATA_TYPE_FACE) {
                    $content = [];
                    $items = explode(';', $extra);
                    foreach ($items as $item) {
                        $content['oss_urls'][] = OssClient::getUrlByFilename(Constant::FILE_TYPE_FACE,
                            $item);
                    }
                    $extra = json_encode($content);
                } else if ($type == Constant::DATA_TYPE_WHITE_KNIGHT) {
                    // do nothing
                } else {
                    $data = [
                        'third' => $row['tp'],
                        'data' => $extra,
                    ];
                    $extra = json_encode($data);
                }
                #推送给风控
                $this->sendAuthData($row['user_id'], $type, $row['biz_no'], $extra);
            }
        }

        Log::info('module=' . $this->signature . "\tmsg=ends");
    }

    /*
     * 通知风控--发送mns消息
     */
    private function sendAuthData($userId, $type, $bizNo, $data)
    {
        $mnsType = $this->getMnsType($type);
        $deviceRes = DeviceInfo::where('user_id', $userId)->first()->toArray();
        if (!$deviceRes || $deviceRes['status'] != 1) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR, '获取用户设备信息失败，请重试');
        }
        $mnsResult = AuthCenter::sendAsyncDataToRisk(Users::where('id', $userId)->first(),
            $type, $mnsType, $deviceRes, $data);

        #修改授权记录附加信息和修改推送状态
        if ($mnsResult) {
            AuthInfo::where('biz_no', $bizNo)->update(['is_pushed' => 1]);
        }
    }


    /*
     * 获取mns_type
     */
    private function getMnsType($type)
    {
        if (in_array($type, Constant::MSN_TYPE_WITH_DATA_TYPE_MAP_AUTH)) {
            return 1;
        } else if ($type == Constant::DATA_TYPE_DEVICE_INFO) {
            return 2;
        } else if (in_array($type, Constant::MSN_TYPE_WITH_DATA_TYPE_MAP_TPSDK)) {
            return 3;
        }
    }


}
