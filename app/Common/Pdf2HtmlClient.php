<?php
/**
 * PDF转HTML服务相关
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-07-29
 * Time: 14:31
 */

namespace App\Common;

use Illuminate\Support\Facades\Log;

class Pdf2HtmlClient extends HttpClient
{
    /**
     * @param $title 页面标题
     * @param $filename 文件名
     * @param $pdfUrl pdf文件地址
     * @return string
     */
    public static function doConvert($title, $filename, $pdfUrl)
    {
        $path = '/pdf';
        $data = [
            'name' => $filename,
            'title' => $title,
            'url' => $pdfUrl,
        ];
        $response = self::_curl(env('PDF2HTML_SERVICE_URL') . $path,
            self::METHOD_POST, $data, '', 10);

        if ($response) {
            $ret = json_decode($response, true);
            if (0 == $ret['code']) {
                return $ret['url'];
            }
            Log::warning('module=pdf2html\terror=' . $response);
        }
        return '';
    }

}

