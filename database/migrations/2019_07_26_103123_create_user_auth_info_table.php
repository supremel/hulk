<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUserAuthInfoTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_auth_info', function (Blueprint $table) {
            $table->bigInteger('id', true)->comment('自增id');
            $table->char('biz_no', 32)->unique('biz_uniq')->comment('业务流水号');
            $table->bigInteger('user_id')->index('user_id')->comment('用户id');
            $table->integer('type')->comment('认证类型，6:设备信息,7:运营商,8:淘宝,101:白骑士,102:人脸识别');
            $table->string('tp', 32)->default('')->comment('第三方标识');
            $table->integer('is_pushed')->default(0)->comment('是否已推送,0:否，1：是');
            $table->integer('scene')->default(0)->comment('授权场景, 0:默认，1:第一次风控，2:提交订单，3:第二次风控');
            $table->text('extra')->nullable(true)->comment('附加信息，对api方式可存放失败信息，对sdk方式可存放认证上传信息');
            $table->integer('status')->comment('-3:认证请求失败,-2:已失效,-1:认证失败，0:初始化，1:认证中，2:认证成功');
            $table->timestamps();
            $table->index(['updated_at'], 'updated_at');
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
        Schema::drop('user_auth_info');
    }

}
