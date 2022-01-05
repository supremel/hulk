<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-11
 * Time: 10:24
 */

namespace App\Http\Controllers\Apis;

use App\Common\OssClient;
use App\Consts\Banks;
use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Events\ProdureEvent;
use App\Exceptions\CustomException;
use App\Http\Controllers\Controller;
use App\Models\AddrInfo;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;

class CommonController extends Controller
{

    public function ossToken(Request $request)
    {
        $user = $request->user;
        $tokenInfo = OssClient::getStsAccessToken($user['uid'], OssClient::ROLE_TYPE_WRITE);
        if (!$tokenInfo) {
            throw new CustomException(ErrorCode::COMMON_SYSTEM_ERROR);
        }
        $data = [
            'bucket' => OssClient::getBucket(OssClient::RESOURCE_TYPE_PRIVATE),
            'access_key_id' => $tokenInfo['AccessKeyId'],
            'access_key_secret' => $tokenInfo['AccessKeySecret'],
            'security_token' => $tokenInfo['SecurityToken'],
            'end_point' => env('OSS_ENDPOINT_' . OssClient::RESOURCE_TYPE_PRIVATE),
            'expiration' => $tokenInfo['Expiration'],
        ];
        return $this->render($data);
    }

    public function bankList(Request $request)
    {
        $banks = Banks::LIST;
        foreach ($banks as &$bank) {
            $bank['icon'] = empty($bank['icon']) ? $bank['icon'] : OssClient::getUrlByFilename(Constant::FILE_TYPE_ICON, $bank['icon']);
        }
        $data = [
            'banks' => $banks,
        ];
        return $this->render($data);
    }

    public function addrInfo(Request $request, Dispatcher $event)
    {
        $event->dispatch(new ProdureEvent(1, 1));
        $addrs = AddrInfo::all()->toArray();
        $data = [];
        $data['province'] = [];
        $data['city'] = [];
        $data['county'] = [];
        foreach ($addrs as $addr) {
            if (0 == $addr['province']) {
                $data['province'][] = [
                    'parent_id' => 0,
                    'code' => $addr['code'],
                    'name' => $addr['name'],
                ];
                continue;
            }
            if (0 == $addr['city']) {
                $data['city'][] = [
                    'parent_id' => $addr['province'],
                    'code' => $addr['code'],
                    'name' => $addr['name'],
                ];
                continue;
            }
            $data['county'][] = [
                'parent_id' => $addr['city'],
                'code' => $addr['code'],
                'name' => $addr['name'],
            ];
        }

        return $this->render($data);
    }

}