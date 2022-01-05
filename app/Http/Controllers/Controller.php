<?php

namespace App\Http\Controllers;

use App\Consts\ErrorCode;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * @param $data
     * @return \Illuminate\Http\JsonResponse
     */
    public function render($data = [])
    {
        return response()->json(['code' => ErrorCode::SUCCESS,
            'msg' => ErrorCode::CODE_MSG_DICT[ErrorCode::SUCCESS],
            'data' => $data]);
    }

}
