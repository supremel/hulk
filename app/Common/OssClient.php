<?php
/**
 * OSS操作相关
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-09
 * Time: 16:04
 */

namespace App\Common;

use App\Consts\Constant;
use App\Consts\ErrorCode;
use App\Exceptions\CustomException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OSS\Core\OssException;

class OssClient extends HttpClient
{
    const RESOURCE_TYPE_PRIVATE = 0;
    const RESOURCE_TYPE_PUBLIC = 1;

    const PATH_DICT = [
        Constant::FILE_TYPE_ID_CARD_FRONT => [
            'type' => self::RESOURCE_TYPE_PRIVATE,
            'path_format' => '%s/%d/', //  uid/file_type/
        ],
        Constant::FILE_TYPE_ID_CARD_BACK => [
            'type' => self::RESOURCE_TYPE_PRIVATE,
            'path_format' => '%s/%d/', //  uid/file_type/
        ],
        Constant::FILE_TYPE_FACE => [
            'type' => self::RESOURCE_TYPE_PRIVATE,
            'path_format' => '%s/%d/', //  uid/file_type/
        ],
        Constant::FILE_TYPE_ICON => [
            'type' => self::RESOURCE_TYPE_PUBLIC,
            'path' => 'icons/',
        ],
        Constant::FILE_TYPE_BANNER => [
            'type' => self::RESOURCE_TYPE_PUBLIC,
            'path' => 'banners/',
        ],
        Constant::FILE_TYPE_CONTRACT => [
            'type' => self::RESOURCE_TYPE_PRIVATE,
            'path' => 'contracts/',
        ],

    ];

    const ROLE_TYPE_READONLY = 0;
    const ROLE_TYPE_WRITE = 1;

    const STS_ENDPOINT = 'https://sts.aliyuncs.com';
    const STS_ACCESS_TOKEN_TIMEOUT = 60 * 60;
    const ACCESS_URL_TIMEOUT = 60 * 60;
    const ACCESS_URL_FOR_AK_TIMEOUT = 3 * 30 * 24 * 60 * 60;

    /**
     * @param $params
     * @return string
     */
    private static function _genStatSign($params)
    {
        ksort($params);
        $sign = '';
        foreach ($params as $key => $value) {
            $sign .= sprintf('&%s=%s', rawurlencode($key), rawurlencode($value));
        }
        $sign = 'GET&%2F&' . rawurlencode(substr($sign, 1));
        $str = hash_hmac('sha1', $sign, env('OSS_ACCESS_KEY_SECRET_' . self::RESOURCE_TYPE_PRIVATE) . '&',
            true);
        return trim(base64_encode($str));
    }

    /**
     * 上传文件
     * @param $fileType
     * @param $filename
     * @param $content
     * @return string
     * @throws CustomException
     */
    public static function upload($fileType, $filename, $content)
    {
        $fullFilePath = '';
        try {
            if (empty(self::PATH_DICT[$fileType])) {
                throw new CustomException(ErrorCode::COMMON_PARAM_ERROR, 'file_type error');
            }
            $config = self::PATH_DICT[$fileType];
            if (self::RESOURCE_TYPE_PUBLIC == $config['type']) { // 公有bucket
                $oss = new \OSS\OssClient(
                    env('OSS_ACCESS_KEY_ID_' . self::RESOURCE_TYPE_PUBLIC),
                    env('OSS_ACCESS_KEY_SECRET_' . self::RESOURCE_TYPE_PUBLIC),
                    env('OSS_ENDPOINT_' . self::RESOURCE_TYPE_PUBLIC));
                $path = self::PATH_DICT[$fileType]['path'];
                $fullFilePath = $path . $filename;
                $oss->putObject(env('OSS_BUCKET_' . self::RESOURCE_TYPE_PUBLIC),
                    $fullFilePath, $content);
            } else { // 私有bucket
                $tokenInfo = OssClient::getStsAccessToken(date('YmdHis') . rand(0, 10000), OssClient::ROLE_TYPE_WRITE);
                $oss = new \OSS\OssClient($tokenInfo['AccessKeyId'],
                    $tokenInfo['AccessKeySecret'],
                    env('OSS_ENDPOINT_' . OssClient::RESOURCE_TYPE_PRIVATE),
                    false,
                    $tokenInfo['SecurityToken']
                );
                $fullFilePath = $config['path'] . $filename;
                $oss->putObject(OssClient::getBucket(OssClient::RESOURCE_TYPE_PRIVATE),
                    $fullFilePath, $content);
            }

            return $fullFilePath;
        } catch (OssException $exception) {
            Log::warning("module=oss\tmsg=upload failed\terror=" . $exception->getMessage());
            return $fullFilePath;
        }
    }

