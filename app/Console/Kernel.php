<?php

namespace App\Console;

use App\Console\Commands\Auth\IdCardOcrCommand;
use App\Console\Commands\Auth\OverdueAuthInfoDetectCommand;
use App\Console\Commands\Auth\PushUserAuthInfoCommand;
use App\Console\Commands\Contracts\ContractGenEventCommand;
use App\Console\Commands\HulkEventCommand;
use App\Console\Commands\Orders\AutoRepayCommand;
use App\Console\Commands\Orders\AutoRepayForOverdueCommand;
use App\Console\Commands\Orders\OverdueDetectCommand;
use App\Console\Commands\Orders\QueryContractsFromFaceBank;
use App\Console\Commands\Orders\RechargeDetectCommand;
use App\Console\Commands\Procedures\StateCallbackCommand;
use App\Console\Commands\Procedures\StateRequestCommand;
use App\Console\Commands\Procedures\WithdrawAuthExpireCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
        OverdueDetectCommand::class,
        RechargeDetectCommand::class,
        IdCardOcrCommand::class,
        AutoRepayCommand::class,
        AutoRepayForOverdueCommand::class,
        PushUserAuthInfoCommand::class,
        StateCallbackCommand::class,
        StateRequestCommand::class,
        HulkEventCommand::class,
        ContractGenEventCommand::class,
        OverdueAuthInfoDetectCommand::class,
        QueryContractsFromFaceBank::class,
        WithdrawAuthExpireCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        /**
         * 订单相关
         */
        $schedule->command('order:auto_repay')
            ->cron('0 15,20 * * *')
            ->withoutOverlapping(60)
            ->runInBackground();
        $schedule->command('order:auto_repay_deduction')
            ->cron('0 9 * * *')
            ->withoutOverlapping(60)
            ->runInBackground();
        $schedule->command('order:auto_repay_for_overdue')
            ->cron('0 9 * * *')
            ->withoutOverlapping(60)
            ->runInBackground();
        $schedule->command('order:deduction_sync_to_legacy')
            ->cron('* * * * *')
            ->withoutOverlapping(30)
            ->runInBackground();
        $schedule->command('order:overdue_detect')
            ->cron('1 0 * * *')
            ->withoutOverlapping(60)
            ->runInBackground();
        $schedule->command('order:query_contracts_from_facebank')
            ->cron('*/10 * * * *')
            ->withoutOverlapping(30)
            ->runInBackground();
        $schedule->command('order:recharge_detect')
            ->cron('* * * * *')
            ->withoutOverlapping(2)
            ->runInBackground();
        $schedule->command('order:repay_reminds')
            ->cron('0 9,16 * * *')
            ->withoutOverlapping(60)
            ->runInBackground();
        $schedule->command('order:repay_sync_from_legacy')
            ->cron('* * * * *')
            ->withoutOverlapping(2)
            ->runInBackground();
        $schedule->command('order:repay_sync_checker')
            ->cron('59 * * * *')
            ->withoutOverlapping(20)
            ->runInBackground();

        /**
         * 授权相关
         */
        $schedule->command('auth:id_card_ocr')
            ->cron('* * * * *')
            ->withoutOverlapping(2)
            ->runInBackground();
        $schedule->command('auth:overdue_detect')
            ->cron('*/10 0 * * *')
            ->withoutOverlapping(30)
            ->runInBackground();
        $schedule->command('auth:push_auth_info')
            ->cron('* * * * *')
            ->withoutOverlapping(10)
            ->runInBackground();

        /**
         * 流程相关
         */
        $schedule->command('procedure:do_request')
            ->cron('* * * * *')
            ->withoutOverlapping(30)
            ->runInBackground();
        $schedule->command('procedure:handle_async_result')
            ->cron('* * * * *')
            ->withoutOverlapping(30)
            ->runInBackground();
        $schedule->command('procedure:withdraw_auth_expire_detect')
            ->cron('0/30 * * * *')
            ->withoutOverlapping(30)
            ->runInBackground();

        /**
         * queue相关
         */
        $schedule->command('queue:work redis --queue=default --tries=0 --stop-when-empty')
            ->cron('* * * * *')
            ->withoutOverlapping(10)
            ->runInBackground();

        /**
         * 合同相关
         */
        $schedule->command('contract:gen_event')
            ->cron('* * * * *')
            ->withoutOverlapping(10)
            ->runInBackground();

        /**
         * 业务事件相关
         */
        $schedule->command('hulk:event')
            ->cron('* * * * *')
            ->withoutOverlapping(2)
            ->runInBackground();

        /**
         * 监控相关
         */
        $schedule->command('monitor:kpi')
            ->cron('59 * * * *')
            ->withoutOverlapping(30)
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
    }
}
