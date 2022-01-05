<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-08
 * Time: 14:24
 */

namespace App\Http\Controllers\Apis;

use App\Common\OssClient;
use App\Common\PushClient;
use App\Common\Utils;
use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Consts\Scheme;
use App\Exceptions\CustomException;
use App\Helpers\AuthCenter;
use App\Helpers\MainCardHelper;
use App\Http\Controllers\Controller;
use App\Models\DeviceInfo;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    private function genRecommends($user)
    {
        $recommends = [];
        $recommends[] = Utils::genBannerItem(OssClient::getUrlByFilename(Constant::FILE_TYPE_BANNER, '水莲金条.png'),
            sprintf(Scheme::APP_WEBVIEW_NONEED_LOGIN_FORMAT, rawurlencode(env('H5_BASE_URL') . '/sl/app/produce'), '水莲金条'),
            false);
        $recommends[] = Utils::genBannerItem(OssClient::getUrlByFilename(Constant::FILE_TYPE_BANNER, '水莲官方.png'),
            sprintf(Scheme::APP_WEBVIEW_NONEED_LOGIN_FORMAT, rawurlencode(env('H5_BASE_URL') . '/sl/doc/5d53aa8ccae97b62f9176911?style=1'), '水莲官方'),
            true);
        $recommends[] = Utils::genBannerItem(OssClient::getUrlByFilename(Constant::FILE_TYPE_BANNER, '抵制暴力.png'),
            sprintf(Scheme::APP_WEBVIEW_NONEED_LOGIN_FORMAT, rawurlencode(env('H5_BASE_URL') . '/sl/doc/5d5290c3741f22005062c8f4?style=1'), '抵制暴力'),
            true);
        return $recommends;
    }

    private function genBottoms($user)
    {
        $bottoms = [];
        $bottoms[] = [
            'icon' => OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, 'ic_jisu.png'),
            'title' => '极速到账',
            'sub_title' => '最快30分钟下款',
        ];
        $bottoms[] = [
            'icon' => OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, 'ic_dixi.png'),
            'title' => '便捷申请',
            'sub_title' => '全程在线申请',
        ];
        $bottoms[] = [
            'icon' => OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, 'ic_fenqi.png'),
            'title' => '灵活分期',
            'sub_title' => '3/6/12期任选',
        ];
        return $bottoms;
    }

    public function ping(Request $request)
    {
        return $this->render([]);
    }

    public function index(Request $request)
    {
        $user = Utils::resolveUser($request);

        $data = [
            'has_new_msg' => 0,
            'main_card' => MainCardHelper::genMainCard($user),
            'sub_card' => '',
            'recommends' => $this->genRecommends($user),
            'bottoms' => $this->genBottoms($user),
            'extra' => [
                'service_tel' => Constant::CUSTOMER_SERVICE_TEL,
                'statement' => '水莲金条不向学生提供贷款服务',
            ],
        ];
        return $this->render($data);
    }

    public function startUp(Request $request)
    {
        return $this->render([]);
    }

    public function pushToken(Request $request)
    {
        $user = $request->user;
        $validatedData = $request->validate(
            [
                'push_token' => 'required',
            ]
        );
        $pushToken = $validatedData['push_token'];
        DeviceInfo::updateOrCreate(['user_id' => $user['id'],],
            [
                'push_token' => $pushToken,
            ]);
        PushClient::tokenReport($user['id'], Utils::getDeviceType($request), $pushToken);
        return $this->render([]);
    }

    /**
     * 客户端主动上报的数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws CustomException
     */
    public function pandora(Request $request)
    {
        $user = Utils::resolveUser($request);
        if ($user) {
            $validatedData = $request->validate(
                [
                    'type' => 'required|regex:/^[0-9]+$/',
                    'data' => 'required|json',
                ]
            );
            $type = $validatedData['type'];
            $data = $validatedData['data'];
            $data = json_decode($data, true);
            if (!$data) {
                throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, 'data必须非空');
            }
            AuthCenter::handleReport($user['id'], $type, $data);
        }

        return $this->render([]);
    }

    public function upgrade(Request $request)
    {
        $version = $request->header('Version', '1.0.0');
        $deviceType = Utils::getDeviceType($request);

        $url = '';
        $forceUpdate = 0;
        $iosLastVersion = '2.0.4';

        if(Constant::DEVICE_TYPE_IOS == $deviceType) {
            if(version_compare($version, $iosLastVersion, '<')) {
                $url = 'https://api.ishuilian.com/h5/autoDownLoad.html';
                $forceUpdate = 1;
            }
        }

        return $this->render(['url' => $url, 'force_update' => $forceUpdate]);
    }
}