    /**
     * 获取sts token信息
     * @param $sessionName
     * @param int $roleType
     * @return array|null
     */
    public static function getStsAccessToken($sessionName, $roleType = self::ROLE_TYPE_READONLY)
    {
        $roleArn = env('ROLE_' . $roleType);
        $params = [
            'Action' => 'AssumeRole',
            'RoleArn' => $roleArn,
            'RoleSessionName' => $sessionName,
            'Format' => 'JSON',
            'DurationSeconds' => self::STS_ACCESS_TOKEN_TIMEOUT,
            'Version' => '2015-04-01',
            'AccessKeyId' => env('OSS_ACCESS_KEY_ID_' . self::RESOURCE_TYPE_PRIVATE),
            'SignatureVersion' => '1.0',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce' => Str::random(16),
            'Timestamp' => date("Y-m-d\TH:i:s\Z", strtotime('-8 hour')),
        ];
        $sign = self::_genStatSign($params);
        $params['Signature'] = $sign;
        $ret = self::_curl(self::STS_ENDPOINT, self::METHOD_GET, $params, '', 10);
        if ($ret) {
            $res = json_decode($ret, true);
            if (!empty($res['Credentials'])) {
                return $res['Credentials'];
            }
        }
        Log::warning("module=oss\tmsg=get sts token failed\tret=" . $ret);
        return null;
    }

    /**
     * 通过sts获取访问地址
     * @param $filename
     * @return string
     */
    private static function _getAccessUrlBySts($filename)
    {
        $cacheKey = 'oss_' . $filename;
        try {
            $cachedUrl = RedisClient::get($cacheKey);
        } catch (\Exception $e) {
            $cachedUrl = '';
        }
        if ($cachedUrl) {
            return $cachedUrl;
        }
        $sessionName = Str::random(32);
        $tokenInfo = self::getStsAccessToken($sessionName, self::ROLE_TYPE_READONLY);
        if (!$tokenInfo) {
            return '';
        }
        $signedUrl = '';
        try {
            $oss = new \OSS\OssClient($tokenInfo['AccessKeyId'],
                $tokenInfo['AccessKeySecret'],
                env('OSS_ENDPOINT_' . self::RESOURCE_TYPE_PRIVATE),
                false,
                $tokenInfo['SecurityToken']);
            $signedUrl = $oss->signUrl(env('OSS_BUCKET_' . self::RESOURCE_TYPE_PRIVATE),
                $filename, self::ACCESS_URL_TIMEOUT);
            RedisClient::setWithExpire($cacheKey, $signedUrl, self::ACCESS_URL_TIMEOUT);
        } catch (\Exception $exception) {
            Log::warning("module=oss\tmsg=get access url failed\terror=" . $exception->getMessage());
        }


        return $signedUrl;
    }

    /**
     * 通过ak获取访问地址
     * @param $filename
     * @return string
     */
    private static function _getAccessUrlByAk($filename)
    {
        $cacheKey = 'oss_' . $filename;
        try {
            $cachedUrl = RedisClient::get($cacheKey);
        } catch (\Exception $e) {
            $cachedUrl = '';
        }
        if ($cachedUrl) {
            return $cachedUrl;
        }
        $signedUrl = '';
        try {
            $oss = new \OSS\OssClient(
                env('OSS_ACCESS_KEY_ID_' . self::RESOURCE_TYPE_PUBLIC),
                env('OSS_ACCESS_KEY_SECRET_' . self::RESOURCE_TYPE_PUBLIC),
                env('OSS_ENDPOINT_' . self::RESOURCE_TYPE_PUBLIC));
            $signedUrl = $oss->signUrl(env('OSS_BUCKET_' . self::RESOURCE_TYPE_PRIVATE),
                $filename, self::ACCESS_URL_FOR_AK_TIMEOUT);
            RedisClient::setWithExpire($cacheKey, $signedUrl, self::ACCESS_URL_FOR_AK_TIMEOUT);
        } catch (\Exception $exception) {
            Log::warning("module=oss\tmsg=get access url failed\terror=" . $exception->getMessage());
        }


        return $signedUrl;
    }

    /**
     * 根据文件名获取地址
     * @param $type
     * @param $filename
     * @param $isStsAccess boolean  是否通过sts访问
     * @return string
     */
    public static function getUrlByFilename($type, $filename, $isStsAccess = false)
    {
        if (!$filename) {
            return '';
        }
        if (false !== strpos($filename, 'http')) {
            return $filename;
        }
        $ossType = self::PATH_DICT[$type]['type'];
        if ($ossType == self::RESOURCE_TYPE_PUBLIC) {
            $url = env('OSS_FILE_ACCESS_URL_' . $ossType) . '/' . self::PATH_DICT[$type]['path'] . $filename;
        } else {
            if ($isStsAccess) {
                $url = self::_getAccessUrlBySts($filename);
            } else {
                $url = self::_getAccessUrlByAk($filename);
            }

        }
        return $url;
    }

    /**
     * 根据资源类型来获取bucket
     * @param $resourceType
     * @return mixed
     */
    public static function getBucket($resourceType)
    {
        return env('OSS_BUCKET_' . $resourceType);
    }
}