<?php
/**
 * Created by PhpStorm.
 * User: hexuefei
 * Date: 2019-09-24
 * Time: 14:38
 */

namespace App\Http\Controllers\H5;


use App\Consts\ErrorCode;
use App\Exceptions\CustomException;
use Illuminate\Http\Request;

class UserController extends \App\Http\Controllers\Apis\UserController
{
    public function verifyCode(Request $request)
    {
        return parent::verifyCode($request);
    }

    public function login(Request $request)
    {
        try {
            parent::login($request);
        } catch (CustomException $exception) {
            if (ErrorCode::USER_FROM_API != $exception->getCode()) { // 忽略跳h5还款页面
                throw $exception;
            }
        }
        return $this->render([]);
    }
}