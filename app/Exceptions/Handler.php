<?php

namespace App\Exceptions;

use App\Consts\ErrorCode;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
        NotFoundHttpException::class,
        CustomException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param Exception $exception
     * @return mixed|void
     * @throws Exception
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Exception $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        Log::warning("path=" . $request->getPathInfo() . "\tcode=" . $exception->getCode() . "\tmessage=" . $exception->getMessage() . "\ttrace=" . str_replace("\n", ';', $exception->getTraceAsString()));
        if ($exception instanceof CustomException) {
            return response()->json([
                'code' => $exception->getCode(),
                'msg' => $exception->getMessage()
            ]);
        } elseif ($exception instanceof NotFoundHttpException) {
            return response('', 404);
        } elseif ($exception instanceof ValidationException) {
            return response()->json([
                'code' => ErrorCode::COMMON_PARAM_ERROR,
                'msg' => ErrorCode::CODE_MSG_DICT[ErrorCode::COMMON_PARAM_ERROR],
            ]);
        }
        if (!env('APP_DEBUG', true)) {
            return response()->json(['code' => ErrorCode::COMMON_SYSTEM_ERROR, 'msg' => 'system error']);;
        }
        return response()->json(['code' => 1, 'msg' => $exception->getMessage()]);
    }
}
