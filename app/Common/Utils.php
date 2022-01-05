<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-08
 * Time: 16:44
 */

namespace App\Common;


use App\Consts\Constant;
use App\Consts\Contract;
use App\Consts\Scheme;
use App\Models\Contracts;
use App\Models\DeviceInfo;
use App\Models\MongoDB\DeviceHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Utils
{
    public static function genBizNo(int $length = 32)
    {
        $strs = "1234567890";
        while (strlen($strs) <= $length) {
            $strs .= $strs;
        }
        $code = substr(str_shuffle($strs), mt_rand(0, strlen($strs) - $length - 1), $length);
        return $code;
    }

    public static function getDeviceType($request)
    {
        $extra = $request->header('User-Agent', '');
        $deviceType = Constant::DEVICE_TYPE_IOS;
        if (strpos($extra, 'Android') !== false) {
            $deviceType = Constant::DEVICE_TYPE_ANDROID;
        }
        return $deviceType;
    }

    /**
     * 保存设备信息（to Mongodb）
     * @param $userId
     * @param $request
     */
    public static function saveDeviceInfo($userId, Request $request)
    {
        $imei = $request->header('A', '');
        $deviceId = $request->header('B', '');
        $extra = $request->header('User-Agent', '');
        $deviceType = self::getDeviceType($request);
        $ip = $request->ip();
        $version = $request->header('Version', 'default');
        if (empty($deviceId)) { // 无设备id，忽略
            return;
        }
        DeviceInfo::updateOrCreate(
            ['user_id' => $userId],
            [
                'device_type' => $deviceType,
                'device_id' => $deviceId,
                'imei' => $imei,
                'version' => $version,
                'extra' => $extra,
                'status' => Constant::COMMON_STATUS_SUCCESS,
            ]
        );
        try {
            DeviceHistory::create(
                [
                    'user_id' => $userId,
                    'device_id' => $deviceId,
                    'imei' => $imei,
                    'client_ip' => $ip,
                    'extra' => $extra,
                    'version' => $version,
                    'time' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Exception $exception) {
            Log::warning("module=mongo\tmsg=save device info error\terror=" . $exception->getMessage());
            AlertClient::sendAlertEmail($exception, $request);
        }

    }

    public static function genNavigationItem($icon = '', $link = '', $title = '', $tip = '',
                                             $tag = '', $color = '', $statisticsId = '')
    {
        return [
            'icon' => $icon,
            'link' => $link,
            'title' => $title,
            'tip' => $tip,
            'tag' => $tag,
            'color' => $color,
            'statistics_id' => $statisticsId,
        ];
    }

    public static function genSectionItem($navigations = [], $title = '', $tip = '')
    {
        return [
            'navigations' => $navigations,
            'title' => $title,
            'tip' => $tip,
        ];
    }

    public static function genBannerItem($img = '', $link = '', $needLogin = false)
    {
        return [
            'img' => $img,
            'link' => $link,
            'need_login' => $needLogin,
        ];
    }

    /**
     * 对身份证号码做掩码处理
     * @param $identity
     * @return string
     */
    public static function maskIdentity($identity)
    {
        if (empty($identity)) {
            return '';
        }
        return substr($identity, 0, 3) . '************' . substr($identity, 15, 3);
    }

    /**
     * 手机号掩码处理
     * @param $phone
     * @return bool|string
     */
    public static function maskPhone($phone)
    {
        if (empty($phone)) {
            return '';
        }
        return substr($phone, 0, 3) . '****' . substr($phone, -4);
    }

    /**
     * 银行卡号掩码处理
     * @param $cardNo
     * @return bool|string
     */
    public static function maskCardNo($cardNo)
    {
        if (empty($cardNo)) {
            return '';
        }
        return substr($cardNo, 0, 4) . '****';
    }

    /**
     * 对姓名做掩码处理
     * @param $name
     * @return string
     */
    public static function maskChineseName($name)
    {
        if (empty($name)) {
            return '';
        }
        $l = mb_strlen($name);
        switch ($l) {
            case 2:
                $maskedName = '*' . mb_substr($name, $l - 1, 1);
                break;
            case 3:
                $maskedName = '**' . mb_substr($name, $l - 1, 1);
                break;
            default:
                $maskedName = '**' . mb_substr($name, $l - 1, 1);
                break;
        }
        return $maskedName;
    }

    public static function resolveUser(Request $request)
    {
        $token = $request->header('Token');
        return Token::getUserByToken($token);
    }

    /**
     * 获取指定日期当月的最后一天
     * @param $d 2019-01-01
     * @return false|string 2019-01-31
     */
    public static function getLastDayOfMonth($d)
    {
        $beginDate = date('Y-m-01', strtotime($d));
        return date('Y-m-d', strtotime("$beginDate +1 month -1 day"));
    }

    /**
     * 获取指定日期下个月的第一天
     * @param $d 2019-01-01
     * @return false|string 2019-01-31
     */
    public static function getNextMonth($d)
    {
        $beginDate = date('Y-m-01', strtotime($d));
        return date('Y-m-d', strtotime("$beginDate +1 month"));
    }

    /**
     * 根据身份证号获取年龄
     * @param $identity
     * @return int
     */
    public static function getAgeByIdentity($identity)
    {
        return intval(date('Y')) - intval(substr($identity, 6, 4));
    }

    /**
     * 根据身份证号获取性别
     * @param $idCard
     * @return int
     */
    public static function getSexByIdentity($idCard)
    {
        $position = (strlen($idCard) == 15 ? -1 : -2);
        if (substr($idCard, $position, 1) % 2 == 0) {
            return Constant::GENDER_WOMEN;
        }
        return Constant::GENDER_MEN;
    }

    /**
     * 获取间隔的时间
     * @param $startDate 开始日期
     * @param $endDate 结束日期
     * @param $type 类型 day：天，month：月，year：年，hour：小时，minute：分钟
     * @param $trunc 取整，intval：直接取整，round：四舍五入，ceil：向上取整，floor：向下取整
     * @return int
     */
    public static function getGapTime($startDate, $endDate, $type = 'day', $trunc = 'ceil')
    {
        $gapTimes = abs(strtotime($endDate) - strtotime($startDate));
        $stepTime = 60;

        switch ($type) {
            case 'hour':
                $stepTime = 60 * 60;
                break;
            case 'day':
                $stepTime = 60 * 60 * 24;
                break;
            case 'month':
                $stepTime = 60 * 60 * 24 * 30;
                break;
            case 'year':
                $stepTime = 60 * 60 * 24 * 365;
                break;
        }

        $gapResult = intval($gapTimes / $stepTime);

        switch ($trunc) {
            case 'round':
                $gapResult = round($gapTimes / $stepTime);
                break;
            case 'ceil':
                $gapResult = ceil($gapTimes / $stepTime);
                break;
            case 'floor':
                $gapResult = floor($gapTimes / $stepTime);
                break;
        }

        return $gapResult;
    }

    /**
     * 概率分配
     * @param $arrRate 参与分配的概率数组
     * @return mixed
     */
    public static function hitProbability(array $arrRate)
    {
        $result = null;
        $randRate = mt_rand(1, array_sum($arrRate));

        foreach ($arrRate as $key => $rate) {
            if ($randRate <= $rate) {
                $result = $key;
                break;
            } else {
                $randRate -= $rate;
            }
        }

        return $result;
    }

    /**
     * 替换请求参数
     * @param $url
     * @param $param
     * @param $val
     * @return mixed
     */
    public static function replaceUrlQuery($url, $param, $val)
    {
        $urlArr = parse_url($url);
        parse_str($urlArr['query'], $queryArr);
        $queryArr[$param] = $val;
        $urlArr['query'] = http_build_query($queryArr);
        return self::http_build_url($urlArr);
    }

    public static function http_build_url($url_arr)
    {
        $new_url = $url_arr['scheme'] . "://" . $url_arr['host'];
        if (!empty($url_arr['port']))
            $new_url = $new_url . ":" . $url_arr['port'];
        $new_url = $new_url . $url_arr['path'];
        if (!empty($url_arr['query']))
            $new_url = $new_url . "?" . $url_arr['query'];
        if (!empty($url_arr['fragment']))
            $new_url = $new_url . "#" . $url_arr['fragment'];
        return $new_url;
    }

    /**
     * 获取合同数据
     * @param $order
     * @return array
     */
    public static function getContractData($order)
    {
        $contractData = Contracts::where('relation_id', $order['id'])
            ->where('relation_type', Contract::RELATION_TYPE_ORDER)
            ->where('status', Constant::COMMON_STATUS_SUCCESS)->get()->toArray();
        $contracts = [];
        // 是否需要从笑脸拉合同
        $needContractFromFacebank = true;
        foreach ($contractData as $contractItem) {
            if ($contractItem['contract_type'] == Contract::AGREEMENT_FACE_BANK) {
                $needContractFromFacebank = false;
            }
            $title = '《' . $contractItem['title'] . '》';
            $contracts[] = Utils::genNavigationItem('',
                sprintf(Scheme::APP_WEBVIEW_FORMAT, rawurlencode($contractItem['h5_view_url']),
                    rawurlencode($title)),
                $title, '', '', '', 'H06001');
        }
        if ($needContractFromFacebank) {
            // 笑脸的借款协议是pdf版的，在线不做转存&格式转换，离线任务进行补偿
            $contractUrl = CapitalClient::queryContract($order['biz_no']);
            if ($contractUrl) {
                try {
                    Contracts::create(
                        [
                            'relation_type' => 0,
                            'relation_id' => $order['id'],
                            'title' => '借款协议',
                            'contract_sn' => $order['biz_no'],
                            'contract_type' => Contract::AGREEMENT_FACE_BANK,
                            'original_pdf' => $contractUrl,
                            'sign_pdf' => '',
                            'h5_view_url' => '',
                            'status' => Constant::COMMON_STATUS_INIT,
                            'is_upload' => Constant::COMMON_STATUS_INIT,
                        ]
                    );
                } catch (\Exception $e) {
                    Log::warning("module=getContractData\torder_id=" . $order['id'] . "\tmsg=" . $e->getMessage());
                }
                $contracts[] = Utils::genNavigationItem('',
                    sprintf(Scheme::APP_WEBVIEW_FORMAT, rawurlencode($contractUrl), rawurlencode('《借款协议》')),
                    '《借款协议》');
            }
        }
        return $contracts;
    }

    /**
     * 获取认证合同数据
     * @param $order
     * @return array
     */
    public static function getContractDataByAuth($user)
    {
        $contractData = Contracts::where('relation_id', $user['id'])
            ->where('relation_type', Contract::RELATION_TYPE_USER)
            ->where('status', Constant::COMMON_STATUS_SUCCESS)->get()->toArray();
        $contracts = [];
        foreach ($contractData as $contractItem) {
            $title = '《' . $contractItem['title'] . '》';
            $contracts[] = Utils::genNavigationItem('',
                sprintf(Scheme::APP_WEBVIEW_FORMAT, rawurlencode($contractItem['h5_view_url']),
                    rawurlencode($title)),
                $title, '', '', '', 'H06002');
        }

        return $contracts;
    }

    /**
     * 获取开户页面注入信息
     * @param $cardNo
     * @return array
     */
    public static function getInterceptInfoForOpenAccount($cardNo)
    {
        $script = <<<SCRIPT
javascript:\$('#bankcardNo').val("$cardNo");\$('#bankcardNo').change();\$('#bankcardNo').blur();\$('#bankcardNo').attr("readonly","readonly");
SCRIPT;

        return [
            [
                'target_url' => env('BANK_PAGE_OF_OPEN_ACCOUNT'),
                'js_content' => $script,
                'js_url' => '',
            ],
        ];
    }

    /**
     * 根据逾期天数获取文案
     * @param $overdueDays
     * @return array
     */
    public static function getDescByOverdueDays($overdueDays)
    {
        if ($overdueDays <= 3) {
            $tip = '您已逾期';
            $level = 0;
            $desc = '您的借款正在使用中，培养良好的信用习惯，请按时还款哟～';
        } else if ($overdueDays <= 7) {
            $tip = '您已严重逾期';
            $level = 1;
            $desc = '您的借款已严重逾期，请尽快处理您的欠款，如有疑问请联系客服400-606-707';
        } else if ($overdueDays <= 15) {
            $tip = '您已严重逾期';
            $level = 2;
            $desc = '您的借款已严重逾期，我司正在准备上报征信的材料。请尽快处理您的欠款，如有疑问请联系客服400-606-707';
        } else {
            $tip = '您已严重逾期';
            $level = 3;
            $desc = '您的借款已严重逾期，我司正在准备上报征信的材料。请尽快处理您的欠款，如有疑问请联系客服400-606-707';
        }
        return [
            'tip' => $tip,
            'desc' => $desc,
            'level' => $level,
        ];
    }


    /**
     * @desc 通过新的Key重建数组索引
     * @action arrayRebuild
     * @author  liuhao
     * @data 2019-08-22
     * @param  array $array
     * @param $key
     * @return  array
     */
    public static function arrayRebuild(array $array, $key)
    {
        $data = array();

        if (empty($array) || empty($key)) {
            return $data;
        }

        foreach ($array as $info) {
            if (isset($info[$key])) {
                $data[$info[$key]] = $info;
            }
        }

        return $data;
    }


    /**
     * @desc   毫秒转换为秒
     * @action millisecondToSecondAction
     * @param $millisecond
     * @return int
     * @author liuhao
     * @date   2019-08-26
     */
    public static function millisecondToSecond($millisecond)
    {
        return intval($millisecond / 1000);
    }

    /**
     * @desc   秒转转为毫秒
     * @action secondToMillisecondAction
     * @param $second
     * @return float|int
     * @author liuhao
     * @date   2019-08-26
     */
    public static function secondToMillisecond($second)
    {
        return intval($second * 1000);
    }

    /**
     * 拼接渠道信息
     * @param $request
     * @param $channel
     * @return string
     */
    public static function genChannelInfo($request, $channel)
    {
        $deviceType = self::getDeviceType($request);
        $deviceName = ($deviceType == Constant::DEVICE_TYPE_IOS) ? 'IOS' : 'Android';
        $version = $request->header('Version', 'default');
        return sprintf('APP-%s-%s-%s', $channel, $deviceName, $version);
    }
}
