<?php
/**
 * 认证-认证信息过期检测
 * User: hexuefei
 * Date: 2019-07-19
 * Time: 18:16
 */
namespace App\Console\Commands\Auth;

use App\Helpers\AuthCenter;
use App\Helpers\Locker;
use App\Models\Users;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OverdueAuthInfoDetectCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'auth:overdue_detect';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '认证-认证信息过期检测';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 定时处理过期的用户认证信息
     *
     * @return mixed
     */
    public function handle()
    {
        Log::info('module=' . $this->signature . "\tmsg=starts");
        #定时查询180天以内的活跃用户数据
        $startTime = date("Y-m-d H:i:s", strtotime("-180 day"));
        $users = Users::where('active_time', '>=', $startTime)->get()->toArray();
        $locker = new Locker();
        $lockerKeyFormat = 'auth_overdue_detect_%d';
        foreach ($users as $user) {
            if (!$locker->lock(sprintf($lockerKeyFormat, $user['id']), 6 * 60 * 60)) {
                continue;
            }
            #获取风控数据
            $overdueData = AuthCenter::getUserDataStatus($user);
            #处理有过期的数据
            AuthCenter::setExpireData($user['id'], $overdueData);
        }
        Log::info('module=' . $this->signature . "\tmsg=ends");
    }
}