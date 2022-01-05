<?php

namespace App\Console\Commands\Once;

use App\Common\OssClient;
use App\Consts\Constant;
use App\Models\AddrInfo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DataInitCommand extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'once:data_init {--addr_list} {--icons}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '初始化数据(省市区&icon)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function _initIconData()
    {
        $dir = './init_data/';
        $fd = opendir($dir);
        while (false !== ($file = readdir($fd))) {
            $type = Constant::FILE_TYPE_ICON;
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (!is_dir($dir . $file)) {
                continue;
            }
            if ($file == 'banners') {
                $type = Constant::FILE_TYPE_BANNER;
            }
            $subDir = $dir . $file . '/';
            $subFd = opendir($subDir);
            while (false !== ($subFile = readdir($subFd))) {
                if ($subFile == '.' || $subFile == '..' || is_dir($subDir . $file)) {
                    continue;
                }
                if ($subFile == '.DS_Store') {
                    continue;
                }
                $content = file_get_contents($subDir . $subFile);
                print $subDir . $subFile . " uploading...\n";
                OssClient::upload($type, $subFile, $content);
            }
        }
    }

    private function _initAddrData()
    {
        $data = file_get_contents('./init_data/city.data');
        $data = explode("\n", $data);
        $lastProvince = 0;
        $lastCity = 0;
        foreach ($data as $line) {
            if (!$line) {
                continue;
            }
            $items = explode('　', $line);
            $level = count($items);
            if ($level == 2) {  // 省
                $code = $items[0];
                $name = $items[1];
                $province = 0;
                $city = 0;
                $lastProvince = $code;
            } else if ($level == 3) { // 市
                $code = $items[0];
                $name = $items[2];
                $province = $lastProvince;
                $city = 0;
                $lastCity = $code;
            } else if ($level == 4) { // 区
                $code = $items[0];
                $name = $items[3];
                $province = $lastProvince;
                $city = $lastCity;
            } else if ($level == 5) {
                continue;
            }

            AddrInfo::create([
                'code' => $code,
                'name' => $name,
                'city' => $city,
                'province' => $province,
            ]);
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::info('module=' . $this->signature . "\tmsg=starts");
        $addrFlag = $this->option('addr_list');
        if ($addrFlag) {
            $this->_initAddrData();
        }
        $iconFlag = $this->option('icons');
        if ($iconFlag) {
            $this->_initIconData();
        }

        Log::info('module=' . $this->signature . "\tmsg=ends");
    }
}
