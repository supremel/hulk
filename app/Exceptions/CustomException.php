<?php
/**
 * 自定义异常类型
 * User: hexuefei
 * Date: 2019-03-23
 * Time: 10:34
 */

namespace App\Exceptions;


use App\Consts\ErrorCode;
use Throwable;

class CustomException extends \Exception
{
    public function __construct(int $code = 0, string $message = "", Throwable $previous = null)
    {
        $msg = (isset(ErrorCode::CODE_MSG_DICT[$code]) ? ErrorCode::CODE_MSG_DICT[$code] : '');
        if (!$message) {
            $message = $msg;
        }
        parent::__construct($message, $code, $previous);
    }

}