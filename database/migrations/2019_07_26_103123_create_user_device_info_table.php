<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUserDeviceInfoTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_device_info', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('自增id');
            $table->bigInteger('user_id')->index('user_id')->comment('用户id');
            $table->integer('device_type')->default(0)->comment('0:IOS, 1:Android');
            $table->string('device_id', 128)->default('')->comment('设备id');
            $table->string('imei')->default('')->comment('手机序列号');
            $table->string('version', 32)->default('')->comment('APP版本号');
            $table->string('push_token', 128)->default('')->comment('推送token');
            $table->string('extra', 1024)->default('')->comment('附加信息');
            $table->integer('status')->default(0)->comment('-1:失效,0:初始化，1:有效');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('user_device_info');
    }

}
