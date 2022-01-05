<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

if (env('APP_ENV') == 'stage' || env('APP_ENV') == 'local') {
    Route::prefix('v1')->group(function () {
        // 不需要校验签名
        Route::middleware([])->group(function () {
            //crm贷后
            Route::post('/crm/today_overdue', 'Crms\OverdueController@todayOverdue');
            Route::post('/crm/user_overdue', 'Crms\OverdueController@userOverdue');
            Route::post('/crm/overdue_status', 'Crms\OverdueController@overdueOrderStatus');
            Route::post('/crm/overdue_plan', 'Crms\OverdueController@overdueOrderPlan');
            Route::post('/crm/overdue_amount', 'Crms\OverdueController@overdueOrderTotalAmount');
            // apis for 风控
            Route::post('/risk_manage/profile', 'RiskManage\DataController@profile');
            Route::post('/risk_manage/credit_detail', 'RiskManage\DataController@creditDetail');
            Route::post('/risk_manage/relationship', 'RiskManage\DataController@relationship');
            Route::post('/risk_manage/credit_history', 'RiskManage\DataController@creditHistory');
            Route::post('/risk_manage/order_history', 'RiskManage\DataController@orderHistory');
        });
    });
}

// v1
Route::prefix('v1')->group(function () {
    // 需要校验签名
    Route::middleware(['api_sign'])->group(function () {
        // 需要校验登录
        Route::middleware(['api_auth'])->group(function () {
            Route::post('/user/logout', 'Apis\UserController@logout');
            Route::post('/user/profile', 'Apis\UserController@profile');
            Route::post('/home/push_token', 'Apis\HomeController@pushToken');
            Route::post('/tp_auth', 'Apis\TpAuthController@index');
            Route::post('/oss_token', 'Apis\CommonController@ossToken');

            Route::post('/profile/identity_info', 'Apis\ProfileController@identityInfo');
            Route::post('/profile/basic', 'Apis\ProfileController@basic');
            Route::post('/profile/real_name', 'Apis\ProfileController@realName');
            Route::post('/profile/basic_info', 'Apis\ProfileController@basicInfo');
            Route::post('/profile/relationship_info', 'Apis\ProfileController@relationshipInfo');
            Route::post('/profile/relationship', 'Apis\ProfileController@relationship');
            Route::post('/profile/bank_info', 'Apis\ProfileController@bankInfo');
            Route::post('/profile/tp_auth_list', 'Apis\ProfileController@tpAuthList');

            Route::post('/user/bank_verify_code', 'Apis\UserController@bankVerifyCode');
            Route::post('/user/bank_bind_card', 'Apis\UserController@bankBindCard');
            Route::post('/user/latest_bills', 'Apis\UserController@latestBills');
            Route::post('/user/order_list', 'Apis\UserController@orderList');
            Route::post('/user/order_info', 'Apis\UserController@orderInfo');
            Route::post('/user/order_detail', 'Apis\UserController@orderDetail');
            Route::post('/user/card_list', 'Apis\UserController@cardList');

            Route::post('/user/repay_trial', 'Apis\UserController@repayTrial');
            Route::post('/user/repay', 'Apis\UserController@repay');
            Route::post('/user/repay_detect', 'Apis\UserController@repayDetect');

            Route::post('/procedure/init', 'Apis\ProcedureController@init');
            Route::post('/procedure/open_account', 'Apis\ProcedureController@openAccount');
            Route::post('/procedure/loan_verify', 'Apis\ProcedureController@loanVerify');
            Route::post('/procedure/borrow_info', 'Apis\ProcedureController@borrowInfo');
            Route::post('/procedure/borrow_trial', 'Apis\ProcedureController@borrowTrial');
            Route::post('/procedure/order_submit', 'Apis\ProcedureController@orderSubmit');

        });

        // 不需校验登录
        Route::middleware([])->group(function () {
            Route::post('/home/index', 'Apis\HomeController@index');
            Route::post('/home/start_up', 'Apis\HomeController@startUp');
            Route::post('/home/pandora', 'Apis\HomeController@pandora');
            Route::post('/home/upgrade', 'Apis\HomeController@upgrade');

            Route::post('/bank_list', 'Apis\CommonController@bankList');
            Route::post('/addr_info', 'Apis\CommonController@addrInfo');

            Route::post('/user/verify_code', 'Apis\UserController@verifyCode');
            Route::post('/user/login', 'Apis\UserController@login');
            Route::post('/user/index', 'Apis\UserController@index');
            Route::post('/user/overdue_notify', 'Apis\UserController@overdueNotify');
        });
    });

    // 不需要校验签名
    Route::middleware([])->group(function () {
        // h5回调
        Route::get('/ping', 'Apis\HomeController@ping');
        // h5回调
        Route::get('/tp_auth/callback', 'Apis\TpAuthController@tpCallback');
        //运营商要的伪接口
        Route::post('/tp_auth/callback_operator', 'Apis\TpAuthController@operatorCallback');
        // h5异步通知
        Route::post('/tp_auth/async_notify', 'Apis\TpAuthController@asyncNotify');
        // 淘宝认证（魔蝎sdk 异步通知)
        Route::post('/tp_auth/sdk_notify_mxt', 'Apis\TpAuthController@taobaoAsyncNotifyFromMoxieSdk');
        // 运营商认证（魔蝎api 异步通知)
        Route::post('/tp_auth/api_notify_mxp', 'Apis\TpAuthController@phoneAsyncNotifyFromMoxieApi');

        Route::post('/procedure/risk_callback', 'Apis\ProcedureController@riskCallback');
        Route::get('/procedure/open_account_callback', 'Apis\ProcedureController@openAccountCallback');
        Route::post('/procedure/loan_verify_callback', 'Apis\ProcedureController@loanVerifyCallback');

        //运营商认证切换第三方,业务提供给前端h5的接口.
        Route::post('/operator/send_sms', 'Apis\TpAuthController@operatorSendSms');
        Route::post('/operator/verify_sms', 'Apis\TpAuthController@operatorVerifySms');

        // h5接口
        Route::post('/h5/verify_code', 'H5\UserController@verifyCode');
        Route::post('/h5/login', 'H5\UserController@login');
    });

});
